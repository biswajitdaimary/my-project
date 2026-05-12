<?php
require_once '../config/config.php';
require_once '../config/payment.php';
require_once '../helpers/payment_helper.php';

// Webhook payload from Razorpay
$webhookSecret = RAZORPAY_KEY_SECRET; // Or a custom webhook secret
$webhookBody = file_get_contents('php://input');
$webhookSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (empty($webhookBody) || empty($webhookSignature)) {
    http_response_code(400);
    exit("Invalid Payload");
}

// Verify webhook signature (Manual HMAC SHA256)
$expectedSignature = hash_hmac('sha256', $webhookBody, $webhookSecret);

if (!hash_equals($expectedSignature, $webhookSignature)) {
    http_response_code(400);
    exit("Invalid Signature");
}

// Process Event
$event = json_decode($webhookBody, true);

if ($event && isset($event['event'])) {
    if ($event['event'] == 'payment.captured' || $event['event'] == 'payment.authorized') {
        // Find payment
        $payment_id = $event['payload']['payment']['entity']['id'];
        $order_id = $event['payload']['payment']['entity']['order_id'];
        
        try {
            $pdo->beginTransaction();

            $payStmt = $pdo->prepare("SELECT * FROM payments WHERE gateway_order_id = ? LIMIT 1 FOR UPDATE");
            $payStmt->execute([$order_id]);
            $payment = $payStmt->fetch();

            if ($payment) {
                $stmt = $pdo->prepare("
                    UPDATE payments
                    SET status = 'success', gateway_payment_id = ?
                    WHERE payment_id = ?
                ");
                $stmt->execute([$payment_id, $payment['payment_id']]);

                $payment['status'] = 'success';
                $payment['gateway_payment_id'] = $payment_id;
                fulfill_membership_from_payment($pdo, $payment, date('Y-m-d'));
            }

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Webhook DB Error: " . $e->getMessage());
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Webhook Fulfillment Error: " . $e->getMessage());
        }
    } elseif ($event['event'] == 'payment.failed') {
        $order_id = $event['payload']['payment']['entity']['order_id'];
        $pdo->prepare("UPDATE payments SET status = 'failed' WHERE gateway_order_id = ? AND status = 'pending'")->execute([$order_id]);
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
?>
