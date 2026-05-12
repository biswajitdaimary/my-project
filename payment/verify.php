<?php
require_once '../config/config.php';
require_once '../config/payment.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/payment_helper.php';
require_once '../helpers/notification_helper.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../plans.php");
    exit;
}

$razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
$razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
$razorpay_signature = $_POST['razorpay_signature'] ?? '';
$csrfToken = $_POST['csrf_token'] ?? '';
$user_id = $_SESSION['user_id'];

$success = false;

if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    die("Invalid security token.");
}

if (empty($razorpay_payment_id) || empty($razorpay_order_id) || empty($razorpay_signature)) {
    die("Payment Details Missing. Please contact support.");
}

try {
    $sdkAvailable = load_razorpay_sdk_if_available();

    if ($sdkAvailable) {
        $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        $attributes = [
            'razorpay_order_id' => $razorpay_order_id,
            'razorpay_payment_id' => $razorpay_payment_id,
            'razorpay_signature' => $razorpay_signature
        ];
        $api->utility->verifyPaymentSignature($attributes);
        $success = true;
    } else {
        if (strpos($razorpay_order_id, 'order_mock_') === 0) {
            $success = true;
        } else {
            $generated_signature = hash_hmac('sha256', $razorpay_order_id . "|" . $razorpay_payment_id, RAZORPAY_KEY_SECRET);
            if (hash_equals($generated_signature, $razorpay_signature)) {
                $success = true;
            } else {
                die("Signature Verification Failed.");
            }
        }
    }
} catch(SignatureVerificationError $e) {
    die("Razorpay Error: " . $e->getMessage());
}

if ($success) {
    try {
        $pdo->beginTransaction();

        $payStmt = $pdo->prepare("
            SELECT *
            FROM payments
            WHERE gateway_order_id = ? AND user_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $payStmt->execute([$razorpay_order_id, $user_id]);
        $payment = $payStmt->fetch();

        if (!$payment) {
            throw new RuntimeException('Payment record not found for this user.');
        }

        if ($payment['status'] === 'failed' || $payment['status'] === 'refunded') {
            throw new RuntimeException('This payment can no longer be completed.');
        }

        $updPay = $pdo->prepare("
            UPDATE payments
            SET status = 'success',
                gateway_payment_id = ?,
                gateway_signature = ?
            WHERE payment_id = ?
        ");
        $updPay->execute([$razorpay_payment_id, $razorpay_signature, $payment['payment_id']]);

        $payment['status'] = 'success';
        $payment['gateway_payment_id'] = $razorpay_payment_id;
        $payment['gateway_signature'] = $razorpay_signature;

        fulfill_membership_from_payment($pdo, $payment, date('Y-m-d'));
        create_notification(
            $pdo,
            $user_id,
            'Membership activated',
            'Your payment was successful and your membership is now active.',
            'success'
        );

        $pdo->commit();

        header("Location: ../user/membership.php?payment=success");
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Payment verification failed: " . $e->getMessage());
    }
} else {
    // Payment failed
    $pdo->prepare("UPDATE payments SET status = 'failed' WHERE gateway_order_id = ?")->execute([$razorpay_order_id]);
    header("Location: ../plans.php?error=failed");
    exit;
}
?>
