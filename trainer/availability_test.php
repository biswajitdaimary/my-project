<?php
require_once '../config/config.php';



$trainer_id = $_SESSION['user_id'];

// --- 1) Handle POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_slot') {
            $date = trim($_POST['date'] ?? '');
            $start = trim($_POST['start_time'] ?? '');
            $end = trim($_POST['end_time'] ?? '');
            if (!$date || !$start || !$end) throw new Exception("All fields required");
            if ($start >= $end) throw new Exception("End time must be after start time");

            $chk = $pdo->prepare("SELECT id FROM availability_slots WHERE trainer_id=? AND date=? AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))");
            $chk->execute([$trainer_id, $date, $end, $start, $start, $end]);
            if ($chk->fetch()) throw new Exception("Slot overlaps with an existing slot");

            $pdo->prepare("INSERT INTO availability_slots (trainer_id, date, start_time, end_time, status) VALUES (?, ?, ?, ?, 'available')")
                ->execute([$trainer_id, $date, $start, $end]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'quick_toggle') {
            $id = (int)$_POST['id'];
            $state = $_POST['state'] === 'available' ? 'available' : 'blocked';
            
            $stmt = $pdo->prepare("SELECT status FROM availability_slots WHERE id=? AND trainer_id=?");
            $stmt->execute([$id, $trainer_id]);
            $slot = $stmt->fetch();
            if (!$slot) throw new Exception("Slot not found");
            if ($slot['status'] === 'booked') throw new Exception("Cannot toggle a booked slot");

            $pdo->prepare("UPDATE availability_slots SET status=? WHERE id=?")->execute([$state, $id]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'delete_slot') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("SELECT status FROM availability_slots WHERE id=? AND trainer_id=?");
            $stmt->execute([$id, $trainer_id]);
            $slot = $stmt->fetch();
            if (!$slot) throw new Exception("Slot not found");
            if ($slot['status'] === 'booked') throw new Exception("Cannot delete a booked slot");

            $pdo->prepare("DELETE FROM availability_slots WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'bulk_action') {
            $ids = json_decode($_POST['ids'] ?? '[]');
            $type = $_POST['type'] ?? '';
            if (!is_array($ids) || empty($ids)) throw new Exception("No slots selected");

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$trainer_id], $ids);

            if ($type === 'delete') {
                $pdo->prepare("DELETE FROM availability_slots WHERE trainer_id=? AND status!='booked' AND id IN ($placeholders)")->execute($params);
            } elseif ($type === 'enable') {
                $pdo->prepare("UPDATE availability_slots SET status='available' WHERE trainer_id=? AND status!='booked' AND id IN ($placeholders)")->execute($params);
            } elseif ($type === 'block') {
                $pdo->prepare("UPDATE availability_slots SET status='blocked' WHERE trainer_id=? AND status!='booked' AND id IN ($placeholders)")->execute($params);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'auto_gen') {
            $date = trim($_POST['date'] ?? '');
            $start = trim($_POST['start_time'] ?? '');
            $end = trim($_POST['end_time'] ?? '');
            $dur = (int)($_POST['duration'] ?? 60);
            $gap = (int)($_POST['gap'] ?? 0);

            if (!$date || !$start || !$end || $dur <= 0) throw new Exception("Invalid parameters");
            if ($start >= $end) throw new Exception("End time must be after start time");

            $curr = strtotime("$date $start");
            $limit = strtotime("$date $end");
            $added = 0;

            while ($curr + ($dur * 60) <= $limit) {
                $slotStart = date('H:i:s', $curr);
                $slotEnd = date('H:i:s', $curr + ($dur * 60));

                $chk = $pdo->prepare("SELECT id FROM availability_slots WHERE trainer_id=? AND date=? AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))");
                $chk->execute([$trainer_id, $date, $slotEnd, $slotStart, $slotStart, $slotEnd]);
                
                if (!$chk->fetch()) {
                    $pdo->prepare("INSERT INTO availability_slots (trainer_id, date, start_time, end_time, status) VALUES (?, ?, ?, ?, 'available')")
                        ->execute([$trainer_id, $date, $slotStart, $slotEnd]);
                    $added++;
                }

                $curr += ($dur + $gap) * 60;
            }

            echo json_encode(['success' => true, 'message' => "Generated $added slots"]);
            exit;
        }

        throw new Exception("Unknown action");
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// --- 2) Prepare Data for Frontend ---
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$todayStr = date('Y-m-d');

$startOfWeek = new DateTime();
$startOfWeek->modify(($weekOffset * 7) . ' days');
$startOfWeek->modify('monday this week');

$weekDates = [];
$weekLabels = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $startOfWeek;
    $d->modify("+$i days");
    $weekDates[] = $d->format('Y-m-d');
    $weekLabels[] = [
        'dname' => $d->format('D'),
        'ddate' => $d->format('j'),
        'month' => $d->format('M'),
        'full'  => $d->format('Y-m-d')
    ];
}

$startStr = $weekDates[0];
$endStr = $weekDates[6];

// Fetch slots
$stmt = $pdo->prepare("SELECT * FROM availability_slots WHERE trainer_id=? AND date >= ? AND date <= ? ORDER BY start_time");
$stmt->execute([$trainer_id, $startStr, $endStr]);
$slotsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$slotsByDate = array_fill_keys($weekDates, []);
$stats = ['available' => 0, 'booked' => 0, 'blocked' => 0];

