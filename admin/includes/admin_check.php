<?php
// Ensure this file is loaded securely
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
require_once __DIR__ . '/../../helpers/auth_check.php';

// Every admin page must pass this check
require_admin();
?>
