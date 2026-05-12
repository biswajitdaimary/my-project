<?php
// Core System Configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent browser caching globally
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'gym_db');
define('DB_USER', 'root');
define('DB_PASS', '');

function detect_site_url(): string {
    $scheme = 'http';
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    ) {
        $scheme = 'https';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // The directory of this config file is <project_root>/config
    // So the project root is dirname(__DIR__)
    $projectRootPath = str_replace('\\', '/', dirname(__DIR__));
    
    // The document root
    $docRootPath = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    
    // Calculate the base path (e.g., /powerhousegym)
    $basePath = '';
    if (strpos($projectRootPath, $docRootPath) === 0) {
        $basePath = substr($projectRootPath, strlen($docRootPath));
    } else {
        // Fallback if Document Root is not a prefix (e.g., alias or symlink)
        // We know we're in the 'config' folder, so we can strip the script name suffix
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
        
        if (strpos($scriptPath, $projectRootPath) === 0) {
            $relativeScriptPath = substr($scriptPath, strlen($projectRootPath));
            if (substr($scriptName, -strlen($relativeScriptPath)) === $relativeScriptPath) {
                $basePath = substr($scriptName, 0, -strlen($relativeScriptPath));
            }
        }
    }
    
    return rtrim($scheme . '://' . $host . $basePath, '/');
}

// Site Constants
define('SITE_NAME', 'FITNESS DESTINATION');
define('SITE_URL', detect_site_url());
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'database.php';

// SMTP Configuration
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
define('SMTP_PORT', 2525);
define('SMTP_USER', 'your_mailtrap_user'); // Replace with actual credentials
define('SMTP_PASS', 'your_mailtrap_pass');
define('SMTP_FROM_EMAIL', 'noreply@powerhousegym.com');
define('SMTP_FROM_NAME', 'PowerHouse Gym');
