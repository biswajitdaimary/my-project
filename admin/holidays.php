<?php
/**
 * Legacy Admin Holidays Page
 * Redirected to the new role-specific Calendar system.
 */
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

header("Location: " . SITE_URL . "/admin/calendar.php");
exit;
