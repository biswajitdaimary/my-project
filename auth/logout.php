<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';

clear_persisted_remember_login();

// Unset all session variables
$_SESSION = [];

// If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session.
session_destroy();

// Redirect to home page
header("Location: " . SITE_URL . "/index.php");
exit;
?>
