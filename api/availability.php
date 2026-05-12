<?php
/**
 * API for managing Trainer Availability Slots
 */
require_once '../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'trainer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$trainer_id = $_SESSION['user_id'];

if ($action === 'get_slots') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM availability_slots WHERE trainer_id = ? AND date = ? ORDER BY start_time");
    $stmt->execute([$trainer_id, $date]);
    echo json_encode(['success' => true, 'slots' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// Ensure CSRF for mutations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

if ($action === 'add_slot') {
    $date = trim($_POST['date'] ?? '');
    $start = trim($_POST['start_time'] ?? '');
    $end = trim($_POST['end_time'] ?? '');
    
    if (!$date || !$start || !$end) {
        echo json_encode(['success' => false, 'error' => 'All fields required']);
        exit;
    }
    
    if ($start >= $end) {
        echo json_encode(['success' => false, 'error' => 'End time must be after start time']);
        exit;
    }

    // Check overlaps
    $chk = $pdo->prepare("SELECT id FROM availability_slots WHERE trainer_id=? AND date=? AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))");
    $chk->execute([$trainer_id, $date, $end, $start, $start, $end]);
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Time slot overlaps with an existing slot']);
        exit;
    }

    try {
        $pdo->prepare("INSERT INTO availability_slots (trainer_id, date, start_time, end_time, status) VALUES (?, ?, ?, ?, 'available')")
            ->execute([$trainer_id, $date, $start, $end]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

if ($action === 'delete_slot') {
    $id = (int)($_POST['id'] ?? 0);
    
    // Ensure slot isn't booked
    $stmt = $pdo->prepare("SELECT status FROM availability_slots WHERE id = ? AND trainer_id = ?");
    $stmt->execute([$id, $trainer_id]);
    $slot = $stmt->fetch();
    
    if (!$slot) {
        echo json_encode(['success' => false, 'error' => 'Slot not found']);
        exit;
    }
    
    if ($slot['status'] === 'booked') {
        echo json_encode(['success' => false, 'error' => 'Cannot delete a booked slot']);
        exit;
    }
    
    $pdo->prepare("DELETE FROM availability_slots WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
