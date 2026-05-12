<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();
require_once 'includes/admin_header.php';

try {
    $stmt = $pdo->query("
        SELECT p.*, u.full_name as client_name, u.email as client_email, mp.plan_name 
        FROM payments p 
        JOIN users u ON p.user_id = u.user_id 
        LEFT JOIN membership_plans mp ON p.plan_id = mp.plan_id 
        ORDER BY p.payment_date DESC
    ");
    $payments = $stmt->fetchAll();
    
    // Quick Stats
    $statStmt = $pdo->query("SELECT SUM(amount) as total, COUNT(*) as count FROM payments WHERE status = 'success'");
    $stats = $statStmt->fetch();
} catch(PDOException $e) {
    die("Database error.");
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold m-0">Payment Records</h3>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-success text-white border-0 shadow-sm rounded-10">
            <div class="card-body p-4 text-center">
                <h6 class="text-uppercase fw-bold opacity-75">Total Revenue</h6>
                <h2 class="fw-bold mb-0">₹<?= number_format($stats['total'] ?? 0, 2) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-primary-custom text-white border-0 shadow-sm rounded-10">
            <div class="card-body p-4 text-center">
                <h6 class="text-uppercase fw-bold opacity-75">Successful Transactions</h6>
                <h2 class="fw-bold mb-0"><?= number_format($stats['count'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-10">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-custom">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Client</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-end">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($payments)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No payments recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach($payments as $p): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark">#<?= $p['payment_id'] ?></span><br>
                                    <small class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($p['gateway_order_id'] ?? '') ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($p['client_name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($p['client_email']) ?></div>
                                </td>
                                <td class="small"><?= htmlspecialchars($p['plan_name'] ?? 'Membership') ?></td>
                                <td class="fw-bold text-success">₹<?= number_format($p['amount'], 2) ?></td>
                                <td>
                                    <div class="fw-bold"><?= date('M d, Y', strtotime($p['payment_date'])) ?></div>
                                    <div class="small text-muted"><?= date('h:i A', strtotime($p['payment_date'])) ?></div>
                                </td>
                                <td>
                                    <?php
                                        $bdg = 'bg-secondary';
                                        if($p['status'] == 'success') $bdg = 'bg-success';
                                        if($p['status'] == 'pending') $bdg = 'bg-warning text-dark';
                                        if($p['status'] == 'failed') $bdg = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $bdg ?> mw-100 px-2 py-1"><?= ucfirst($p['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if($p['status'] == 'success'): ?>
                                        <a href="../payment/receipt.php?payment_id=<?= $p['payment_id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-file-pdf"></i></a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