foreach ($slotsRaw as $s) {
    $d = $s['date'];
    if (isset($slotsByDate[$d])) {
        $slotsByDate[$d][] = $s;
        if (isset($stats[$s['status']])) {
            $stats[$s['status']]++;
        }
    }
}

// Fetch holidays
$hStmt = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date >= ? AND holiday_date <= ?");
$hStmt->execute([$startStr, $endStr]);
$holidaysRaw = $hStmt->fetchAll(PDO::FETCH_ASSOC);
$holidays = [];
foreach ($holidaysRaw as $h) {
    $tids = json_decode($h['trainer_ids'], true) ?: [];
    if ($h['target_type'] === 'all' || in_array($trainer_id, $tids)) {
        $holidays[$h['holiday_date']] = $h['title'];
    }
}

$pageTitle = "Schedule & Availability | Trainer Portal";
include 'includes/trainer_header.php';
?>

<style>
/* ── Layout ──────────────────────────────────────────── */
.av-page{padding:1.5rem 2rem;}
.av-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;}
.av-title{font-size:1.45rem;font-weight:800;color:#1e293b;margin:0;}
.av-subtitle{font-size:.83rem;color:#64748b;margin-top:.15rem;}
.av-actions{display:flex;gap:.6rem;flex-wrap:wrap;}

/* ── Week Nav ────────────────────────────────────────── */
.week-nav{display:flex;align-items:center;gap:.5rem;background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;padding:.35rem .6rem;}
.week-nav button{background:none;border:none;width:30px;height:30px;border-radius:8px;cursor:pointer;color:#475569;display:flex;align-items:center;justify-content:center;transition:all .15s;}
.week-nav button:hover{background:#f1f5f9;color:#0ea5e9;}
.week-label{font-size:.82rem;font-weight:700;color:#334155;white-space:nowrap;padding:0 .25rem;}

/* ── Stat Pills ──────────────────────────────────────── */
.stat-row{display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap;}
.stat-pill{display:flex;align-items:center;gap:.45rem;padding:.38rem .85rem;border-radius:100px;font-size:.78rem;font-weight:700;}

/* ── Board ───────────────────────────────────────────── */
.av-board{display:grid;grid-template-columns:repeat(7,minmax(145px,1fr));gap:.75rem;overflow-x:auto;padding-bottom:1rem;}
.day-col{display:flex;flex-direction:column;gap:.5rem;}
.day-head{text-align:center;padding:.5rem .25rem .6rem;border-radius:10px;background:#fff;border:1.5px solid #e2e8f0;}
.day-head .dname{font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;}
.day-head .ddate{font-size:1.05rem;font-weight:800;color:#1e293b;line-height:1;}
.day-head.today{background:linear-gradient(135deg,#0ea5e9,#38bdf8);border-color:#0ea5e9;}
.day-head.today .dname,.day-head.today .ddate{color:#fff;}

/* ── Slot Card ───────────────────────────────────────── */
.slot-card{background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;padding:.65rem .75rem;cursor:pointer;position:relative;transition:all .2s cubic-bezier(.4,0,.2,1);border-left:4px solid #e2e8f0;overflow:hidden;}
.slot-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.09);}
.slot-card.available{border-left-color:#22c55e;background:#fff;}
.slot-card.available:hover{box-shadow:0 6px 20px rgba(34,197,94,.15);}
.slot-card.booked{border-left-color:#ef4444;background:linear-gradient(135deg,#fef2f2,#fff);cursor:default;}
.slot-card.booked:hover{box-shadow:0 6px 20px rgba(239,68,68,.15);}
.slot-card.blocked{border-left-color:#94a3b8;background:#f8fafc;opacity:.8;}
.slot-card.blocked:hover{opacity:1;box-shadow:0 6px 20px rgba(148,163,184,.15);}
.slot-card.bulk-selected{outline:2.5px solid #0ea5e9;box-shadow:0 0 0 4px rgba(14,165,233,.15);}

.sc-time{font-size:.78rem;font-weight:800;color:#1e293b;white-space:nowrap;}
.sc-badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:.18rem .5rem;border-radius:100px;margin-top:.25rem;}
.sc-badge.available{background:rgba(34,197,94,.12);color:#16a34a;}
.sc-badge.booked{background:rgba(239,68,68,.12);color:#dc2626;}
.sc-badge.blocked{background:#f1f5f9;color:#64748b;}

/* quick toggle */
.qt{position:relative;display:inline-block;width:30px;height:16px;flex-shrink:0;}
.qt input{opacity:0;width:0;height:0;}
.qt-slider{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:34px;transition:.2s;}
.qt-slider:before{position:absolute;content:"";height:12px;width:12px;left:2px;bottom:2px;background:#fff;border-radius:50%;transition:.2s;}
input:checked+.qt-slider{background:#22c55e;}
input:checked+.qt-slider:before{transform:translateX(14px);}

/* Add-slot btn */
.add-slot-btn{display:flex;align-items:center;justify-content:center;gap:.35rem;padding:.5rem;border:1.5px dashed #cbd5e1;border-radius:10px;background:transparent;color:#94a3b8;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .18s;width:100%;}
.add-slot-btn:hover{border-color:#0ea5e9;color:#0ea5e9;background:rgba(14,165,233,.04);}

/* ── Bulk Bar ─────────────────────────────────────────── */
.bulk-bar{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;border-radius:16px;padding:.75rem 1.25rem;display:flex;align-items:center;gap:1rem;box-shadow:0 12px 40px rgba(0,0,0,.25);z-index:500;flex-wrap:wrap;justify-content:center;animation:slideUp .25s ease;}
@keyframes slideUp{from{transform:translateX(-50%) translateY(20px);opacity:0}to{transform:translateX(-50%) translateY(0);opacity:1}}
.bulk-count{font-size:.82rem;font-weight:700;background:rgba(255,255,255,.1);padding:.25rem .6rem;border-radius:8px;}
.bulk-btn{padding:.4rem .9rem;border-radius:10px;font-size:.8rem;font-weight:700;border:none;cursor:pointer;transition:all .15s;}
.bulk-btn.enable{background:#22c55e;color:#fff;}
.bulk-btn.block{background:#ef4444;color:#fff;}
.bulk-btn.cancel{background:rgba(255,255,255,.1);color:#fff;}
.bulk-btn:hover{filter:brightness(1.1);}
.bulk-all-label{font-size:.78rem;color:#94a3b8;display:flex;align-items:center;gap:.4rem;cursor:pointer;}

/* ── Modals ──────────────────────────────────────────── */
.av-modal-bg{display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(3px);padding:1rem;}
.av-modal-bg.open{display:flex;animation:fadeIn .18s ease;}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.av-modal{background:#fff;border-radius:18px;width:100%;max-width:420px;overflow:hidden;animation:popUp .25s cubic-bezier(.34,1.56,.64,1);}
@keyframes popUp{from{transform:scale(.88);opacity:0}to{transform:scale(1);opacity:1}}
.modal-hd{padding:1.25rem 1.5rem;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;}
.modal-hd h6{margin:0;font-weight:800;color:#1e293b;font-size:1rem;}
.modal-bd{padding:1.25rem 1.5rem;}
.modal-ft{padding:1rem 1.5rem;background:#f8fafc;display:flex;gap:.75rem;border-top:1px solid #f1f5f9;}
.mbtn{flex:1;padding:.6rem;border-radius:10px;font-weight:700;font-size:.85rem;border:none;cursor:pointer;transition:all .15s;}
.mbtn.primary{background:#0ea5e9;color:#fff;}
.mbtn.primary:hover{background:#0284c7;}
.mbtn.danger{background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca;}
.mbtn.danger:hover{background:#fee2e2;}
.mbtn.ghost{background:#f1f5f9;color:#475569;}
.mbtn.ghost:hover{background:#e2e8f0;}
.form-label-sm{font-size:.78rem;font-weight:700;color:#374151;margin-bottom:.35rem;display:block;}
.form-ctrl{width:100%;padding:.55rem .75rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.85rem;font-family:inherit;outline:none;transition:border .15s;}
.form-ctrl:focus{border-color:#0ea5e9;box-shadow:0 0 0 3px rgba(14,165,233,.1);}

/* ── Toast ───────────────────────────────────────────── */
.av-toast{position:fixed;bottom:1.5rem;right:1.5rem;background:#1e293b;color:#fff;padding:.7rem 1.2rem;border-radius:12px;font-size:.82rem;font-weight:600;z-index:2000;display:flex;align-items:center;gap:.6rem;box-shadow:0 8px 24px rgba(0,0,0,.2);animation:slideUp .2s ease;max-width:280px;}
.av-toast.success .ti{color:#22c55e;}
.av-toast.error .ti{color:#ef4444;}

/* ── Buttons ─────────────────────────────────────────── */
.btn-av{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;border-radius:10px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;transition:all .18s;}
.btn-av.primary{background:#0ea5e9;color:#fff;}
.btn-av.orange{background:#FF6B35;color:#fff;}
.btn-av.outline{background:#fff;border:1.5px solid #e2e8f0;color:#475569;}
.btn-av.bulk-mode{background:rgba(14,165,233,.1);color:#0ea5e9;border:1.5px solid #bae6fd;}
.btn-av.bulk-mode.active{background:#0ea5e9;color:#fff;}

@media(max-width:768px){.av-board{grid-template-columns:repeat(7,minmax(120px,1fr));}.av-page{padding:1rem;}}

/* ── View Toggle ─────────────────────────────────────────────── */
.view-toggle{display:flex;background:#f1f5f9;border-radius:10px;padding:.2rem;gap:.15rem;}
.vtbtn{padding:.35rem .75rem;border-radius:8px;border:none;background:transparent;font-size:.78rem;font-weight:600;color:#64748b;cursor:pointer;transition:all .18s;display:flex;align-items:center;gap:.35rem;white-space:nowrap;}
.vtbtn.active{background:#fff;color:#0ea5e9;box-shadow:0 1px 4px rgba(0,0,0,.1);}

/* ── Day-Picker ──────────────────────────────────────────────── */
.day-picker{display:flex;gap:.4rem;flex-wrap:nowrap;overflow-x:auto;padding:.25rem 0;}
.dp-btn{min-width:52px;padding:.45rem .5rem;border-radius:10px;border:1.5px solid #e2e8f0;background:#fff;text-align:center;cursor:pointer;transition:all .18s;}
.dp-btn .dp-name{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;}
.dp-btn .dp-num{font-size:1.05rem;font-weight:800;color:#1e293b;line-height:1.2;}
.dp-btn.today-btn{border-color:#0ea5e9;}
.dp-btn.today-btn .dp-name,.dp-btn.today-btn .dp-num{color:#0ea5e9;}
.dp-btn.active-day{background:#0ea5e9;border-color:#0ea5e9;}
.dp-btn.active-day .dp-name,.dp-btn.active-day .dp-num{color:#fff;}

/* ── Day View ────────────────────────────────────────────────── */
#dayView{display:none;}
.dv-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem;}
.dv-date-label{font-size:1.1rem;font-weight:800;color:#1e293b;}
.dv-subtitle{font-size:.8rem;color:#64748b;margin-top:.15rem;}
.dv-timeline{display:flex;flex-direction:column;gap:.6rem;max-width:560px;}
.dv-slot{display:flex;align-items:center;gap:1rem;background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;padding:.85rem 1.1rem;border-left:5px solid #e2e8f0;transition:all .2s;cursor:pointer;}
.dv-slot:hover{transform:translateX(4px);box-shadow:0 4px 16px rgba(0,0,0,.08);}
.dv-slot.available{border-left-color:#22c55e;}
.dv-slot.booked{border-left-color:#ef4444;cursor:default;}
.dv-slot.blocked{border-left-color:#94a3b8;opacity:.8;}
.dv-time-block{min-width:70px;text-align:center;}
.dv-time{font-size:.92rem;font-weight:800;color:#1e293b;}
.dv-dur{font-size:.68rem;color:#94a3b8;margin-top:.1rem;}
.dv-divider{width:1px;height:36px;background:#e2e8f0;flex-shrink:0;}
.dv-info{flex:1;}
.dv-status-badge{font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:.18rem .55rem;border-radius:100px;}
.dv-status-badge.available{background:rgba(34,197,94,.12);color:#16a34a;}
.dv-status-badge.booked{background:rgba(239,68,68,.12);color:#dc2626;}
.dv-status-badge.blocked{background:#f1f5f9;color:#64748b;}
.dv-empty{text-align:center;padding:3rem 1rem;color:#94a3b8;}
.dv-empty i{font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.3;}

/* ── Calendar View ───────────────────────────────────────────── */
#calView{display:none;}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:.4rem;}
.cal-dow{text-align:center;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;padding:.5rem 0;}
.cal-cell{border:1.5px solid #e2e8f0;border-radius:10px;padding:.6rem .5rem;min-height:72px;background:#fff;cursor:pointer;transition:all .18s;position:relative;}
.cal-cell:hover{border-color:#0ea5e9;box-shadow:0 4px 12px rgba(14,165,233,.1);}
.cal-cell.cal-today{border-color:#0ea5e9;background:linear-gradient(135deg,rgba(14,165,233,.06),#fff);}
.cal-cell.cal-other-month{background:#f8fafc;opacity:.5;cursor:default;}
.cal-cell.cal-past{opacity:.45;cursor:default;}
.cal-cell.cal-selected{border-color:#0ea5e9;box-shadow:0 0 0 3px rgba(14,165,233,.15);}
.cal-day-num{font-size:.88rem;font-weight:800;color:#1e293b;margin-bottom:.3rem;}
.cal-today .cal-day-num{color:#0ea5e9;}
.cal-dots{display:flex;gap:3px;flex-wrap:wrap;}
.cal-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
.cal-dot.available{background:#22c55e;}
.cal-dot.booked{background:#ef4444;}
.cal-dot.blocked{background:#94a3b8;}
.cal-slot-count{position:absolute;top:5px;right:7px;font-size:.6rem;font-weight:800;color:#94a3b8;}
.cal-nav{display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;}
.cal-nav button{background:#f1f5f9;border:none;width:32px;height:32px;border-radius:8px;cursor:pointer;color:#475569;display:flex;align-items:center;justify-content:center;transition:all .15s;}
.cal-nav button:hover{background:#0ea5e9;color:#fff;}
.cal-month-label{font-size:1rem;font-weight:800;color:#1e293b;flex:1;text-align:center;}
</style>

<div class="av-page">
    <div class="av-header">
        <div>
            <h1 class="av-title"><i class="fa-solid fa-calendar-week me-2" style="color:#0ea5e9;"></i>Schedule & Availability</h1>
            <p class="av-subtitle" id="avSubtitle">Manage your weekly slots. Click any slot to edit or view booking details.</p>
        </div>
        <div class="av-actions">
            <div class="view-toggle" id="viewToggle">
                <button class="vtbtn" id="vt-day" onclick="switchView('day')"><i class="fa-solid fa-calendar-day"></i> Day</button>
                <button class="vtbtn active" id="vt-week" onclick="switchView('week')"><i class="fa-solid fa-calendar-week"></i> Week</button>
                <button class="vtbtn" id="vt-cal" onclick="switchView('cal')"><i class="fa-solid fa-calendar"></i> Month</button>
            </div>
            <div class="week-nav" id="weekNavBar">
                <a href="?week=<?= $weekOffset - 1 ?>"><button type="button"><i class="fa-solid fa-chevron-left"></i></button></a>
                <span class="week-label"><?= date('M j', strtotime($startStr)) ?> &ndash; <?= date('M j', strtotime($endStr)) ?></span>
                <a href="?week=<?= $weekOffset + 1 ?>"><button type="button"><i class="fa-solid fa-chevron-right"></i></button></a>
            </div>
            <button class="btn-av bulk-mode" id="bulkToggle" onclick="toggleBulk()"><i class="fa-solid fa-layer-group"></i> Bulk Select</button>
            <button class="btn-av orange" onclick="openAutoGen()"><i class="fa-solid fa-bolt"></i> Auto-Generate</button>
        </div>
    </div>

    <!-- Stats -->
    <div class="stat-row" id="statRow">
        <div class="stat-pill" style="background:rgba(34,197,94,.1);color:#16a34a;"><i class="fa-solid fa-circle-check"></i> <span id="totAv"><?= $stats['available'] ?></span> Available</div>
        <div class="stat-pill" style="background:rgba(239,68,68,.1);color:#dc2626;"><i class="fa-solid fa-calendar-check"></i> <span id="totBk"><?= $stats['booked'] ?></span> Booked</div>
        <div class="stat-pill" style="background:#f1f5f9;color:#64748b;"><i class="fa-solid fa-ban"></i> <span id="totBl"><?= $stats['blocked'] ?></span> Blocked</div>
    </div>

    <!-- Day picker (shown in Day View) -->
    <div id="dayPickerBar" style="display:none;margin-bottom:1rem;">
        <div class="day-picker">
            <?php foreach ($weekLabels as $lbl): ?>
            <div class="dp-btn <?= $lbl['full'] === $todayStr ? 'today-btn' : '' ?>" data-date="<?= $lbl['full'] ?>" onclick="selectDay('<?= $lbl['full'] ?>')">
                <div class="dp-name"><?= $lbl['dname'] ?></div>
                <div class="dp-num"><?= $lbl['ddate'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Board -->
    <div class="av-board" id="avBoard">
        <?php foreach ($weekLabels as $lbl): 
            $d = $lbl['full'];
            $isToday = ($d === $todayStr);
            $hasHol = isset($holidays[$d]);
            $dSlots = $slotsByDate[$d] ?? [];
        ?>
        <div class="day-col" data-date="<?= $d ?>">
            <div class="day-head <?= $isToday ? 'today' : '' ?>" <?= $hasHol ? 'style="border-color:#ef4444; background:linear-gradient(135deg, #fef2f2, #fff);"' : '' ?>>
                <div class="dname" <?= $hasHol ? 'style="color:#ef4444;"' : '' ?>><?= $lbl['dname'] ?></div>
                <div class="ddate" <?= $hasHol ? 'style="color:#ef4444;"' : '' ?>><?= $lbl['ddate'] ?></div>
                <div style="font-size:.65rem;color:<?= $isToday ? 'rgba(255,255,255,.75)' : ($hasHol?'#f87171':'#94a3b8') ?>;margin-top:.1rem;"><?= $lbl['month'] ?></div>
                <?php if ($hasHol): ?>
                    <div style="font-size:0.65rem; font-weight:800; color:#ef4444; margin-top:0.3rem; background:rgba(239,68,68,0.1); border-radius:4px; padding:0.15rem; text-transform:uppercase;">Holiday</div>
                <?php endif; ?>
            </div>

            <?php if ($hasHol): ?>
                <div style="text-align:center;padding:1.5rem .5rem;color:#ef4444;font-size:.85rem;font-weight:700;display:flex;flex-direction:column;align-items:center;opacity:0.6;">
                    <i class="fa-solid fa-umbrella-beach d-block mb-2 fa-2x"></i>
                    <div><?= htmlspecialchars($holidays[$d]) ?></div>
                </div>
            <?php else: ?>
                <?php if (empty($dSlots)): ?>
                    <div style="text-align:center;padding:1rem .5rem;color:#cbd5e1;font-size:.72rem;">No slots</div>
                <?php else: ?>
                    <?php foreach ($dSlots as $slot): 
                        $statusClass = $slot['status']; 
                        $chk = $statusClass === 'available' ? 'checked' : '';
                        $stime = date('h:i A', strtotime($slot['start_time']));
                    ?>
                    <div class="slot-card <?= $statusClass ?>" data-id="<?= $slot['id'] ?>" data-state="<?= $slot['status'] ?>" title="Click to edit" onclick="handleClick(this)">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:.35rem;">
                            <div style="min-width:0;">
                                <div class="sc-time" style="overflow:hidden;text-overflow:ellipsis;"><?= $stime ?></div>
                                <div class="sc-badge <?= $statusClass ?>">
                                    <i class="fa-solid fa-circle" style="font-size:.4rem;"></i> <?= ucfirst($slot['status']) ?>
                                </div>
                            </div>
                            <?php if ($statusClass !== 'booked'): ?>
                            <label class="qt" onclick="event.stopPropagation()" title="Toggle status">
                                <input type="checkbox" class="qt-cb" data-id="<?= $slot['id'] ?>" <?= $chk ?> onchange="quickToggle(<?= $slot['id'] ?>,this)">
                                <span class="qt-slider"></span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <button class="add-slot-btn" onclick="openAddSlot('<?= $d ?>')">
                    <i class="fa-solid fa-plus"></i> Add Slot
                </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Day View -->
    <div id="dayView">
        <div class="dv-header">
            <div>
                <div class="dv-date-label" id="dvDateLabel">Select a day</div>
                <div class="dv-subtitle">Review slots and appointments for this specific date.</div>
            </div>
            <button class="btn-av primary" id="dvAddBtn" style="display:none;"><i class="fa-solid fa-plus"></i> Add Slot</button>
        </div>
        <div class="dv-timeline" id="dvTimeline"></div>
    </div>

    <!-- Calendar View -->
    <div id="calView">
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div style="background:#fff; border:1.5px solid #e2e8f0; border-radius:14px; padding:1.25rem;">
                    <div class="cal-nav">
                        <button onclick="calPrev()"><i class="fa-solid fa-chevron-left"></i></button>
                        <div class="cal-month-label" id="calMonthLabel">Month Year</div>
                        <button onclick="calNext()"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <div class="cal-grid">
                        <div class="cal-dow">Sun</div><div class="cal-dow">Mon</div><div class="cal-dow">Tue</div><div class="cal-dow">Wed</div>
                        <div class="cal-dow">Thu</div><div class="cal-dow">Fri</div><div class="cal-dow">Sat</div>
                    </div>
                    <div class="cal-grid" id="calGrid"></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div id="calDayPanel" style="background:#fff; border:1.5px solid #e2e8f0; border-radius:14px; padding:1.25rem; display:none;">
                    <h6 id="calPanelTitle" style="font-weight:800; color:#1e293b; margin-bottom:1rem; padding-bottom:.5rem; border-bottom:1px solid #e2e8f0;">Date</h6>
                    <div class="dv-timeline" id="calPanelTimeline"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Bar -->
<div class="bulk-bar" id="bulkBar" style="display:none;">
    <div class="d-flex align-items-center gap-3">
        <label class="bulk-all-label"><input type="checkbox" id="bulkAll" onchange="toggleAllBulk(this)"> Select All</label>
        <span class="bulk-count"><span id="bulkCount">0</span> selected</span>
    </div>
    <div style="display:flex;gap:.5rem;">
        <button class="bulk-btn enable" onclick="bulkAction('enable')"><i class="fa-solid fa-check"></i> Make Available</button>
        <button class="bulk-btn block" onclick="bulkAction('block')"><i class="fa-solid fa-ban"></i> Block</button>
        <button class="bulk-btn cancel" onclick="toggleBulk()"><i class="fa-solid fa-xmark"></i> Cancel</button>
    </div>
</div>

<!-- Modal: Edit Slot -->
<div class="av-modal-bg" id="editModal">
    <div class="av-modal">
        <div class="modal-hd">
            <h6><i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit Slot</h6>
            <button class="btn-close" style="font-size:.8rem;" onclick="closeModal('editModal')"></button>
        </div>
        <div class="modal-bd">
            <input type="hidden" id="editSlotId">
            <div class="mb-3">
                <label class="form-label-sm">Status</label>
                <select class="form-ctrl" id="editSlotStatus">
                    <option value="available">Available</option>
                    <option value="blocked">Blocked</option>
                </select>
            </div>
            <div class="text-muted" style="font-size:.75rem;">
                <i class="fa-solid fa-circle-info me-1"></i> Use Auto-Generate or Add Slot to create new times.
            </div>
        </div>
        <div class="modal-ft">
            <button class="mbtn danger" onclick="deleteCurrentSlot()"><i class="fa-solid fa-trash me-1"></i> Delete</button>
            <button class="mbtn ghost" onclick="closeModal('editModal')">Cancel</button>
            <button class="mbtn primary" onclick="saveSlotEdit()"><i class="fa-solid fa-floppy-disk me-1"></i> Save</button>
        </div>
    </div>
</div>

<!-- Modal: Add Slot -->
<div class="av-modal-bg" id="addModal">
    <div class="av-modal">
        <div class="modal-hd">
            <h6><i class="fa-solid fa-plus-circle text-primary me-2"></i> Add Single Slot</h6>
            <button class="btn-close" style="font-size:.8rem;" onclick="closeModal('addModal')"></button>
        </div>
        <div class="modal-bd">
            <input type="hidden" id="addDate">
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label-sm">Start Time</label>
                    <input type="time" id="addStart" class="form-ctrl">
                </div>
                <div class="col-6">
                    <label class="form-label-sm">End Time</label>
                    <input type="time" id="addEnd" class="form-ctrl">
                </div>
            </div>
        </div>
        <div class="modal-ft">
            <button class="mbtn ghost" onclick="closeModal('addModal')">Cancel</button>
            <button class="mbtn primary" onclick="submitAddSlot()">Add Slot</button>
        </div>
    </div>
</div>

<!-- Modal: Auto-Generate -->
<div class="av-modal-bg" id="genModal">
    <div class="av-modal">
        <div class="modal-hd">
            <h6><i class="fa-solid fa-bolt text-warning me-2"></i> Auto-Generate Slots</h6>
            <button class="btn-close" style="font-size:.8rem;" onclick="closeModal('genModal')"></button>
        </div>
        <div class="modal-bd">
            <div class="mb-3">
                <label class="form-label-sm">Date</label>
                <input type="date" id="genDate" class="form-ctrl">
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label-sm">From Time</label>
                    <input type="time" id="genStart" class="form-ctrl" value="09:00">
                </div>
                <div class="col-6">
                    <label class="form-label-sm">To Time</label>
                    <input type="time" id="genEnd" class="form-ctrl" value="17:00">
                </div>
            </div>
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label-sm">Duration (mins)</label>
                    <input type="number" id="genDur" class="form-ctrl" value="60" min="15">
                </div>
                <div class="col-6">
                    <label class="form-label-sm">Gap (mins)</label>
                    <input type="number" id="genGap" class="form-ctrl" value="0" min="0">
                </div>
            </div>
            <div class="mt-3 text-muted" style="font-size:.72rem;">
                <i class="fa-solid fa-circle-info"></i> This will automatically create multiple slots. Overlapping slots will be skipped.
            </div>
        </div>
        <div class="modal-ft">
            <button class="mbtn ghost" onclick="closeModal('genModal')">Cancel</button>
            <button class="mbtn primary" onclick="submitGen()">Generate</button>
        </div>
    </div>
</div>

<script>
// Expose PHP data to JS
const SLOT_DATA_RAW = <?= json_encode($slotsRaw) ?>;
const WEEK_DATES = <?= json_encode($weekDates) ?>;
const TODAY = '<?= $todayStr ?>';
const WEEK = <?= $weekOffset ?>;

const SLOT_DATA = {};
SLOT_DATA_RAW.forEach(s => {
    if (!SLOT_DATA[s.date]) SLOT_DATA[s.date] = [];
    SLOT_DATA[s.date].push({
        id: s.id,
        status: s.status,
        start: s.start_time,
        end: s.end_time,
        client: null // If we joined bookings, we'd add it here
    });
});

let currentView = 'week';
let selectedDay = TODAY;
let bulkMode = false;
let bulkIds = new Set();
let calCursor = new Date(selectedDay);

// --- Helpers
function reload() { window.location.reload(); }
function toast(msg, type='success') {
    const t = document.createElement('div');
    t.className = `av-toast ${type}`;
    t.innerHTML = `<i class="fa-solid fa-${type==='success'?'check':'circle-exclamation'} ti"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(), 3000);
}
function pad(n) { return String(n).padStart(2,'0'); }
function dateToString(d) { return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`; }
function formatTime(t) { return new Date(`2000-01-01T${t}`).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'}); }

// --- AJAX
const POST_URL = `availability.php?week=${WEEK}`;
function ajax(data, onSuccess) {
    fetch(POST_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    })
    .then(r => r.json())
    .then(r => {
        if(r.success){ toast('Success','success'); if(onSuccess)onSuccess(); else reload(); }
        else toast(r.error || 'Error','error');
    });
}

// --- Interaction
function handleClick(el) {
    if(bulkMode) {
        if(el.dataset.state === 'booked') return;
        el.classList.toggle('bulk-selected');
        const id = parseInt(el.dataset.id);
        bulkIds.has(id) ? bulkIds.delete(id) : bulkIds.add(id);
        document.getElementById('bulkCount').textContent = bulkIds.size;
    } else {
        if(el.dataset.state === 'booked') { toast('Slot is booked. Edit not allowed.','error'); return; }
        document.getElementById('editSlotId').value = el.dataset.id;
        document.getElementById('editSlotStatus').value = el.dataset.state;
        openModal('editModal');
    }
}

function quickToggle(id, cb) {
    const state = cb.checked ? 'available' : 'blocked';
    ajax({action:'quick_toggle', id:id, state:state});
}

// --- Modals
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Edit Slot
function saveSlotEdit() {
    const id = document.getElementById('editSlotId').value;
    const state = document.getElementById('editSlotStatus').value;
    ajax({action:'quick_toggle', id:id, state:state});
}
function deleteCurrentSlot() {
    if(!confirm("Delete this slot?")) return;
    const id = document.getElementById('editSlotId').value;
    ajax({action:'delete_slot', id:id});
}

// Add Slot
function openAddSlot(date) {
    document.getElementById('addDate').value = date;
    document.getElementById('addStart').value = '';
    document.getElementById('addEnd').value = '';
    openModal('addModal');
}
function submitAddSlot() {
    const d = document.getElementById('addDate').value;
    const s = document.getElementById('addStart').value;
    const e = document.getElementById('addEnd').value;
    ajax({action:'add_slot', date:d, start_time:s, end_time:e});
}

// Auto Gen
function openAutoGen() {
    document.getElementById('genDate').value = selectedDay;
    openModal('genModal');
}
function submitGen() {
    const d = document.getElementById('genDate').value;
    const s = document.getElementById('genStart').value;
    const e = document.getElementById('genEnd').value;
    const dur = document.getElementById('genDur').value;
    const gap = document.getElementById('genGap').value;
    ajax({action:'auto_gen', date:d, start_time:s, end_time:e, duration:dur, gap:gap}, reload);
}

// Bulk
function toggleBulk() {
    bulkMode = !bulkMode;
    bulkIds.clear();
    document.getElementById('bulkToggle').classList.toggle('active', bulkMode);
    document.getElementById('bulkBar').style.display = bulkMode ? 'flex' : 'none';
    document.querySelectorAll('.slot-card').forEach(c => c.classList.remove('bulk-selected'));
    document.getElementById('bulkCount').textContent = '0';
    document.getElementById('bulkAll').checked = false;
}
function toggleAllBulk(cb) {
    const ckd = cb.checked;
    bulkIds.clear();
    document.querySelectorAll('.slot-card:not(.booked)').forEach(c => {
        if(ckd) {
            c.classList.add('bulk-selected');
            bulkIds.add(parseInt(c.dataset.id));
        } else {
            c.classList.remove('bulk-selected');
        }
    });
    document.getElementById('bulkCount').textContent = bulkIds.size;
}
function bulkAction(type) {
    if(bulkIds.size === 0) return toast('No slots selected', 'error');
    if(type === 'delete' && !confirm('Delete selected slots?')) return;
    ajax({action:'bulk_action', ids:JSON.stringify([...bulkIds]), type:type}, reload);
}

// --- Views
function renderTimelineMarkup(date) {
    const slots = SLOT_DATA[date] || [];
    if (!slots.length) return `<div class="dv-empty"><i class="fa-regular fa-calendar-xmark"></i>No slots</div>`;
    return slots.map(slot => `
        <div class="dv-slot ${slot.status}" onclick="document.querySelector('.slot-card[data-id=\\'${slot.id}\\']').click()">
            <div class="dv-time-block">
                <div class="dv-time">${formatTime(slot.start)}</div>
                <div class="dv-dur">to ${formatTime(slot.end)}</div>
            </div>
            <div class="dv-divider"></div>
            <div class="dv-info">
                <span class="dv-status-badge ${slot.status}">${slot.status}</span>
            </div>
        </div>
    `).join('');
}

function selectDay(date) {
    selectedDay = date;
    document.querySelectorAll('.dp-btn').forEach(b => b.classList.toggle('active-day', b.dataset.date===date));
    if(currentView==='day') {
        document.getElementById('dvDateLabel').textContent = new Date(date).toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'});
        document.getElementById('dvTimeline').innerHTML = renderTimelineMarkup(date);
        const add = document.getElementById('dvAddBtn');
        if(date >= TODAY) { add.style.display='inline-flex'; add.onclick=()=>openAddSlot(date); } else add.style.display='none';
    }
}

function switchView(view) {
    currentView = view;
    if(bulkMode && view!=='week') toggleBulk();
    document.getElementById('vt-week').classList.toggle('active', view==='week');
    document.getElementById('vt-day').classList.toggle('active', view==='day');
    document.getElementById('vt-cal').classList.toggle('active', view==='cal');

    document.getElementById('avBoard').style.display = view==='week'?'grid':'none';
    document.getElementById('dayView').style.display = view==='day'?'block':'none';
    document.getElementById('calView').style.display = view==='cal'?'block':'none';
    document.getElementById('dayPickerBar').style.display = view==='day'?'block':'none';
    document.getElementById('weekNavBar').style.display = view==='cal'?'none':'flex';
    document.getElementById('bulkToggle').style.display = view==='week'?'inline-flex':'none';

    if(view==='day') selectDay(selectedDay);
    if(view==='cal') renderCalendar();
}

function renderCalendar() {
    const grid = document.getElementById('calGrid');
    document.getElementById('calMonthLabel').textContent = calCursor.toLocaleDateString('en-US',{month:'long',year:'numeric'});
    const monthStart = new Date(calCursor.getFullYear(), calCursor.getMonth(), 1);
    const gridStart = new Date(monthStart);
    gridStart.setDate(monthStart.getDate() - monthStart.getDay());

    let html = '';
    for(let i=0; i<42; i++) {
        const d = new Date(gridStart); d.setDate(gridStart.getDate()+i);
        const dStr = dateToString(d);
        const slots = SLOT_DATA[dStr] || [];
        const isCur = d.getMonth() === calCursor.getMonth();
        const classes = ['cal-cell'];
        if(dStr===TODAY) classes.push('cal-today');
        if(!isCur) classes.push('cal-other-month');
        if(dStr<TODAY && isCur) classes.push('cal-past');
        if(dStr===selectedDay) classes.push('cal-selected');
        const dots = slots.slice(0,4).map(s=>`<span class="cal-dot ${s.status}"></span>`).join('');
        const click = WEEK_DATES.includes(dStr) ? `onclick="selectCalDay('${dStr}')"` : '';

        html += `<div class="${classes.join(' ')}" ${click}>
            <div class="cal-day-num">${d.getDate()}</div>
            ${slots.length?`<div class="cal-slot-count">${slots.length}</div>`:''}
            <div class="cal-dots">${dots}</div>
        </div>`;
    }
    grid.innerHTML = html;
    
    // Panel
    const panel = document.getElementById('calDayPanel');
    if(WEEK_DATES.includes(selectedDay)) {
        panel.style.display = 'block';
        document.getElementById('calPanelTitle').textContent = new Date(selectedDay).toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'});
        document.getElementById('calPanelTimeline').innerHTML = renderTimelineMarkup(selectedDay);
    } else {
        panel.style.display = 'none';
    }
}
function selectCalDay(d) { selectedDay = d; renderCalendar(); }
function calPrev() { calCursor = new Date(calCursor.getFullYear(), calCursor.getMonth()-1, 1); renderCalendar(); }
function calNext() { calCursor = new Date(calCursor.getFullYear(), calCursor.getMonth()+1, 1); renderCalendar(); }

// Close modals on outside click
document.querySelectorAll('.av-modal-bg').forEach(bg => {
    bg.addEventListener('click', e => { if(e.target===bg) bg.classList.remove('open'); });
});
</script>

<?php include 'includes/trainer_footer.php'; ?>
