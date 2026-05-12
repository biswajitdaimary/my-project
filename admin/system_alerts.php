<?php
$pageTitle = 'System Alerts';
require_once 'includes/admin_header.php';

try {
    // Mark all unread alerts as read
    $pdo->exec("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");

    // Fetch the latest 50 alerts
    $stmt = $pdo->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 50");
    $alerts = $stmt->fetchAll();
} catch (PDOException $e) {
    $alerts = [];
}
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h3 class="fw-bold mb-1" style="color:#1a1a2e;">System Alerts</h3>
        <p class="text-muted mb-0" style="font-size:0.9rem;">Recent notifications from across the platform</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-10">
    <div class="card-body p-4">
        <?php if (empty($alerts)): ?>
            <div class="text-center py-5">
                <div style="width:80px;height:80px;border-radius:50%;background:#f4f6fb;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:2.5rem;color:#d1d5db;">
                    <i class="fa-regular fa-bell-slash"></i>
                </div>
                <h5 class="fw-bold text-secondary">All Caught Up!</h5>
                <p class="text-muted mb-0">There are no new system alerts right now.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush border-0">
                <?php foreach ($alerts as $a): 
                    $type = $a['type'] ?? 'info';
                    $iconBg = 'rgba(59,130,246,0.12)';
                    $iconColor = '#3b82f6';
                    $iconClass = 'fa-info-circle';

                    if ($type === 'success') {
                        $iconBg = 'rgba(34,197,94,0.12)';
                        $iconColor = '#22c55e';
                        $iconClass = 'fa-check-circle';
                    } elseif ($type === 'warning') {
                        $iconBg = 'rgba(245,158,11,0.12)';
                        $iconColor = '#f59e0b';
                        $iconClass = 'fa-triangle-exclamation';
                    } elseif ($type === 'danger') {
                        $iconBg = 'rgba(239,68,68,0.12)';
                        $iconColor = '#ef4444';
                        $iconClass = 'fa-circle-xmark';
                    }
                ?>
                    <div class="list-group-item d-flex gap-3 align-items-start py-3 border-bottom" style="border-color:#f0f2f7 !important;">
                        <div style="width:42px;height:42px;border-radius:12px;background:<?= $iconBg ?>;color:<?= $iconColor ?>;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                            <i class="fa-solid <?= $iconClass ?>"></i>
                        </div>
                        <div class="flex-grow-1" style="min-width:0;">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="fw-bold mb-0 text-truncate" style="color:#1a1a2e;font-size:0.95rem;">
                                    <?= htmlspecialchars($a['title']) ?>
                                </h6>
                                <span class="text-muted flex-shrink-0" style="font-size:0.75rem;">
                                    <?= date('M d, Y h:i A', strtotime($a['created_at'])) ?>
                                </span>
                            </div>
                            <?php if (!empty($a['message'])): ?>
                                <p class="mb-2 text-secondary" style="font-size:0.85rem; line-height:1.4;">
                                    <?= nl2br(htmlspecialchars($a['message'])) ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($a['link_url'])): ?>
                                <div>
                                    <a href="<?= htmlspecialchars($a['link_url']) ?>" class="btn btn-sm btn-light border" style="font-size:0.75rem; font-weight:600; border-radius:8px;">
                                        View Details <i class="fa-solid fa-arrow-right ms-1" style="font-size:0.7rem;"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
