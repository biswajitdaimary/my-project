<?php
/**
 * POST /api/book_session.php
 * Books a trainer session for the currently logged-in user.
 * Expects JSON body: { trainer_id, date, start_time, end_time, notes }
 * 
 * This is the canonical endpoint called by user/book-trainer.php.
 * It integrates with trainer_bookings + notifications for both user & trainer.
 */
require_once '../config/config.php';
require_once '../helpers/notification_helper.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

// ── Auth Guard ─────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to book a session.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Parse Input ────────────────────────────────────────────────────────────
$raw   = file_get_contents('php://input');
$body  = json_decode($raw, true);

$user_id    = (int)$_SESSION['user_id'];
$trainer_id = (int)($body['trainer_id'] ?? $_POST['trainer_id'] ?? 0);
$date       = trim($body['date']       ?? $_POST['date']       ?? '');
$start_time = trim($body['start_time'] ?? $_POST['start_time'] ?? '');
$end_time   = trim($body['end_time']   ?? $_POST['end_time']   ?? '');
$csrfToken  = (string)($body['csrf_token'] ?? $_POST['csrf_token'] ?? '');
$notes      = htmlspecialchars(trim($body['notes'] ?? $_POST['notes'] ?? ''), ENT_QUOTES, 'UTF-8');

// ── Validate ────────────────────────────────────────────────────────────────
if (!$trainer_id || !$date || !$start_time) {
    echo json_encode(['success' => false, 'message' => 'Missing required booking details.']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Invalid or past date selected.']);
    exit;
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
    exit;
}
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid start time selected.']);
    exit;
}

// ── Database Transaction ────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // 1. Verify the trainer exists and is active
    $tStmt = $pdo->prepare("SELECT trainer_id, full_name FROM trainers WHERE trainer_id = ? AND is_active = 1");
    $tStmt->execute([$trainer_id]);
    $trainer = $tStmt->fetch();
    if (!$trainer) {
        throw new Exception('Trainer not found or is inactive.');
    }

    // 2. Block bookings on global or trainer-specific holidays.
    $holidayStmt = $pdo->prepare("
        SELECT title, target_type, trainer_ids
        FROM holidays
        WHERE holiday_date = ?
        FOR UPDATE
    ");
    $holidayStmt->execute([$date]);
    foreach ($holidayStmt->fetchAll() as $holiday) {
        if (($holiday['target_type'] ?? '') === 'all') {
            throw new Exception('Cannot book: This date is a holiday (' . $holiday['title'] . ').');
        }

        $trainerIds = json_decode($holiday['trainer_ids'] ?? '[]', true);
        if (is_array($trainerIds) && in_array($trainer_id, $trainerIds)) {
            throw new Exception('Cannot book: The trainer is on holiday (' . $holiday['title'] . ').');
        }
    }

    // 3. Find the availability slot
    $slotCheck = $pdo->prepare("
        SELECT id, status, start_time, end_time 
        FROM availability_slots 
        WHERE trainer_id = ? 
          AND date = ? 
          AND (start_time = ? OR start_time = CONCAT(?, ':00'))
        FOR UPDATE
    ");
    $slotCheck->execute([$trainer_id, $date, $start_time, $start_time]);
    $slot = $slotCheck->fetch();

    if (!$slot || $slot['status'] !== 'available') {
        throw new Exception('This time slot is no longer available. Please pick a different slot.');
    }

    // Compare times loosely (HH:MM) to avoid HH:MM vs HH:MM:00 mismatch
    $dbStart = substr($slot['start_time'], 0, 5);
    $dbEnd   = substr($slot['end_time'], 0, 5);
    $reqStart= substr($start_time, 0, 5);
    $reqEnd  = substr($end_time, 0, 5);

    if ($reqEnd !== '' && $reqEnd !== $dbEnd) {
        throw new Exception('The selected slot timing has changed. Please refresh and try again.');
    }
    
    $slot_id = $slot['id'];

    // 4. Also check this user hasn't already booked a session at the same time
    $userDup = $pdo->prepare("
        SELECT booking_id FROM trainer_bookings
        WHERE user_id = ? AND session_date = ? AND start_time = ?
          AND status IN ('pending', 'confirmed')
    ");
    $userDup->execute([$user_id, $date, $start_time]);
    if ($userDup->fetch()) {
        throw new Exception('You already have a booking at this time.');
    }

    // 5. Insert booking (status = 'pending' — trainer must confirm)
    $insStmt = $pdo->prepare("
        INSERT INTO trainer_bookings (user_id, trainer_id, session_date, start_time, end_time, status, notes, slot_id)
        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
    ");
    $insStmt->execute([$user_id, $trainer_id, $date, $slot['start_time'], $slot['end_time'], $notes, $slot_id]);
    $bookingId = $pdo->lastInsertId();

    // 6. Mark slot as booked
    $pdo->prepare("UPDATE availability_slots SET status = 'booked' WHERE id = ?")->execute([$slot_id]);

    // 7. Notify the USER
    $dateFormatted = date('D, M j Y', strtotime($date));
    $timeFormatted = date('h:i A', strtotime($slot['start_time'])) . ' – ' . date('h:i A', strtotime($slot['end_time']));
    create_notification(
        $pdo, $user_id,
        '📅 Session Requested',
        "Your session with {$trainer['full_name']} on $dateFormatted at $timeFormatted is pending trainer confirmation.",
        'info'
    );

    // 8. Notify the TRAINER via trainer_notifications table
    $uStmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $uStmt->execute([$user_id]);
    $clientName = $uStmt->fetchColumn() ?: 'A client';

    $pdo->prepare("
        INSERT INTO trainer_notifications (trainer_id, title, message, type)
        VALUES (?, ?, ?, 'info')
    ")->execute([
        $trainer_id,
        '🔔 New Booking Request',
        "New session request from $clientName on $dateFormatted at $timeFormatted. Please accept or decline in your panel.",
    ]);

    $pdo->commit();

    echo json_encode([
        'success'    => true,
        'booking_id' => (int)$bookingId,
        'message'    => 'Booking request sent! Awaiting trainer confirmation.',
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
