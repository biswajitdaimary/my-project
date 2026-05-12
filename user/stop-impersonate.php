<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header("Location: dashboard.php");
    exit;
}

// If not currently impersonating, redirect away
if (
    !isset($_SESSION['admin_return_id'], $_SESSION['admin_return_email'], $_SESSION['admin_return_name'], $_SESSION['admin_return_role']) ||
    $_SESSION['admin_return_role'] !== 'admin'
) {
    header("Location: dashboard.php");
    exit;
}

// Snapshot the admin session before rotating the session ID.
$adminReturnId = (int)$_SESSION['admin_return_id'];
$adminReturnEmail = $_SESSION['admin_return_email'];
$adminReturnName = $_SESSION['admin_return_name'];
$adminReturnRole = $_SESSION['admin_return_role'];

session_regenerate_id(true);

// Restore admin session
$_SESSION['user_id'] = $adminReturnId;
$_SESSION['email'] = $adminReturnEmail;
$_SESSION['full_name'] = $adminReturnName;
$_SESSION['role'] = $adminReturnRole;

// Clean up impersonation data
unset($_SESSION['admin_return_id']);
unset($_SESSION['admin_return_email']);
unset($_SESSION['admin_return_name']);
unset($_SESSION['admin_return_role']);

// Redirect back to admin members list
header("Location: ../admin/members.php");
exit;
