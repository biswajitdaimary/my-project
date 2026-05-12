<?php

function create_notification(PDO $pdo, int $userId, string $title, ?string $message = null, string $type = 'info'): bool
{
    $allowedTypes = ['info', 'success', 'warning', 'danger'];
    $finalType = in_array($type, $allowedTypes, true) ? $type : 'info';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            trim($title),
            $message !== null && trim($message) !== '' ? trim($message) : null,
            $finalType
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}
function create_trainer_notification(PDO $pdo, int $trainerId, string $title, ?string $message = null, string $type = 'info'): bool
{
    $allowedTypes = ['info', 'success', 'warning', 'danger'];
    $finalType = in_array($type, $allowedTypes, true) ? $type : 'info';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO trainer_notifications (trainer_id, title, message, type)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([
            $trainerId,
            trim($title),
            $message !== null && trim($message) !== '' ? trim($message) : null,
            $finalType
        ]);
    } catch (Throwable $e) {
        return false;
    }
}


function notify_admin(PDO $pdo, string $title, string $message = '', string $type = 'info', ?string $linkUrl = null): bool
{
    $allowedTypes = ['info', 'success', 'warning', 'danger'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'info';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_notifications (title, message, type, link_url)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([
            trim($title),
            trim($message),
            $type,
            $linkUrl
        ]);
    } catch (Throwable $e) {
        error_log("Failed to send admin notification: " . $e->getMessage());
        return false;
    }
}
?>
