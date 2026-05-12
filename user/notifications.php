<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_user();
$pageTitle = 'Notifications';
$userId = $_SESSION['user_id'];
$notifications = [];

try {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 60");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
} catch(PDOException $e) {}

// Group by time period
$today = []; $thisWeek = []; $earlier = [];
foreach ($notifications as $n) {
    $ts = strtotime($n['created_at']);
    if ($ts >= strtotime('today'))           $today[]    = $n;
    elseif ($ts >= strtotime('-7 days'))     $thisWeek[] = $n;
    else                                     $earlier[]  = $n;
}

require_once '../includes/header.php';
require_once '../includes/nav.php';
?>
<style>
.notif-group-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#9ca3af;margin:1.5rem 0 .75rem;padding-left:.25rem}
.notif-item{display:flex;align-items:flex-start;gap:1rem;padding:1rem 1.25rem;border-radius:1rem;margin-bottom:.5rem;border-left:4px solid transparent;background:#f8f9fc;transition:all .2s}
.notif-item:hover{background:#fff;box-shadow:0 4px 16px rgba(0,0,0,.06)}
.notif-item.type-info   {border-left-color:#3b82f6}
.notif-item.type-success{border-left-color:#22c55e}
.notif-item.type-warning{border-left-color:#f59e0b}
.notif-item.type-danger {border-left-color:#ef4444}
.notif-icon{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.notif-title{font-weight:700;font-size:.9rem;color:#1a1a2e;margin-bottom:.2rem}
.notif-msg{font-size:.82rem;color:#6b7280;margin-bottom:.3rem}
.notif-time{font-size:.72rem;color:#9ca3af}
.empty-state{text-align:center;padding:4rem 1rem}
.empty-icon{width:80px;height:80px;border-radius:50%;background:#f4f6fb;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:2rem;color:#d1d5db}
</style>

<div class="up-wrap"><div class="container-fluid px-0"><div class="d-flex">
<?php require_once '../includes/sidebar-user.php'; ?>
<main class="up-main flex-grow-1" style="min-width:0;">

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1a1a2e;">Notifications</h4>
        <p class="text-muted mb-0" style="font-size:.87rem;"><?= count($notifications) ?> total · All marked as read</p>
    </div>
</div>

<div class="up-card">
<div class="up-card-body pt-4">
<?php if (empty($notifications)): ?>
<div class="empty-state">
    <div class="empty-icon"><i class="fa-regular fa-bell-slash"></i></div>
    <h5 class="fw-bold">All Caught Up!</h5>
    <p class="text-muted" style="font-size:.9rem;">No notifications right now. We'll let you know about your membership, bookings, and payments.</p>
</div>
<?php else: ?>

<?php
$iconMap = [
    'info'   => ['icon'=>'fa-info-circle',         'bg'=>'rgba(59,130,246,.12)', 'color'=>'#3b82f6'],
    'success'=> ['icon'=>'fa-check-circle',         'bg'=>'rgba(34,197,94,.12)',  'color'=>'#22c55e'],
    'warning'=> ['icon'=>'fa-triangle-exclamation', 'bg'=>'rgba(245,158,11,.12)', 'color'=>'#f59e0b'],
    'danger' => ['icon'=>'fa-circle-xmark',         'bg'=>'rgba(239,68,68,.12)',  'color'=>'#ef4444'],
];

$groups = ['Today' => $today, 'This Week' => $thisWeek, 'Earlier' => $earlier];
foreach ($groups as $label => $group):
    if (empty($group)) continue;
?>
    <div class="notif-group-label"><i class="fa-solid fa-calendar-day me-1"></i><?= $label ?></div>
    <?php foreach ($group as $n):
        $type = $n['type'] ?? 'info';
        $ic   = $iconMap[$type] ?? $iconMap['info'];
    ?>
    <div class="notif-item type-<?= htmlspecialchars($type) ?>">
        <div class="notif-icon" style="background:<?= $ic['bg'] ?>;color:<?= $ic['color'] ?>;">
            <i class="fa-solid <?= $ic['icon'] ?>"></i>
        </div>
        <div class="flex-grow-1">
            <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
            <?php if ($n['message']): ?>
            <div class="notif-msg"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
            <?php endif; ?>
            <div class="notif-time"><i class="fa-regular fa-clock me-1"></i><?= date('M d, Y · h:i A', strtotime($n['created_at'])) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<?php endif; ?>
</div>
</div>
</main></div></div></div>
<?php require_once '../includes/footer.php'; ?>
