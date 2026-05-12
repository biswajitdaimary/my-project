<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/notification_helper.php';
header('Content-Type: application/json');

// Only logged in users via POST
if (empty($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$availability_id = $_POST['availability_id'] ?? null;
$date = $_POST['date'] ?? null;
$notes = htmlspecialchars($_POST['notes'] ?? '');

if (!$availability_id || !$date) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Check if user has active membership with remaining sessions
    $mStmt = $pdo->prepare("SELECT membership_id, sessions_remaining FROM user_memberships WHERE user_id = ? AND status = 'active' AND end_date >= ? AND sessions_remaining > 0 ORDER BY end_date DESC LIMIT 1");
    $mStmt->execute([$user_id, $date]);
    $membership = $mStmt->fetch();

    if (!$membership) {
        throw new Exception("No active sessions remaining for this date.");
    }

    // 2. Fetch slot details
    $sStmt = $pdo->prepare("
        SELECT a.trainer_id, a.start_time, a.end_time, t.full_name AS trainer_name, a.status, a.date
        FROM availability_slots a
        JOIN trainers t ON t.trainer_id = a.trainer_id
        WHERE a.id = ?
    ");
    $sStmt->execute([$availability_id]);
    $slot = $sStmt->fetch();

    if (!$slot || $slot['status'] !== 'available') {
        throw new Exception("Invalid or unavailable time slot.");
    }

    // Ensure the passed date matches the slot date
    if ($slot['date'] !== $date) {
        throw new Exception("Date mismatch.");
    }

    // Holiday Check
    $hCheck = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date = ?");
    $hCheck->execute([$date]);
    $holidays = $hCheck->fetchAll();
    foreach ($holidays as $h) {
        if ($h['target_type'] === 'all') {
            throw new Exception('Cannot book: This date is a holiday (' . $h['title'] . ').');
        } else {
            $tids = json_decode($h['trainer_ids'], true) ?: [];
            if (in_array($slot['trainer_id'], $tids)) {
                throw new Exception('Cannot book: The trainer is on holiday (' . $h['title'] . ').');
            }
        }
    }

    // 3. Double-check if booking already exists for this exact date & time (in case of race conditions)
    $bCheck = $pdo->prepare("SELECT 1 FROM trainer_bookings WHERE trainer_id = ? AND session_date = ? AND start_time = ? AND status IN ('pending', 'confirmed') FOR UPDATE");
    $bCheck->execute([$slot['trainer_id'], $date, $slot['start_time']]);
    if ($bCheck->fetch()) {
        throw new Exception("Slot was just booked by someone else.");
    }

    // 4. Insert Booking
    $insStmt = $pdo->prepare("INSERT INTO trainer_bookings (user_id, trainer_id, session_date, start_time, end_time, status, notes, slot_id) VALUES (?, ?, ?, ?, ?, 'confirmed', ?, ?)");
    $insStmt->execute([$user_id, $slot['trainer_id'], $date, $slot['start_time'], $slot['end_time'], $notes, $availability_id]);

    // 5. Update the availability_slots to mark it as booked
    $updSlot = $pdo->prepare("UPDATE availability_slots SET status = 'booked' WHERE id = ?");
    $updSlot->execute([$availability_id]);

    // 6. Deduct session from membership
    $deductStmt = $pdo->prepare("UPDATE user_memberships SET sessions_remaining = sessions_remaining - 1 WHERE membership_id = ?");
    $deductStmt->execute([$membership['membership_id']]);

    create_notification(
        $pdo,
        $user_id,
        'Trainer session booked',
        'Your session with ' . $slot['trainer_name'] . ' is confirmed for ' . date('M d, Y', strtotime($date)) . ' at ' . date('h:i A', strtotime($slot['start_time'])) . '.',
        'success'
    );

    // Notify Admin
    $userName = $_SESSION['full_name'] ?? 'A member';
    notify_admin(
        $pdo,
        "New Trainer Booking",
        "{$userName} has booked a session with {$slot['trainer_name']} for " . date('M d, Y', strtotime($date)) . " at " . date('h:i A', strtotime($slot['start_time'])) . ".",
        "info",
        "bookings.php"
    );

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Booking confirmed']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
