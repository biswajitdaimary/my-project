<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header("Location: members.php");
    exit;
}

$target_user_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($target_user_id <= 0) {
    header("Location: members.php");
    exit;
}

// Ensure the target is a user, not another admin
$stmt = $pdo->prepare("SELECT user_id, email, full_name, role FROM users WHERE user_id = ? AND role = 'user'");
$stmt->execute([$target_user_id]);
$user = $stmt->fetch();

if (!$user) {
    // User not found or is an admin
    header("Location: members.php");
    exit;
}

// Store current admin details in a backup session variable
$_SESSION['admin_return_id'] = $_SESSION['user_id'];
$_SESSION['admin_return_email'] = $_SESSION['email'];
$_SESSION['admin_return_name'] = $_SESSION['full_name'];
$_SESSION['admin_return_role'] = $_SESSION['role'];

session_regenerate_id(true);

// Set session to target user
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['email'] = $user['email'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role'] = $user['role'];

// Redirect to user dashboard
header("Location: ../user/dashboard.php");
exit;
