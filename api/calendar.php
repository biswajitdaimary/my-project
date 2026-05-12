<?php
/**
 * Unified Calendar API
 * Returns FullCalendar-compatible JSON events for Admin, Trainer, Client roles.
 * All mutations (add/edit/delete event, register, reminder) handled here.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/notification_helper.php';


header('Content-Type: application/json');

// ── Auth & Role Detection ──────────────────────────────────────────────────────
$role   = '';
$uid    = 0; // user_id (client) or trainer_id
$adminId = 0;

if (!empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        $role    = 'admin';
        $adminId = (int)($_SESSION['user_id'] ?? 0);
    } elseif ($_SESSION['role'] === 'trainer') {
        $role = 'trainer';
        $uid  = (int)($_SESSION['user_id'] ?? 0);
    } elseif ($_SESSION['role'] === 'user') {
        $role = 'client';
        $uid  = (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!$role) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'events';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

// CSRF check for mutations
if ($isPost && $action !== 'events') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

// ── Colour Map ──────────────────────────────────────────────────────────────────
function catColor(string $cat): string {
    return [
        'announcement' => '#f59e0b',
        'class'        => '#8b5cf6',
        'program'      => '#22c55e',
        'special'      => '#FF6B35',
        'reminder'     => '#8b5cf6',
        'gym_event'    => '#FF6B35',
    ][$cat] ?? '#0ea5e9';
}

// ── Helper: safe HTML ───────────────────────────────────────────────────────────
function esc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ══════════════════════════════════════════════════════════════════════════════
// GET: events feed (FullCalendar eventSources)
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'events') {
    $start = $_GET['start'] ?? date('Y-m-01');
    $end   = $_GET['end']   ?? date('Y-m-t');
    // Clamp to safe dates
    $start = preg_match('/^\d{4}-\d{2}-\d{2}/', $start) ? substr($start, 0, 10) : date('Y-m-01');
    $end   = preg_match('/^\d{4}-\d{2}-\d{2}/', $end)   ? substr($end,   0, 10) : date('Y-m-t');

    $events = [];

    // ── 1. Gym Events ───────────────────────────────────────────────────────────
    try {
        $visWhere = '';
        if ($role === 'trainer') $visWhere = "AND ge.visibility IN ('all','trainers')";
        if ($role === 'client')  $visWhere = "AND ge.visibility IN ('all','members')";

        $evStmt = $pdo->prepare("
            SELECT ge.*, t.full_name AS trainer_name,
                   (SELECT COUNT(*) FROM event_registrations WHERE event_id = ge.event_id AND status = 'registered') AS reg_count,
                   (SELECT COUNT(*) FROM event_registrations WHERE event_id = ge.event_id AND user_id = ? AND status = 'registered') AS is_registered
            FROM gym_events ge
            LEFT JOIN trainers t ON ge.trainer_id = t.trainer_id
            WHERE ge.event_date BETWEEN ? AND ?
            AND ge.event_date >= CURDATE()
            $visWhere
            ORDER BY ge.event_date, ge.start_time
        ");
        $evStmt->execute([$uid, $start, $end]);

        foreach ($evStmt->fetchAll() as $ev) {
            $color = $ev['color'] ?: catColor($ev['category']);
            $startStr = $ev['event_date'];
            $endStr   = date('Y-m-d', strtotime($ev['event_date'] . ' +1 day')); // FullCalendar end date is exclusive for allDay

            if (!$ev['all_day'] && $ev['start_time']) {
                $startStr .= 'T' . $ev['start_time'];
                $endStr   = $ev['end_time'] ? $ev['event_date'] . 'T' . $ev['end_time'] : null;
            }

            $events[] = [
                'id'           => 'ev_' . $ev['event_id'],
                'title'        => $ev['title'],
                'start'        => $startStr,
                'end'          => $endStr,
                'allDay'       => (bool)$ev['all_day'],
                'backgroundColor' => $color,
                'borderColor'  => $color,
                'textColor'    => '#fff',
                'extendedProps' => [
                    'type'         => 'gym_event',
                    'event_id'     => $ev['event_id'],
                    'category'     => $ev['category'],
                    'description'  => $ev['description'],
                    'trainer_name' => $ev['trainer_name'],
                    'max_capacity' => $ev['max_capacity'],
                    'reg_count'    => (int)$ev['reg_count'],
                    'registered'   => (bool)$ev['is_registered'],
                    'visibility'   => $ev['visibility'],
                ],
            ];
        }
    } catch (Exception $e) {}

    // ── 2. Holidays ─────────────────────────────────────────────────────────────
    try {
        $holStmt = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date");
        $holStmt->execute([$start, $end]);
        foreach ($holStmt->fetchAll() as $h) {
            if ($h['target_type'] === 'specific') {
                if ($role === 'client') {
                    continue;
                }
                if ($role === 'trainer') {
                    $tids = json_decode($h['trainer_ids'] ?? '[]', true) ?: [];
                    if (!in_array($uid, $tids, true)) continue;
                }
            }
            $color = $h['color'] ?? '#ef4444';
            $events[] = [
                'id'              => 'hol_' . $h['holiday_id'],
                'title'           => '🏖 ' . $h['title'],
                'start'           => $h['holiday_date'],
                'allDay'          => true,
                'backgroundColor' => $color,
                'borderColor'     => $color,
                'textColor'       => '#fff',
                'display'         => 'block',
                'extendedProps'   => [
                    'type'        => 'holiday',
                    'holiday_id'  => $h['holiday_id'],
                    'description' => $h['description'],
                    'hol_type'    => $h['type'],
                    'target_type' => $h['target_type'],
                    'trainer_ids' => json_decode($h['trainer_ids'] ?? '[]', true) ?: [],
                ],
            ];
        }
    } catch (Exception $e) {}

    // ── 3. Trainer Bookings ─────────────────────────────────────────────────────
    try {
        if ($role === 'trainer') {
            // The user requested to ONLY show Holiday, Event, and Reminder on the trainer calendar.
            // Bookings are managed in 'My Sessions' and 'Availability'.
            $bkStmt = null;
        } elseif ($role === 'client') {
            $bkStmt = $pdo->prepare("
                SELECT tb.*, t.full_name AS trainer_name, t.photo AS trainer_photo
                FROM trainer_bookings tb
                JOIN trainers t ON tb.trainer_id = t.trainer_id
                WHERE tb.user_id = ? AND tb.session_date BETWEEN ? AND ?
                AND tb.session_date >= CURDATE()
                AND tb.status != 'cancelled'
                ORDER BY tb.session_date, tb.start_time
            ");
            $bkStmt->execute([$uid, $start, $end]);
        } elseif ($role === 'admin') {
            $bkStmt = $pdo->prepare("
                SELECT tb.*, u.full_name AS client_name, t.full_name AS trainer_name
                FROM trainer_bookings tb
                JOIN users u ON tb.user_id = u.user_id
                JOIN trainers t ON tb.trainer_id = t.trainer_id
                WHERE tb.session_date BETWEEN ? AND ?
                AND tb.session_date >= CURDATE()
                AND tb.status != 'cancelled'
                ORDER BY tb.session_date, tb.start_time
            ");
            $bkStmt->execute([$start, $end]);
        }

        $statusColor = ['pending'=>'#f59e0b','confirmed'=>'#0ea5e9','completed'=>'#22c55e','cancelled'=>'#94a3b8'];
        if ($bkStmt) {
            foreach ($bkStmt->fetchAll() as $bk) {
                $col   = $statusColor[$bk['status']] ?? '#0ea5e9';
                $sdate = $bk['session_date'];
                $st    = substr($bk['start_time'] ?? '00:00', 0, 5);
                $et    = substr($bk['end_time']   ?? '', 0, 5);
                $title = $role === 'trainer'
                    ? '👤 ' . ($bk['client_name'] ?? 'Client')
                    : ($role === 'client' ? '🏋 ' . ($bk['trainer_name'] ?? 'Trainer') : ($bk['client_name'] . ' ↔ ' . $bk['trainer_name']));

                $ep = [
                    'type'       => 'booking',
                    'booking_id' => $bk['booking_id'],
                    'status'     => $bk['status'],
                    'notes'      => $bk['notes'] ?? '',
                ];
                if ($role === 'trainer') { $ep['client_name'] = $bk['client_name']; $ep['photo'] = $bk['profile_photo'] ?? ''; }
                if ($role === 'client')  { $ep['trainer_name'] = $bk['trainer_name']; }

                $events[] = [
                    'id'              => 'bk_' . $bk['booking_id'],
                    'title'           => $title,
                    'start'           => $sdate . 'T' . $st,
                    'end'             => $et ? $sdate . 'T' . $et : null,
                    'allDay'          => false,
                    'backgroundColor' => $col,
                    'borderColor'     => $col,
                    'textColor'       => '#fff',
                    'extendedProps'   => $ep,
                ];
            }
        }
    } catch (Exception $e) {}

    // ── 4. Availability Slots (Removed from Calendar, managed in Availability module) ──

    // ── 5. Personal Reminders ───────────────────────────────────────────────────
    try {
        if ($role === 'client') {
            $remStmt = $pdo->prepare("SELECT * FROM calendar_reminders WHERE user_id=? AND is_done=0 AND reminder_date BETWEEN ? AND ? ORDER BY reminder_date");
            $remStmt->execute([$uid, $start . ' 00:00:00', $end . ' 23:59:59']);
        } elseif ($role === 'trainer') {
            $remStmt = $pdo->prepare("SELECT * FROM calendar_reminders WHERE trainer_id=? AND is_done=0 AND reminder_date BETWEEN ? AND ? ORDER BY reminder_date");
            $remStmt->execute([$uid, $start . ' 00:00:00', $end . ' 23:59:59']);
        } else {
            $remStmt = null;
        }
        if ($remStmt) {
            foreach ($remStmt->fetchAll() as $rem) {
                $col = $rem['is_done'] ? '#94a3b8' : '#8b5cf6';
                $events[] = [
                    'id'              => 'rem_' . $rem['reminder_id'],
                    'title'           => '🔔 ' . $rem['title'],
                    'start'           => str_replace(' ', 'T', $rem['reminder_date']),
                    'allDay'          => false,
                    'backgroundColor' => $col,
                    'borderColor'     => $col,
                    'textColor'       => '#fff',
                    'extendedProps'   => [
                        'type'        => 'reminder',
                        'reminder_id' => $rem['reminder_id'],
                        'is_done'     => (bool)$rem['is_done'],
                    ],
                ];
            }
        }
    } catch (Exception $e) {}

    echo json_encode($events);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// POST MUTATIONS
// ══════════════════════════════════════════════════════════════════════════════

// ── Add/Edit Gym Event (Admin only) ──────────────────────────────────────────
if ($action === 'save_event' && $role === 'admin') {
    $eid      = (int)($_POST['event_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $date     = $_POST['event_date'] ?? '';
    $startT   = $_POST['start_time'] ?? null;
    $endT     = $_POST['end_time']   ?? null;
    $allDay   = !empty($_POST['all_day']) ? 1 : 0;
    $cat      = in_array($_POST['category'] ?? '', ['announcement','class','program','special','reminder']) ? $_POST['category'] : 'announcement';
    $color    = preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['color'] ?? '') ? $_POST['color'] : catColor($cat);
    $vis      = in_array($_POST['visibility'] ?? '', ['all','members','trainers','admin']) ? $_POST['visibility'] : 'all';
    $trId     = (int)($_POST['trainer_id'] ?? 0) ?: null;
    $cap      = (int)($_POST['max_capacity'] ?? 0) ?: null;

    if (!$title || !$date) { echo json_encode(['success'=>false,'error'=>'Title and date required']); exit; }

    if ($eid) {
        $pdo->prepare("UPDATE gym_events SET title=?,description=?,event_date=?,start_time=?,end_time=?,all_day=?,category=?,color=?,visibility=?,trainer_id=?,max_capacity=? WHERE event_id=?")
            ->execute([$title,$desc,$date,$startT,$endT,$allDay,$cat,$color,$vis,$trId,$cap,$eid]);
        echo json_encode(['success'=>true,'message'=>'Event updated']);
    } else {
        $pdo->prepare("INSERT INTO gym_events (title,description,event_date,start_time,end_time,all_day,category,color,visibility,trainer_id,max_capacity,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$title,$desc,$date,$startT,$endT,$allDay,$cat,$color,$vis,$trId,$cap,$adminId]);
        $newId = $pdo->lastInsertId(); // ✅ FIXED: called AFTER INSERT, not before
        // ── Hook: Notify target users ──
        $msg = "New $cat: $title on " . date('M j', strtotime($date));
        if (in_array($vis, ['all', 'members'])) {
            $users = $pdo->query("SELECT user_id FROM users WHERE role='user'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($users as $u) create_notification($pdo, $u, "Gym Event: $title", $msg, 'info');
        }
        if (in_array($vis, ['all', 'trainers'])) {
            $trainers = $pdo->query("SELECT trainer_id FROM trainers WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($trainers as $t) create_trainer_notification($pdo, $t, "Gym Event: $title", $msg, 'info');
        }
        echo json_encode(['success'=>true,'message'=>'Event created','event_id'=>$newId]);
    }
    exit;
}

// ── Delete Gym Event (Admin only) ─────────────────────────────────────────────
if ($action === 'delete_event' && $role === 'admin') {
    $eid = (int)($_POST['event_id'] ?? 0);
    if ($eid) {
        $pdo->prepare("DELETE FROM gym_events WHERE event_id=?")->execute([$eid]);
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'error'=>'Missing event_id']);
    }
    exit;
}

// ── Register/Unregister for class (Client only) ───────────────────────────────
if ($action === 'register_event' && $role === 'client') {
    $eid = (int)($_POST['event_id'] ?? 0);
    $toggle = ($_POST['toggle'] ?? '') === '1';
    if (!$eid) { echo json_encode(['success'=>false,'error'=>'Missing event_id']); exit; }

    // ✅ FIXED: removed duplicate prepare (first one overwrote with bool, second was redundant)
    $ev = $pdo->prepare("SELECT title, event_date, max_capacity, category, visibility FROM gym_events WHERE event_id=?");
    $ev->execute([$eid]);
    $evRow = $ev->fetch();
    if (!$evRow) { echo json_encode(['success'=>false,'error'=>'Event not found']); exit; }
    if (!in_array($evRow['category'] ?? '', ['class', 'program'], true)) {
        echo json_encode(['success'=>false,'error'=>'This event does not accept registrations']); exit;
    }
    if (!in_array($evRow['visibility'] ?? '', ['all', 'members'], true)) {
        echo json_encode(['success'=>false,'error'=>'You cannot register for this event']); exit;
    }
    if (($evRow['event_date'] ?? '') < date('Y-m-d')) {
        echo json_encode(['success'=>false,'error'=>'Cannot register for past events']); exit;
    }

    if ($toggle) {
        // Unregister
        $pdo->prepare("UPDATE event_registrations SET status='cancelled' WHERE event_id=? AND user_id=?")->execute([$eid, $uid]);
        echo json_encode(['success'=>true,'action'=>'unregistered']);
    } else {
        // Check capacity
        if ($evRow['max_capacity']) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id=? AND status='registered'");
            $cnt->execute([$eid]);
            if ((int)$cnt->fetchColumn() >= $evRow['max_capacity']) {
                echo json_encode(['success'=>false,'error'=>'Class is full']); exit;
            }
        }
        $pdo->prepare("INSERT INTO event_registrations (event_id,user_id,status) VALUES (?,?,'registered') ON DUPLICATE KEY UPDATE status='registered'")
            ->execute([$eid, $uid]);
        
        // ── Hook: Confirmation notification ──
        create_notification($pdo, $uid, "Registration Confirmed", "You are registered for " . ($evRow['title'] ?? 'event') . " on " . ($evRow['event_date'] ?? 'date'), 'success');
        
        echo json_encode(['success'=>true,'action'=>'registered']);
    }
    exit;
}

// ── Add Reminder ──────────────────────────────────────────────────────────────
if ($action === 'add_reminder') {
    $title = trim($_POST['title'] ?? '');
    $dt    = trim($_POST['reminder_date'] ?? '');
    if (!$title || !$dt) { echo json_encode(['success'=>false,'error'=>'Title and date required']); exit; }

    try {
        if ($role === 'client') {
            $stmt = $pdo->prepare("INSERT INTO calendar_reminders (user_id, title, reminder_date) VALUES (?,?,?)");
            $success = $stmt->execute([$uid, $title, $dt]);
        } elseif ($role === 'trainer') {
            $stmt = $pdo->prepare("INSERT INTO calendar_reminders (trainer_id, title, reminder_date) VALUES (?,?,?)");
            $success = $stmt->execute([$uid, $title, $dt]);
        } else {
            $success = false;
        }
        
        if ($success) {
            echo json_encode(['success'=>true, 'reminder_id'=>$pdo->lastInsertId()]);
        } else {
            error_log("Failed to insert reminder: UID=$uid, Role=$role, Title=$title");
            echo json_encode(['success'=>false, 'error'=>'Database insertion failed']);
        }
    } catch (Exception $e) {
        error_log("Add Reminder Exception: " . $e->getMessage());
        echo json_encode(['success'=>false, 'error'=>'System error: ' . $e->getMessage()]);
    }
    exit;
}

// ── Toggle Reminder Done ──────────────────────────────────────────────────────
if ($action === 'toggle_reminder') {
    $rid = (int)($_POST['reminder_id'] ?? 0);
    try {
        if ($role === 'client') {
            $pdo->prepare("UPDATE calendar_reminders SET is_done=1-is_done WHERE reminder_id=? AND user_id=?")->execute([$rid,$uid]);
        } elseif ($role === 'trainer') {
            $pdo->prepare("UPDATE calendar_reminders SET is_done=1-is_done WHERE reminder_id=? AND trainer_id=?")->execute([$rid,$uid]);
        }
        echo json_encode(['success'=>true]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

// ── Save/Delete Holiday (Admin only) ─────────────────────────────────────────
if ($action === 'save_holiday' && $role === 'admin') {
    $hid  = (int)($_POST['holiday_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $date  = $_POST['holiday_date'] ?? '';
    $desc  = trim($_POST['description'] ?? '');
    $type  = $_POST['type'] === 'partial' ? 'partial' : 'full';
    $color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#ef4444';
    $target = $_POST['target_type'] === 'specific' ? 'specific' : 'all';
    $tids = null;
    if ($target === 'specific' && !empty($_POST['trainer_ids'])) {
        $tids = json_encode(array_map('intval', (array)$_POST['trainer_ids']));
    }
    if (!$title || !$date) { echo json_encode(['success'=>false,'error'=>'Title and date required']); exit; }
    if ($hid) {
        $pdo->prepare("UPDATE holidays SET title=?,holiday_date=?,description=?,type=?,color=?,target_type=?,trainer_ids=? WHERE holiday_id=?")
            ->execute([$title,$date,$desc,$type,$color,$target,$tids,$hid]);
    } else {
        $pdo->prepare("INSERT INTO holidays (title,holiday_date,description,type,color,target_type,trainer_ids) VALUES (?,?,?,?,?,?,?)")
            ->execute([$title,$date,$desc,$type,$color,$target,$tids]);
    }
    echo json_encode(['success'=>true]);
    exit;
}

if ($action === 'delete_holiday' && $role === 'admin') {
    $hid = (int)($_POST['holiday_id'] ?? 0);
    if ($hid) { $pdo->prepare("DELETE FROM holidays WHERE holiday_id=?")->execute([$hid]); }
    echo json_encode(['success'=>true]);
    exit;
}

// ── Update Booking Status & Auto-Reminder ────────────────────────────────────
if ($action === 'update_booking_status') {
    $bid    = (int)($_POST['booking_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (!$bid || !in_array($status, ['confirmed','completed','cancelled'])) {
        echo json_encode(['success'=>false,'error'=>'Invalid request']); exit;
    }
    if (!in_array($role, ['admin', 'trainer'], true)) {
        echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit;
    }

    // Auth check: only trainer assigned to booking or admin
    $bk = $pdo->prepare("SELECT * FROM trainer_bookings WHERE booking_id=?");
    $bk->execute([$bid]);
    $bkRow = $bk->fetch();
    if (!$bkRow || ($role === 'trainer' && (int)$bkRow['trainer_id'] !== $uid)) {
        echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE trainer_bookings SET status=? WHERE booking_id=?")->execute([$status, $bid]);

        if ($status === 'cancelled' && !empty($bkRow['slot_id'])) {
            $pdo->prepare("UPDATE availability_slots SET status='available' WHERE id=?")->execute([$bkRow['slot_id']]);
        }

        // ── Hook: Auto-reminder for confirmed sessions ──
        if ($status === 'confirmed') {
            $remDate = date('Y-m-d H:i:s', strtotime($bkRow['session_date'] . ' ' . $bkRow['start_time'] . ' -1 hour'));
            // For client
            $pdo->prepare("INSERT INTO calendar_reminders (user_id, title, reminder_date) VALUES (?,?,?)")
                ->execute([$bkRow['user_id'], "Session Reminder: Upcoming workout today", $remDate]);
            // For trainer
            $pdo->prepare("INSERT INTO calendar_reminders (trainer_id, title, reminder_date) VALUES (?,?,?)")
                ->execute([$bkRow['trainer_id'], "Session Reminder: Upcoming client session", $remDate]);

            create_notification($pdo, $bkRow['user_id'], "Booking Confirmed", "Your session on " . date('M j', strtotime($bkRow['session_date'])) . " has been confirmed.", 'success');
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success'=>false,'error'=>'Failed to update booking status']); exit;
    }

    echo json_encode(['success'=>true]);
    exit;
}

// ── Upcoming events list ──────────────────────────────────────────────────────
// ── My Reminders (sidebar) ────────────────────────────────────────────────────
if ($action === 'my_reminders') {
    $reminders = [];
    try {
        error_log("my_reminders called with role=$role, uid=$uid");
        if ($role === 'trainer') {
            $st = $pdo->prepare("SELECT reminder_id, title, reminder_date FROM calendar_reminders WHERE trainer_id = ? AND is_done = 0 ORDER BY reminder_date ASC LIMIT 10");
            $st->execute([$uid]);
            $reminders = $st->fetchAll();
            error_log("my_reminders trainer fetch count: " . count($reminders));
        } elseif ($role === 'client') {
            $st = $pdo->prepare("SELECT reminder_id, title, reminder_date FROM calendar_reminders WHERE user_id = ? AND is_done = 0 ORDER BY reminder_date ASC LIMIT 10");
            $st->execute([$uid]);
            $reminders = $st->fetchAll();
        }
    } catch (Exception $e) {
        error_log('my_reminders error: ' . $e->getMessage());
    }
    echo json_encode(['success' => true, 'reminders' => $reminders, 'uid' => $uid, 'role' => $role]);
    exit;
}

if ($action === 'upcoming') {
    $limit = min((int)($_GET['limit'] ?? 5), 20);
    $today = date('Y-m-d');
    $list  = [];
    try {
        $vis = ($role === 'client') ? "AND visibility IN ('all','members')" : (($role === 'trainer') ? "AND visibility IN ('all','trainers')" : '');
        $st = $pdo->prepare("SELECT event_id,title,event_date,start_time,category,color FROM gym_events WHERE event_date >= ? $vis ORDER BY event_date,start_time LIMIT $limit");
        $st->execute([$today]);
        foreach ($st->fetchAll() as $r) { $r['type']='event'; $list[] = $r; }
    } catch(Exception $e){}
    echo json_encode($list);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
