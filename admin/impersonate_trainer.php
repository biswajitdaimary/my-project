<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header("Location: trainers.php");
    exit;
}

$target_trainer_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($target_trainer_id <= 0) {
    header("Location: trainers.php");
    exit;
}

// Ensure the target is a valid trainer
$stmt = $pdo->prepare("SELECT trainer_id, email, full_name, is_active FROM trainers WHERE trainer_id = ?");
$stmt->execute([$target_trainer_id]);
$trainer = $stmt->fetch();

if (!$trainer || !$trainer['is_active']) {
    // Trainer not found or inactive
    header("Location: trainers.php?error=Trainer+not+found+or+inactive");
    exit;
}

// Store current admin details in a backup session variable
$_SESSION['admin_return_id'] = $_SESSION['user_id'];
$_SESSION['admin_return_email'] = $_SESSION['email'];
$_SESSION['admin_return_name'] = $_SESSION['full_name'];
$_SESSION['admin_return_role'] = $_SESSION['role'];

session_regenerate_id(true);

// Set session to target trainer
// The trainer panel expects 'user_id' to be the trainer's ID
$_SESSION['user_id'] = $trainer['trainer_id'];
$_SESSION['email'] = $trainer['email'];
$_SESSION['full_name'] = $trainer['full_name'];
$_SESSION['role'] = 'trainer';

// Redirect to trainer dashboard or specific destination
$dest = $_POST['dest'] ?? 'dashboard';
if ($dest === 'availability') {
    header("Location: ../trainer/availability.php");
} else {
    header("Location: ../trainer/dashboard.php");
}
exit;
