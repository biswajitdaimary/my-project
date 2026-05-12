<?php
$pageTitle = 'Payments & Receipts';
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_user();
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT p.*, mp.plan_name FROM payments p LEFT JOIN membership_plans mp ON p.plan_id = mp.plan_id WHERE p.user_id = ? ORDER BY p.payment_date DESC");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) { $payments = []; }

$totalSpent   = array_sum(array_column(array_filter($payments, fn($p) => $p['status'] === 'success'), 'amount'));
$successCount = count(array_filter($payments, fn($p) => $p['status'] === 'success'));
$lastPayment  = count($payments) > 0 ? $payments[0] : null;

require_once '../includes/header.php';
require_once '../includes/nav.php';
?>
<style>
.kpi-strip{background:#fff;border-radius:1.25rem;padding:1.5rem;box-shadow:0 4px 20px rgba(0,0,0,.05);border:1px solid #f0f2f7;margin-bottom:1.5rem}
.kpi-item{text-align:center;flex:1}
.kpi-item .val{font-size:1.6rem;font-weight:900;color:#1a1a2e;line-height:1}
.kpi-item .lbl{font-size:.75rem;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-top:.25rem}
.kpi-divider{width:1px;background:#f0f2f7;align-self:stretch}
.pay-row{display:flex;align-items:center;gap:1rem;padding:1rem 0;border-bottom:1px solid #f4f6fb;flex-wrap:wrap}
.pay-row:last-child{border-bottom:none}
.pay-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.pay-amt{font-size:1.25rem;font-weight:900;color:#1a1a2e}
.pay-plan{font-size:.82rem;color:#6b7280}
.pay-gateway{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#9ca3af}
.pay-actions{margin-left:auto;display:flex;align-items:center;gap:.5rem}
.empty-state{text-align:center;padding:3rem 1rem}
.empty-icon{width:80px;height:80px;border-radius:50%;background:#f4f6fb;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:2rem;color:#d1d5db}
</style>

<div class="up-wrap"><div class="container-fluid px-0"><div class="d-flex">
<?php require_once '../includes/sidebar-user.php'; ?>
<main class="up-main flex-grow-1" style="min-width:0;">

<div class="mb-4">
    <h4 class="fw-bold mb-0" style="color:#1a1a2e;">Payments & Receipts</h4>
    <p class="text-muted mb-0" style="font-size:.87rem;">View your full transaction history</p>
</div>

<!-- Summary Strip -->
<div class="kpi-strip d-flex gap-3 align-items-center">
    <div class="kpi-item">
        <div class="val" style="color:#FF6B35;">₹<?= number_format($totalSpent, 0) ?></div>
        <div class="lbl">Total Spent</div>
    </div>
    <div class="kpi-divider"></div>
    <div class="kpi-item">
        <div class="val"><?= $successCount ?></div>
        <div class="lbl">Successful</div>
    </div>
    <div class="kpi-divider"></div>
    <div class="kpi-item">
        <div class="val" style="font-size:1rem;"><?= $lastPayment ? date('M d, Y', strtotime($lastPayment['payment_date'])) : '—' ?></div>
        <div class="lbl">Last Payment</div>
    </div>
</div>

<div class="up-card">
    <div class="up-card-header">
        <h6 class="up-card-title"><i class="fa-solid fa-receipt me-2" style="color:#f72585;"></i>Transaction History</h6>
    </div>
    <div class="up-card-body">
    <?php if (empty($payments)): ?>
        <div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-receipt"></i></div>
        <h5 class="fw-bold">No Transactions Yet</h5>
        <p class="text-muted mb-4" style="font-size:.9rem;">You haven't made any payments yet.</p>
        <a href="<?= SITE_URL ?>/plans.php" class="btn rounded-pill px-5 fw-bold" style="background:#FF6B35;color:#fff;">View Plans</a></div>
    <?php else: ?>
        <?php foreach ($payments as $pay):
            $isSuccess = $pay['status'] === 'success';
            $sc = ['success'=>'bg-success','pending'=>'bg-warning text-dark','failed'=>'bg-danger','refunded'=>'bg-dark'];
            $cls = $sc[$pay['status']] ?? 'bg-secondary';
            $iconBg = $isSuccess ? 'background:rgba(34,197,94,.1);color:#22c55e;' : 'background:rgba(239,68,68,.1);color:#ef4444;';
        ?>
        <div class="pay-row">
            <div class="pay-icon" style="<?= $iconBg ?>">
                <i class="fa-solid <?= $isSuccess ? 'fa-check' : 'fa-xmark' ?>"></i>
            </div>
            <div>
                <div class="pay-amt">₹<?= number_format($pay['amount'], 2) ?></div>
                <div class="pay-plan"><?= htmlspecialchars($pay['plan_name'] ?? 'Payment') ?></div>
            </div>
            <div>
                <div class="pay-gateway"><?= htmlspecialchars($pay['gateway']) ?></div>
                <?php if ($pay['gateway_payment_id']): ?>
                <div style="font-size:.7rem;color:#bbb;"><?= htmlspecialchars(substr($pay['gateway_payment_id'],0,24)) ?>…</div>
                <?php endif; ?>
            </div>
            <div class="text-muted" style="font-size:.8rem;">
                <div><?= date('M d, Y', strtotime($pay['payment_date'])) ?></div>
                <div><?= date('h:i A', strtotime($pay['payment_date'])) ?></div>
            </div>
            <div class="pay-actions">
                <span class="badge <?= $cls ?> rounded-pill px-3"><?= ucfirst($pay['status']) ?></span>
                <?php if ($isSuccess): ?>
                <a href="<?= SITE_URL ?>/payment/receipt.php?payment_id=<?= $pay['payment_id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size:.75rem;"><i class="fa-solid fa-download me-1"></i>PDF</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>
</main></div></div></div>
<?php require_once '../includes/footer.php'; ?>
