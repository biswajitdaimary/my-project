<?php
/**
 * GET /api/get-slots.php?trainer_id=X&date=YYYY-MM-DD
 * Returns available time slots for a trainer on a given date,
 * excluding any slot already booked (pending or confirmed).
 */
require_once '../config/config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['slots' => [], 'error' => 'Unauthorized.']);
    exit;
}

$trainer_id = isset($_GET['trainer_id']) ? (int)$_GET['trainer_id'] : 0;
$date       = trim($_GET['date'] ?? '');

// Basic validation
if ($trainer_id <= 0 || !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['slots' => [], 'error' => 'Missing or invalid parameters.']);
    exit;
}

// Reject past dates
if ($date < date('Y-m-d')) {
    echo json_encode(['slots' => [], 'error' => 'Cannot book for past dates.']);
    exit;
}

    // Check if date is a holiday
    $hCheck = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date = ?");
    $hCheck->execute([$date]);
    $holidays = $hCheck->fetchAll();

    foreach ($holidays as $h) {
        if ($h['target_type'] === 'all') {
            echo json_encode(['slots' => [], 'error' => 'This date is a holiday (' . htmlspecialchars($h['title']) . ').']);
            exit;
        } else {
            $tids = json_decode($h['trainer_ids'], true) ?: [];
            if (in_array($trainer_id, $tids)) {
                echo json_encode(['slots' => [], 'error' => 'This trainer is on holiday (' . htmlspecialchars($h['title']) . ').']);
                exit;
            }
        }
    }

// Convert date to day abbreviation used by DB: Mon, Tue, Wed, Thu, Fri, Sat, Sun
$dayOfWeek = date('D', strtotime($date)); // e.g. "Thu"

try {
    /*
     * Select availability_slots for the exact date.
     * Blocked slots are filtered out.
     */
    $stmt = $pdo->prepare("
        SELECT
            id,
            start_time,
            end_time,
            status
        FROM availability_slots
        WHERE trainer_id  = :trainer_id
          AND date = :date
          AND status != 'blocked'
        ORDER BY start_time ASC
    ");
    $stmt->execute([
        ':trainer_id' => $trainer_id,
        ':date'       => $date,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalise to a clean shape the frontend expects
    $slots = array_map(fn($r) => [
        'availability_id' => (int)$r['id'], // keeping key as availability_id for frontend compatibility
        'start'           => substr($r['start_time'], 0, 5), // HH:MM
        'end'             => substr($r['end_time'],   0, 5),
        'is_booked'       => $r['status'] === 'booked',
    ], $rows);

    echo json_encode(['slots' => $slots]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['slots' => [], 'error' => 'Database error.']);
}
