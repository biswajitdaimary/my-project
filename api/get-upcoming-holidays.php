<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['holidays' => []]);
    exit;
}

$role = $_SESSION['role'] ?? 'user';
$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date >= CURDATE() AND holiday_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) ORDER BY holiday_date ASC");
    $stmt->execute();
    $allHolidays = $stmt->fetchAll();

    $validHolidays = [];
    foreach ($allHolidays as $h) {
        if ($h['target_type'] === 'all' || $role === 'admin') {
            $validHolidays[] = $h;
        } else {
            // If it's specific trainers
            if ($role === 'trainer') {
                $tids = json_decode($h['trainer_ids'], true) ?: [];
                if (in_array($userId, $tids)) {
                    $validHolidays[] = $h;
                }
            }
        }
    }
    
    $today = date('Y-m-d');
    // Format dates
    foreach($validHolidays as &$h) {
        $h['formatted_date'] = date('M d, Y', strtotime($h['holiday_date']));
        $h['is_today'] = ($h['holiday_date'] === $today);
    }

    echo json_encode(['holidays' => $validHolidays]);
} catch(Exception $e) {
    echo json_encode(['holidays' => []]);
}
