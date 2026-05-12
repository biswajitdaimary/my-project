<?php
require_once '../config/config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: login.php");
    exit;
}

try {
    // Look up the token in users table
    $stmt = $pdo->prepare("SELECT user_id, is_verified FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['is_verified'] == 1) {
            // Already verified
            header("Location: login.php?verified=already");
            exit;
        } else {
            // Update the user
            $updateStmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            // Redirect with success
            header("Location: login.php?registered=success");
            exit;
        }
    } else {
        // Invalid token
        die("Invalid or expired verification link.");
    }
} catch(PDOException $e) {
    die("Database error occurred.");
}
?>
