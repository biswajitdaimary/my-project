<?php
require_once '../config/config.php';
require_once '../config/payment.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/payment_helper.php';

use Razorpay\Api\Api;

header('Content-Type: application/json');

// Only logged in users via POST
if (empty($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$plan_id = $_POST['plan_id'] ?? null;
$user_id = $_SESSION['user_id'];
$csrfToken = $_POST['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

if (!$plan_id) {
    echo json_encode(['status' => 'error', 'message' => 'Plan not provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT price FROM membership_plans WHERE plan_id = ? AND is_active = 1");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();
    
    if (!$plan) {
        throw new Exception("Invalid Plan");
    }

    $amount = (int) ($plan['price'] * 100); // Razorpay requires amount in paise (cents)
    $receipt_id = 'rcpt_' . $user_id . '_' . time();
    $sdkAvailable = load_razorpay_sdk_if_available();

    // Check if SDK exists, otherwise simulate response (To allow system to run if composer wasn't executed)
    if ($sdkAvailable) {
        $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        $orderData = [
            'receipt'         => $receipt_id,
            'amount'          => $amount, 
            'currency'        => 'INR',
            'payment_capture' => 1
        ];
        $razorpayOrder = $api->order->create($orderData);
        $razorpayOrderId = $razorpayOrder['id'];
    } else {
        // Mock order creation for demonstration purposes if SDK is missing
        $razorpayOrderId = 'order_mock_' . bin2hex(random_bytes(6));
    }

    // Record pending transaction in DB
    $insStmt = $pdo->prepare("INSERT INTO payments (user_id, plan_id, amount, gateway, gateway_order_id, status) VALUES (?, ?, ?, 'razorpay', ?, 'pending')");
    $insStmt->execute([$user_id, $plan_id, $plan['price'], $razorpayOrderId]);

    echo json_encode([
        'status' => 'success',
        'order_id' => $razorpayOrderId,
        'amount' => $amount,
        'key' => RAZORPAY_KEY_ID,
        'mock_mode' => !$sdkAvailable
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
