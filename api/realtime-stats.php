<?php
/**
 * GET /api/realtime-stats.php
 * Returns live booking and slot statistics for real-time sync.
 *
 * Modes:
 *   ?mode=admin              → overall booking counts for admin panel
 *   ?mode=slots&trainer_id=X&date=YYYY-MM-DD → available slots for user booking page
 *   ?mode=trainer&trainer_id=X&week_start=YYYY-MM-DD → slot stats for trainer panel
 */
require_once '../config/config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$mode = $_GET['mode'] ?? 'admin';
$sessionRole = $_SESSION['role'] ?? '';
$sessionUserId = (int)($_SESSION['user_id'] ?? 0);

function deny_api(string $message = 'Unauthorized.', int $status = 403): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

try {
    if ($mode === 'admin') {
        if (!$sessionUserId || $sessionRole !== 'admin') {
            deny_api();
        }

        // ── Admin: live booking counts by status ────────────────────────
        $stmt = $pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'pending')   AS pending,
                SUM(status = 'confirmed') AS confirmed,
                SUM(status = 'completed') AS completed,
                SUM(status = 'cancelled') AS cancelled
            FROM trainer_bookings
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Today's bookings
        $todayStmt = $pdo->prepare("SELECT COUNT(*) FROM trainer_bookings WHERE session_date = CURDATE() AND status != 'cancelled'");
        $todayStmt->execute();
        $stats['today'] = (int)$todayStmt->fetchColumn();

        // Pending that need action (for badge)
        $stats['pending']   = (int)$stats['pending'];
        $stats['confirmed'] = (int)$stats['confirmed'];
        $stats['completed'] = (int)$stats['completed'];
        $stats['cancelled'] = (int)$stats['cancelled'];
        $stats['total']     = (int)$stats['total'];

        // Latest 5 bookings for live feed
        $latestStmt = $pdo->query("
            SELECT tb.booking_id, tb.status, tb.session_date, tb.start_time,
                   u.full_name AS client_name,
                   t.full_name AS trainer_name
            FROM trainer_bookings tb
            JOIN users u ON u.user_id = tb.user_id
            JOIN trainers t ON t.trainer_id = tb.trainer_id
            ORDER BY tb.created_at DESC
            LIMIT 5
        ");
        $stats['latest'] = $latestStmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['timestamp'] = time();

        echo json_encode(['success' => true, 'data' => $stats]);

    } elseif ($mode === 'slots') {
        if (!$sessionUserId || $sessionRole !== 'user') {
            deny_api();
        }

        // ── User booking page: live available slots for trainer+date ────
        $trainer_id = (int)($_GET['trainer_id'] ?? 0);
        $date       = trim($_GET['date'] ?? '');

        if (!$trainer_id || !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'error' => 'Missing parameters.']);
            exit;
        }
        if ($date < date('Y-m-d')) {
            echo json_encode(['success' => true, 'slots' => [], 'message' => 'Past date.']);
            exit;
        }

        $hCheck = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date = ?");
        $hCheck->execute([$date]);
        $holidays = $hCheck->fetchAll(PDO::FETCH_ASSOC);

        foreach ($holidays as $h) {
            if (($h['target_type'] ?? '') === 'all') {
                echo json_encode([
                    'success' => true,
                    'slots' => [],
                    'error' => 'This date is a holiday (' . htmlspecialchars($h['title']) . ').',
                    'timestamp' => time(),
                ]);
                exit;
            }

            $tids = json_decode($h['trainer_ids'] ?? '[]', true) ?: [];
            if (in_array($trainer_id, $tids, true)) {
                echo json_encode([
                    'success' => true,
                    'slots' => [],
                    'error' => 'This trainer is on holiday (' . htmlspecialchars($h['title']) . ').',
                    'timestamp' => time(),
                ]);
                exit;
            }
        }

        $stmt = $pdo->prepare("
            SELECT id, start_time, end_time, status
            FROM availability_slots
            WHERE trainer_id = ? AND date = ? AND status != 'blocked'
            ORDER BY start_time ASC
        ");
        $stmt->execute([$trainer_id, $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $slots = array_map(fn($r) => [
            'availability_id' => (int)$r['id'],
            'start'           => substr($r['start_time'], 0, 5),
            'end'             => substr($r['end_time'],   0, 5),
            'is_booked'       => $r['status'] === 'booked',
        ], $rows);

        echo json_encode(['success' => true, 'slots' => $slots, 'timestamp' => time()]);

    } elseif ($mode === 'trainer') {
        if (!$sessionUserId || $sessionRole !== 'trainer') {
            deny_api();
        }

        // ── Trainer panel: slot counts for current week ─────────────────
        $trainer_id = (int)($_GET['trainer_id'] ?? 0);
        $week_start = trim($_GET['week_start'] ?? '');

        if (!$trainer_id || !$week_start || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            echo json_encode(['success' => false, 'error' => 'Missing parameters.']);
            exit;
        }
        if ($trainer_id !== $sessionUserId) {
            deny_api();
        }

        $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));

        $stmt = $pdo->prepare("
            SELECT
                SUM(status = 'available') AS available,
                SUM(status = 'booked')    AS booked,
                SUM(status = 'blocked')   AS blocked
            FROM availability_slots
            WHERE trainer_id = ? AND date BETWEEN ? AND ?
        ");
        $stmt->execute([$trainer_id, $week_start, $week_end]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'   => true,
            'available' => (int)$counts['available'],
            'booked'    => (int)$counts['booked'],
            'blocked'   => (int)$counts['blocked'],
            'timestamp' => time()
        ]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown mode.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
