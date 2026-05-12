<?php
$pageTitle = 'My Membership';
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_user();
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT um.*, mp.plan_name, mp.price, mp.duration_days, mp.features_json, p.receipt_path
        FROM user_memberships um
        JOIN membership_plans mp ON um.plan_id = mp.plan_id
        LEFT JOIN payments p ON um.payment_id = p.payment_id
        WHERE um.user_id = ?
        ORDER BY um.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll();

    $activePlan = null;
    foreach ($history as $plan) {
        if ($plan['status'] == 'active' && strtotime($plan['end_date']) >= time()) {
            $activePlan = $plan;
            break;
        }
    }
} catch (PDOException $e) { $history = []; $activePlan = null; }

$daysLeft = 0; $daysTotal = 1; $progress = 0;
if ($activePlan) {
    $daysTotal = max(1, (int)$activePlan['duration_days']);
    $daysUsed  = max(0, (new DateTime())->diff(new DateTime($activePlan['start_date']))->days);
    $daysLeft  = max(0, (new DateTime($activePlan['end_date']))->diff(new DateTime())->days);
    $progress  = min(100, round($daysUsed / $daysTotal * 100));
}

require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<style>
/* Active Plan Hero */
.mem-hero {
    background: linear-gradient(135deg, #1A1A2E 0%, #0f3460 100%);
    border-radius: 1.5rem;
    padding: 2rem 2.25rem;
    color: #fff;
    position: relative;
    overflow: hidden;
    margin-bottom: 1.75rem;
}
.mem-hero::before {
    content:''; position:absolute; top:-50px; right:-50px;
    width:200px; height:200px; background:rgba(255,107,53,0.1); border-radius:50%;
}
.mem-plan-badge {
    background: rgba(255,107,53,0.2);
    border: 1px solid rgba(255,107,53,0.4);
    color: #FF6B35;
    font-size:0.8rem; font-weight:700;
    padding:0.3rem 0.9rem; border-radius:100px;
    display:inline-block; margin-bottom:0.75rem;
}
.mem-hero h2 { font-size:2rem; font-weight:900; margin:0 0 0.35rem; }
.mem-hero .price-tag { font-size:1.5rem; font-weight:800; color:#FF6B35; }
.sessions-badge {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius:1rem; padding:1.25rem 1.5rem; text-align:center;
    backdrop-filter:blur(8px);
}
.sessions-badge .count { font-size:3rem; font-weight:900; line-height:1; color:#FF6B35; }
.sessions-badge .lbl  { font-size:0.8rem; color:rgba(255,255,255,0.65); text-transform:uppercase; letter-spacing:0.07em; }

/* Progress bar */
.mem-progress { height:8px; border-radius:100px; background:rgba(255,255,255,0.1); overflow:hidden; margin:1rem 0; }
.mem-progress-bar { height:100%; background:linear-gradient(90deg,#FF6B35,#ff9a5c); border-radius:100px; }

/* Timeline history */
.history-item {
    display:flex; align-items:flex-start; gap:1.1rem;
    padding:1.1rem 0; border-bottom:1px solid #f4f6fb;
}
.history-item:last-child { border-bottom:none; }
.history-dot {
    width:38px; height:38px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:0.9rem;
}
.history-plan { font-weight:700; font-size:0.92rem; color:#1a1a2e; margin-bottom:0.2rem; }
.history-date { font-size:0.78rem; color:#9ca3af; }

/* Empty */
.empty-state { text-align:center; padding:3rem 1rem; }
.empty-state-icon {
    width:80px; height:80px; border-radius:50%;
    background:#f4f6fb; display:flex; align-items:center;
    justify-content:center; margin:0 auto 1.25rem; font-size:2rem; color:#d1d5db;
}
</style>

<div class="up-wrap">
<div class="container-fluid px-0">
<div class="d-flex">
    <?php require_once '../includes/sidebar-user.php'; ?>

    <main class="up-main flex-grow-1" style="min-width:0;">

        <!-- Page Title -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-0" style="color:#1a1a2e;">My Membership</h4>
                <p class="text-muted mb-0" style="font-size:0.87rem;">Manage your gym membership and view history</p>
            </div>
            <a href="<?= SITE_URL ?>/plans.php" class="btn btn-sm rounded-pill fw-bold px-4" style="background:#FF6B35;color:#fff;">
                <i class="fa-solid fa-arrow-up me-1"></i>Upgrade Plan
            </a>
        </div>

        <!-- Active Plan Hero -->
        <?php if ($activePlan): ?>
        <div class="mem-hero mb-4">
            <div class="row align-items-center g-4">
                <div class="col-md-8">
                    <div class="mem-plan-badge"><i class="fa-solid fa-check-circle me-1"></i>Active Membership</div>
                    <h2><?= htmlspecialchars($activePlan['plan_name']) ?></h2>
                    <div class="price-tag">₹<?= number_format($activePlan['price'], 0) ?> <span style="font-size:1rem;color:rgba(255,255,255,0.5);">/ <?= $activePlan['duration_days'] ?> days</span></div>

                    <div class="mem-progress mt-3">
                        <div class="mem-progress-bar" style="width:<?= $progress ?>%;"></div>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:0.8rem;color:rgba(255,255,255,0.6);">
                        <span>Started <?= date('M d, Y', strtotime($activePlan['start_date'])) ?></span>
                        <span><?= $daysLeft ?> days remaining</span>
                        <span>Expires <?= date('M d, Y', strtotime($activePlan['end_date'])) ?></span>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="sessions-badge d-inline-block">
                        <div class="count"><?= $activePlan['sessions_remaining'] ?? 0 ?></div>
                        <div class="lbl">Trainer Sessions Left</div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="up-card mb-4">
            <div class="up-card-body pt-4">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa-solid fa-id-card-clip"></i></div>
                    <h5 class="fw-bold">No Active Membership</h5>
                    <p class="text-muted mb-4" style="font-size:0.9rem;">Choose a plan to unlock full gym access and book personal trainers.</p>
                    <a href="<?= SITE_URL ?>/plans.php" class="btn rounded-pill px-5 fw-bold" style="background:#FF6B35;color:#fff;">View Plans</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Membership History -->
        <div class="up-card">
            <div class="up-card-header">
                <h6 class="up-card-title"><i class="fa-solid fa-clock-rotate-left me-2" style="color:#9ca3af;"></i>Membership History</h6>
            </div>
            <div class="up-card-body">
                <?php if (empty($history)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fa-solid fa-folder-open"></i></div>
                        <p class="text-muted">No membership history yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($history as $record):
                        $isActive  = $record['status'] === 'active' && strtotime($record['end_date']) >= time();
                        $isExpired = $record['status'] === 'expired' || (!$isActive && $record['status'] === 'active');
                        if ($isActive)  { $dotBg = 'background:rgba(34,197,94,0.12);color:#22c55e;'; $badge = 'bg-success'; }
                        elseif ($record['status'] === 'cancelled') { $dotBg = 'background:rgba(107,114,128,0.12);color:#6b7280;'; $badge = 'bg-secondary'; }
                        else            { $dotBg = 'background:rgba(239,68,68,0.1);color:#ef4444;'; $badge = 'bg-danger'; }
                    ?>
                    <div class="history-item">
                        <div class="history-dot" style="<?= $dotBg ?>">
                            <i class="fa-solid fa-id-card"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="history-plan"><?= htmlspecialchars($record['plan_name']) ?></div>
                            <div class="history-date">
                                <?= date('M d, Y', strtotime($record['start_date'])) ?> → <?= date('M d, Y', strtotime($record['end_date'])) ?>
                                &nbsp;·&nbsp; <?= $record['duration_days'] ?> days
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?= $badge ?> rounded-pill"><?= ucfirst($record['status']) ?></span>
                            <?php if ($record['receipt_path']): ?>
                                <a href="<?= htmlspecialchars($record['receipt_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-download me-1"></i>PDF
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>
</div>
</div>

<?php require_once '../includes/footer.php'; ?>
