<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/site_settings_helper.php';

require_login();
$payment_id = $_GET['payment_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$payment_id) {
    die("Invalid request");
}

try {
    // Check if user owns payment or is admin
    $q = "SELECT p.*, mp.plan_name, mp.duration_days, u.full_name, u.email 
          FROM payments p 
          LEFT JOIN membership_plans mp ON p.plan_id = mp.plan_id
          JOIN users u ON p.user_id = u.user_id
          WHERE p.payment_id = ?";
          
    if ($_SESSION['role'] !== 'admin') {
        $q .= " AND p.user_id = " . (int)$user_id;
    }
    
    $stmt = $pdo->prepare($q);
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        die("Payment not found or you do not have permission.");
    }
} catch (PDOException $e) {
    die("Database Error");
}

// Below is HTML logic for visual receipt. 
// If TCPDF is installed, we would use it here. We will wrap the HTML in a structure that can be printed.
$receiptGymName = site_settings_get('gym_name', SITE_NAME);
$receiptEmail = site_settings_get('email', 'info@powerhousegym.com');
$receiptAddress = site_settings_get('address', "123 Fitness Street\nGym City");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT) ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; }
        .receipt-container { width: 800px; margin: 40px auto; padding: 40px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #FF6B35; padding-bottom: 20px; mb-4 }
        .header h1 { margin: 0; color: #1A1A2E; }
        .details { display: flex; justify-content: space-between; margin-top: 30px; margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #F8F9FA; }
        .total { font-size: 24px; font-weight: bold; text-align: right; margin-top: 20px; }
        .print-btn { display: block; width: 100%; max-width: 200px; margin: 20px auto; padding: 10px; background: #FF6B35; color: white; text-align: center; border: none; cursor: pointer; border-radius: 5px; }
        @media print {
            .print-btn { display: none; }
            .receipt-container { box-shadow: none; border: none; width: 100%; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <div>
                <h1><?= htmlspecialchars($receiptGymName) ?></h1>
                <p><?= nl2br(htmlspecialchars($receiptAddress)) ?><br><?= htmlspecialchars($receiptEmail) ?></p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin:0; color: #FF6B35;">PAYMENT RECEIPT</h2>
                <p><strong>Receipt #:</strong> RCPT-<?= str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT) ?><br>
                   <strong>Date:</strong> <?= date('F d, Y', strtotime($payment['payment_date'])) ?></p>
            </div>
        </div>

        <div class="details">
            <div>
                <strong>Billed To:</strong><br>
                <?= htmlspecialchars($payment['full_name']) ?><br>
                <?= htmlspecialchars($payment['email']) ?>
            </div>
            <div style="text-align: right;">
                <strong>Payment Method:</strong><br>
                <?= strtoupper(htmlspecialchars($payment['gateway'])) ?><br>
                Ref: <?= htmlspecialchars($payment['gateway_payment_id'] ?? 'N/A') ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($payment['plan_name'] ?? 'Membership Plan') ?></strong><br>
                        <small><?= $payment['duration_days'] ?? 0 ?> Days Access</small>
                    </td>
                    <td style="text-align: right;">₹<?= number_format($payment['amount'], 2) ?></td>
                </tr>
                <!-- Taxes could go here -->
            </tbody>
        </table>

        <div class="total">
            Total Paid: ₹<?= number_format($payment['amount'], 2) ?>
        </div>

        <div style="margin-top: 50px; text-align: center; color: #777; font-size: 12px;">
            <p>Thank you for choosing <?= htmlspecialchars($receiptGymName) ?>! For any queries, contact our support.</p>
            <p>This is a computer-generated receipt.</p>
        </div>
    </div>
    <button class="print-btn" onclick="window.print()">Print / Save PDF</button>
</body>
</html>
