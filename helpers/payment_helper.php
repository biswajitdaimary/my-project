<?php

function load_razorpay_sdk_if_available(): bool
{
    static $loaded = false;
    static $available = false;

    if ($loaded) {
        return $available;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        $available = class_exists('Razorpay\Api\Api');
    }

    $loaded = true;
    return $available;
}

function fulfill_membership_from_payment(PDO $pdo, array $payment, string $today): void
{
    $planStmt = $pdo->prepare("SELECT plan_id, duration_days, trainer_sessions FROM membership_plans WHERE plan_id = ? AND is_active = 1");
    $planStmt->execute([$payment['plan_id']]);
    $plan = $planStmt->fetch();

    if (!$plan) {
        throw new RuntimeException('The membership plan for this payment is no longer available.');
    }

    $membershipStmt = $pdo->prepare("SELECT membership_id FROM user_memberships WHERE payment_id = ? LIMIT 1");
    $membershipStmt->execute([$payment['payment_id']]);
    if ($membershipStmt->fetchColumn()) {
        return;
    }

    $startDate = $today;
    $endDate = date('Y-m-d', strtotime($today . ' +' . (int) $plan['duration_days'] . ' days'));

    $insertStmt = $pdo->prepare("
        INSERT INTO user_memberships (user_id, plan_id, start_date, end_date, status, payment_id, sessions_remaining)
        VALUES (?, ?, ?, ?, 'active', ?, ?)
    ");
    $insertStmt->execute([
        $payment['user_id'],
        $plan['plan_id'],
        $startDate,
        $endDate,
        $payment['payment_id'],
        $plan['trainer_sessions']
    ]);
}
?>
