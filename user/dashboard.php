<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_user();

$pageTitle = 'My Dashboard';
$userId = $_SESSION['user_id'];

// ── Holiday check (server-side for reliable popup) ─────────────
$todayHoliday = null;
$upcomingHoliday = null;
try {
    $hStmt = $pdo->prepare("
        SELECT * FROM holidays
        WHERE holiday_date >= CURDATE()
        AND holiday_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        AND target_type = 'all'
        ORDER BY holiday_date ASC
    ");
    $hStmt->execute();
    foreach ($hStmt->fetchAll() as $h) {
        $h['formatted_date'] = date('M d, Y', strtotime($h['holiday_date']));
        if ($h['holiday_date'] === date('Y-m-d')) {
            $todayHoliday = $h;
        } elseif (!$upcomingHoliday) {
            $upcomingHoliday = $h;
        }
    }
} catch (Exception $e) {}

try {
    $memStmt = $pdo->prepare("
        SELECT um.*, mp.plan_name, mp.duration_days, mp.trainer_sessions, mp.price
        FROM user_memberships um
        JOIN membership_plans mp ON um.plan_id = mp.plan_id
        WHERE um.user_id = ? AND um.status IN ('active', 'expired')
        ORDER BY um.end_date DESC LIMIT 1
    ");
    $memStmt->execute([$userId]);
    $membership = $memStmt->fetch();

    $bookStmt = $pdo->prepare("
        SELECT tb.*, t.full_name AS trainer_name, t.specialization, t.photo
        FROM trainer_bookings tb
        JOIN trainers t ON tb.trainer_id = t.trainer_id
        WHERE tb.user_id = ? AND tb.status != 'cancelled' AND tb.session_date >= CURDATE()
        ORDER BY tb.session_date ASC, tb.start_time ASC LIMIT 4
    ");
    $bookStmt->execute([$userId]);
    $upcomingBookings = $bookStmt->fetchAll();

    $bmiStmt = $pdo->prepare("SELECT bmi_value, category, recorded_at FROM bmi_records WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 6");
    $bmiStmt->execute([$userId]);
    $bmiRecords = $bmiStmt->fetchAll();

    $payCount = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status='success'");
    $payCount->execute([$userId]);
    $paymentCount = (int)$payCount->fetchColumn();

    $totalSpent = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE user_id = ? AND status='success'");
    $totalSpent->execute([$userId]);
    $totalSpentAmt = (float)$totalSpent->fetchColumn();

    $userInfo = $pdo->prepare("SELECT full_name, email, profile_photo FROM users WHERE user_id = ?");
    $userInfo->execute([$userId]);
    $user = $userInfo->fetch();

} catch(PDOException $e) {
    $membership = null; $upcomingBookings = []; $bmiRecords = []; $paymentCount = 0; $totalSpentAmt = 0;
    $user = ['full_name' => $_SESSION['full_name'] ?? 'Member', 'email' => '', 'profile_photo' => null];
}

$daysTotal = $membership ? max(1, (int)$membership['duration_days']) : 1;
$daysUsed  = $membership ? max(0, (new DateTime())->diff(new DateTime($membership['start_date']))->days) : 0;
$daysLeft  = $membership ? max(0, (new DateTime($membership['end_date']))->diff(new DateTime())->days) : 0;
$progress  = $membership ? min(100, round($daysUsed / $daysTotal * 100)) : 0;
$bmiSavedMessage = isset($_GET['bmi_saved']) && $_GET['bmi_saved'] === '1';

$bmiChartLabels = array_reverse(array_column($bmiRecords, 'recorded_at'));
$bmiChartValues = array_reverse(array_column($bmiRecords, 'bmi_value'));
$latestBmi      = $bmiRecords[0] ?? null;

require_once '../config/config.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<style>
/* ── Welcome Banner ─────────── */
.up-hero {
    background: linear-gradient(135deg, #1A1A2E 0%, #16213E 50%, #0f3460 100%);
    border-radius: 1.5rem;
    padding: 2rem 2.25rem;
    color: #fff;
    position: relative;
    overflow: hidden;
    margin-bottom: 1.75rem;
}
.up-hero::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 200px; height: 200px;
    background: rgba(255,107,53,0.12);
    border-radius: 50%;
}
.up-hero::after {
    content: '';
    position: absolute;
    bottom: -60px; right: 100px;
    width: 150px; height: 150px;
    background: rgba(255,107,53,0.07);
    border-radius: 50%;
}
.up-hero-avatar {
    width: 62px; height: 62px;
    border-radius: 50%;
    background: rgba(255,107,53,0.25);
    color: #FF6B35;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; font-weight: 800;
    flex-shrink: 0;
    border: 2.5px solid rgba(255,107,53,0.5);
}
.up-hero-avatar img {
    width: 100%; height: 100%;
    border-radius: 50%; object-fit: cover;
    border: 2.5px solid #FF6B35;
}
.up-hero h4 { font-size: 1.4rem; font-weight: 800; margin: 0; }
.up-hero p  { opacity: 0.72; margin: 0.35rem 0 0; font-size: 0.92rem; }
.up-hero-badge {
    background: rgba(255,107,53,0.18);
    color: #FF6B35;
    border: 1px solid rgba(255,107,53,0.3);
    border-radius: 100px;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.3rem 0.85rem;
    display: inline-block;
    margin-bottom: 0.6rem;
}
.hero-btn {
    background: rgba(255,255,255,0.12);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 100px;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0.45rem 1.1rem;
    text-decoration: none;
    transition: all 0.22s ease;
    backdrop-filter: blur(6px);
}
.hero-btn:hover { background: #FF6B35; border-color: #FF6B35; color: #fff; }
.hero-btn.primary { background: #FF6B35; border-color: #FF6B35; }
.hero-btn.primary:hover { background: #e85a22; }

/* ── KPI Cards ───────────────── */
.kpi-card {
    background: #fff;
    border-radius: 1.25rem;
    padding: 1.35rem 1.5rem;
    display: flex; align-items: center; gap: 1.1rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    border: 1px solid #f0f2f7;
    transition: transform 0.22s ease, box-shadow 0.22s ease;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
.kpi-icon {
    width: 50px; height: 50px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
}
.kpi-label { font-size: 0.78rem; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
.kpi-value { font-size: 1.55rem; font-weight: 800; color: #1a1a2e; line-height: 1.1; }
.kpi-sub   { font-size: 0.75rem; color: #9ca3af; margin-top: 0.15rem; }

/* ── Section Cards ───────────── */
.up-card {
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    border: 1px solid #f0f2f7;
}
.up-card-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.35rem 1.5rem 0;
    margin-bottom: 1.25rem;
}
.up-card-title { font-size: 1rem; font-weight: 800; color: #1a1a2e; margin: 0; }
.up-card-body  { padding: 0 1.5rem 1.5rem; }

/* ── Circular Progress Ring ──── */
.plan-ring {
    width: 110px; height: 110px; border-radius: 50%;
    background: conic-gradient(#FF6B35 calc(var(--prog) * 1%), #f0f2f7 0deg);
    display: flex; align-items: center; justify-content: center;
    position: relative; margin: 0 auto 1.25rem;
}
.plan-ring::before { content:''; position:absolute; inset:12px; background:#fff; border-radius:50%; }
.plan-ring-inner  {
    position: relative; z-index: 1;
    text-align: center; display: flex; flex-direction: column; align-items: center;
}
.plan-ring-days   { font-size: 1.4rem; font-weight: 900; color: #1a1a2e; line-height: 1; }
.plan-ring-label  { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; color: #9ca3af; letter-spacing: 0.07em; margin-top: 2px; }

/* ── BMI Badge ───────────────── */
.bmi-latest-badge {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.4rem 1rem; border-radius: 100px;
    font-size: 0.82rem; font-weight: 700;
}

/* ── Booking Cards ───────────── */
.booking-mini {
    background: #f8f9fc;
    border-radius: 1rem;
    padding: 1rem 1.1rem;
    border: 1px solid #eef0f7;
    transition: all 0.22s ease;
}
.booking-mini:hover { background: #fff; box-shadow: 0 6px 20px rgba(0,0,0,0.07); }
.booking-trainer-photo {
    width: 40px; height: 40px;
    border-radius: 50%; object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.booking-trainer-initials {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff; font-weight: 700; font-size: 0.9rem;
    display: flex; align-items: center; justify-content: center;
}
.booking-date-pill {
    background: rgba(255,107,53,0.1);
    color: #FF6B35;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.2rem 0.7rem;
    display: inline-block;
}

/* ── Empty State ─────────────── */
.empty-state { text-align: center; padding: 2.5rem 1rem; }
.empty-state-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: #f4f6fb;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.8rem; color: #d1d5db;
}

/* ── Stat Trend Badge ─────────── */
.trend-up   { color: #22c55e; font-size: 0.72rem; font-weight: 700; }
.trend-down { color: #ef4444; font-size: 0.72rem; font-weight: 700; }
</style>

<div class="up-wrap">
<div class="container-fluid px-0">
<div class="d-flex">
    <?php require_once '../includes/sidebar-user.php'; ?>

    <main class="up-main flex-grow-1" style="min-width:0;">
        <?php if ($todayHoliday): ?>
        <!-- ━━━ Persistent Holiday Banner ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <div id="holidayBanner" style="
            position: sticky;
            top: 5.5rem;
            z-index: 99;
            background: linear-gradient(135deg, #4f46e5 0%, #c026d3 100%);
            color: #fff;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            border-radius: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 18px rgba(192, 38, 211, 0.45);
            animation: bannerPulse 2.5s ease-in-out infinite;
        ">
            <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                <span style="font-size:1.6rem;">🏖️</span>
                <div>
                    <strong style="font-size:1rem; letter-spacing:0.02em;">GYM HOLIDAY TODAY</strong>
                    <span style="margin:0 0.5rem; opacity:0.6;">|</span>
                    <span style="font-size:0.95rem;"><?= htmlspecialchars($todayHoliday['title']) ?></span>
                    <?php if (!empty($todayHoliday['description'])): ?>
                        <span style="margin:0 0.5rem; opacity:0.6;">—</span>
                        <span style="font-size:0.85rem; opacity:0.9;"><?= htmlspecialchars($todayHoliday['description']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:0.6rem; flex-shrink:0;">
                <span style="background:rgba(255,255,255,0.25); border-radius:20px; padding:0.25rem 0.85rem; font-size:0.8rem; font-weight:700; letter-spacing:0.05em;">
                    <?= date('l, F j') ?>
                </span>
                <span style="background:rgba(0,0,0,0.25); border-radius:20px; padding:0.25rem 0.85rem; font-size:0.78rem; font-weight:700; letter-spacing:0.04em;">
                    🚫 Trainer Sessions Unavailable
                </span>
            </div>
        </div>
        <style>
        @keyframes bannerPulse {
            0%, 100% { box-shadow: 0 4px 18px rgba(192, 38, 211, 0.45); }
            50%       { box-shadow: 0 4px 30px rgba(192, 38, 211, 0.75); }
        }
        </style>
        <?php endif; ?>

        <?php if ($bmiSavedMessage): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4" role="alert">
                <i class="fa-solid fa-check-circle me-2"></i>Your BMI result was saved successfully and is now visible on your dashboard.
            </div>
        <?php endif; ?>

        <?php if ($membership): ?>
            <?php if ($membership['status'] === 'expired'): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center justify-content-between" role="alert">
                    <div>
                        <i class="fa-solid fa-circle-exclamation me-2"></i><strong>Your membership has expired!</strong> You can no longer book trainer sessions or access member benefits.
                    </div>
                    <a href="<?= SITE_URL ?>/plans.php" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold">Renew Now</a>
                </div>
            <?php elseif ($daysLeft <= 3): ?>
                <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center justify-content-between" style="background:#fff7ed; color:#9a3412;" role="alert">
                    <div>
                        <i class="fa-solid fa-triangle-exclamation me-2"></i><strong>Your membership expires in <?= $daysLeft ?> <?= $daysLeft === 1 ? 'day' : 'days' ?>!</strong> Renew soon to avoid interruption.
                    </div>
                    <a href="<?= SITE_URL ?>/plans.php" class="btn btn-sm rounded-pill px-3 fw-bold" style="background:#c2410c; color:#fff;">Renew Now</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Hero Banner -->
        <div class="up-hero mb-4">
            <div class="d-flex align-items-center gap-4 flex-wrap">
                <!-- Avatar -->
                <div class="up-hero-avatar">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars($user['profile_photo']) ?>" alt="avatar">
                    <?php else: ?>
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>

                <!-- Text -->
                <div class="flex-grow-1">
                    <?php if ($membership): ?>
                        <div class="up-hero-badge"><i class="fa-solid fa-check-circle me-1"></i><?= htmlspecialchars($membership['plan_name']) ?> Plan</div>
                    <?php endif; ?>
                    <h4>Welcome back, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?>! 💪</h4>
                    <p>
                        <?php if ($membership): ?>
                            <?php if ($membership['status'] === 'expired'): ?>
                                <span class="text-danger fw-bold"><i class="fa-solid fa-ban me-1"></i>Plan Expired</span> on <?= date('M d, Y', strtotime($membership['end_date'])) ?>
                            <?php else: ?>
                                Your plan expires in <strong><?= $daysLeft ?> days</strong>. Keep pushing your limits!
                            <?php endif; ?>
                        <?php else: ?>
                            No active membership yet. <a href="<?= SITE_URL ?>/plans.php" class="text-white fw-bold text-decoration-underline">Choose a plan →</a>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Quick Actions -->
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= SITE_URL ?>/user/book-trainer.php" class="hero-btn primary"><i class="fa-solid fa-dumbbell me-1"></i>Book Trainer</a>
                    <a href="<?= SITE_URL ?>/bmi-calculator.php"    class="hero-btn"><i class="fa-solid fa-heart-pulse me-1"></i>Check BMI</a>
                    <a href="<?= SITE_URL ?>/user/profile.php"      class="hero-btn"><i class="fa-solid fa-user me-1"></i>Profile</a>
                </div>
            </div>
        </div>

        <!-- KPI Strip -->
        <div class="row g-3 mb-4">
            <!-- Membership -->
            <div class="col-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon" style="background:rgba(255,107,53,0.1);color:#FF6B35;">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Membership</div>
                        <div class="kpi-value">
                            <?php 
                                if (!$membership) echo 'None';
                                elseif ($membership['status'] === 'expired') echo '<span class="text-danger">Expired</span>';
                                else echo 'Active';
                            ?>
                        </div>
                        <div class="kpi-sub">
                            <?php 
                                if (!$membership) echo 'No active plan';
                                elseif ($membership['status'] === 'expired') echo 'Ended '.date('M d', strtotime($membership['end_date']));
                                else echo $daysLeft . ' days left';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Sessions -->
            <div class="col-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon" style="background:rgba(99,102,241,0.1);color:#6366f1;">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Sessions</div>
                        <div class="kpi-value"><?= count($upcomingBookings) ?></div>
                        <div class="kpi-sub">Upcoming bookings</div>
                    </div>
                </div>
            </div>
            <!-- BMI -->
            <div class="col-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon" style="background:rgba(6,214,160,0.1);color:#06d6a0;">
                        <i class="fa-solid fa-heart-pulse"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Latest BMI</div>
                        <div class="kpi-value"><?= $latestBmi ? number_format($latestBmi['bmi_value'], 1) : '—' ?></div>
                        <div class="kpi-sub"><?= $latestBmi ? htmlspecialchars($latestBmi['category']) : 'No records' ?></div>
                    </div>
                </div>
            </div>
            <!-- Spent -->
            <div class="col-6 col-xl-3">
                <div class="kpi-card">
                    <div class="kpi-icon" style="background:rgba(247,37,133,0.1);color:#f72585;">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div>
                        <div class="kpi-label">Total Spent</div>
                        <div class="kpi-value">₹<?= number_format($totalSpentAmt, 0) ?></div>
                        <div class="kpi-sub"><?= $paymentCount ?> successful payment<?= $paymentCount !== 1 ? 's' : '' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Membership + BMI Row -->
        <div class="row g-4 mb-4">

            <!-- Membership Progress -->
            <div class="col-lg-5">
                <div class="up-card h-100">
                    <div class="up-card-header">
                        <h6 class="up-card-title"><i class="fa-solid fa-id-card me-2 text-primary-custom"></i>Membership Status</h6>
                        <a href="<?= SITE_URL ?>/user/membership.php" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size:0.78rem;">View Details</a>
                    </div>
                    <div class="up-card-body">
                        <?php if ($membership): ?>
                            <div class="plan-ring" style="--prog:<?= $progress ?>">
                                <div class="plan-ring-inner">
                                    <div class="plan-ring-days"><?= $daysLeft ?></div>
                                    <div class="plan-ring-label">Days Left</div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between text-muted" style="font-size:0.78rem;">
                                <span>Started <?= date('M d', strtotime($membership['start_date'])) ?></span>
                                <span>Expires <?= date('M d, Y', strtotime($membership['end_date'])) ?></span>
                            </div>
                            <div class="progress my-2" style="height:6px;border-radius:100px;background:#f0f2f7;">
                                <div class="progress-bar" style="width:<?= $progress ?>%;background:linear-gradient(90deg,#FF6B35,#ff9a5c);border-radius:100px;"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-3">
                                <span class="text-muted" style="font-size:0.82rem;">Plan: <strong><?= htmlspecialchars($membership['plan_name']) ?></strong></span>
                                <span class="text-muted" style="font-size:0.82rem;">Sessions: <strong style="color:#FF6B35;"><?= $membership['sessions_remaining'] ?? 0 ?></strong> left</span>
                            </div>
                            <a href="<?= SITE_URL ?>/plans.php" class="btn btn-sm w-100 mt-3 rounded-pill fw-semibold" style="background:#f4f6fb;color:#1a1a2e;font-size:0.85rem;">Upgrade / Renew →</a>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fa-solid fa-id-card-clip"></i></div>
                                <p class="text-muted mb-3" style="font-size:0.9rem;">No active membership plan</p>
                                <a href="<?= SITE_URL ?>/plans.php" class="btn btn-sm rounded-pill px-4 fw-bold" style="background:#FF6B35;color:#fff;">Get a Plan</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- BMI Trend -->
            <div class="col-lg-7">
                <div class="up-card h-100">
                    <div class="up-card-header">
                        <h6 class="up-card-title"><i class="fa-solid fa-chart-line me-2 text-success"></i>BMI Trend</h6>
                        <a href="<?= SITE_URL ?>/user/bmi-history.php" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size:0.78rem;">Full History</a>
                    </div>
                    <div class="up-card-body">
                        <?php if (count($bmiRecords) >= 2): ?>
                            <canvas id="bmiTrendChart" height="130"></canvas>
                        <?php elseif (count($bmiRecords) === 1): ?>
                            <div class="text-center py-3">
                                <div style="font-size:3rem;font-weight:900;color:#FF6B35;"><?= number_format($bmiRecords[0]['bmi_value'], 1) ?></div>
                                <p class="text-muted mb-1">Your latest BMI</p>
                                <span class="bmi-latest-badge" style="background:rgba(255,107,53,0.1);color:#FF6B35;">
                                    <?= htmlspecialchars($bmiRecords[0]['category']) ?>
                                </span>
                                <p class="text-muted mt-3" style="font-size:0.82rem;">Log at least 2 readings to see your progress chart.</p>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fa-solid fa-chart-line"></i></div>
                                <p class="text-muted mb-3" style="font-size:0.9rem;">No BMI records yet</p>
                                <a href="<?= SITE_URL ?>/bmi-calculator.php" class="btn btn-sm rounded-pill px-4 fw-bold" style="background:#FF6B35;color:#fff;">Check BMI Now</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Bookings -->
        <div class="up-card">
            <div class="up-card-header">
                <h6 class="up-card-title"><i class="fa-solid fa-calendar-check me-2" style="color:#6366f1;"></i>Upcoming Sessions</h6>
                <a href="<?= SITE_URL ?>/user/bookings.php" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size:0.78rem;">All Bookings</a>
            </div>
            <div class="up-card-body">
                <?php if (empty($upcomingBookings)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fa-regular fa-calendar-xmark"></i></div>
                        <p class="text-muted mb-3" style="font-size:0.9rem;">No upcoming sessions booked</p>
                        <a href="<?= SITE_URL ?>/user/book-trainer.php" class="btn btn-sm rounded-pill px-4 fw-bold" style="background:#FF6B35;color:#fff;">Book a Trainer</a>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($upcomingBookings as $b): ?>
                        <div class="col-sm-6 col-xl-3">
                            <div class="booking-mini">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <?php if (!empty($b['photo'])): 
                                        $photoUrl = filter_var($b['photo'], FILTER_VALIDATE_URL) ? $b['photo'] : SITE_URL . '/' . ltrim($b['photo'], '/');
                                    ?>
                                        <img src="<?= htmlspecialchars($photoUrl) ?>" class="booking-trainer-photo" alt="">
                                    <?php else: ?>
                                        <div class="booking-trainer-initials"><?= strtoupper(substr($b['trainer_name'], 0, 1)) ?></div>
                                    <?php endif; ?>
                                    <div style="min-width:0;">
                                        <div class="fw-bold text-truncate" style="font-size:0.87rem;color:#1a1a2e;"><?= htmlspecialchars($b['trainer_name']) ?></div>
                                        <div class="text-muted text-truncate" style="font-size:0.72rem;"><?= htmlspecialchars($b['specialization']) ?></div>
                                    </div>
                                </div>
                                <div class="booking-date-pill mb-2"><?= date('D, M d', strtotime($b['session_date'])) ?></div>
                                <div class="text-muted" style="font-size:0.75rem;"><i class="fa-regular fa-clock me-1"></i><?= date('h:i A', strtotime($b['start_time'])) ?> – <?= date('h:i A', strtotime($b['end_time'])) ?></div>
                                <span class="badge mt-2 <?= $b['status'] === 'confirmed' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= ucfirst($b['status']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (count($bmiRecords) >= 2): ?>
new Chart(document.getElementById('bmiTrendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('M d', strtotime($d)), $bmiChartLabels)) ?>,
        datasets: [{
            label: 'BMI',
            data: <?= json_encode(array_map('floatval', $bmiChartValues)) ?>,
            borderColor: '#FF6B35',
            backgroundColor: 'rgba(255,107,53,0.06)',
            borderWidth: 2.5, fill: true, tension: 0.4,
            pointRadius: 5, pointBackgroundColor: '#FF6B35',
            pointBorderColor: '#fff', pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => 'BMI: ' + ctx.raw } } },
        scales: {
            y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { callback: v => v.toFixed(1) } },
            x: { grid: { display: false } }
        }
    }
});
<?php endif; ?>
</script>

<?php if ($todayHoliday): ?>
<!-- ── Holiday Today Modal ───────────────────────────────────── -->
<div class="modal fade" id="holidayTodayModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius:18px;">
      <div style="background:linear-gradient(135deg, #4f46e5 0%, #c026d3 100%);padding:2.5rem 2rem 1.5rem;text-align:center;">
        <i class="fa-solid fa-umbrella-beach" style="font-size:3.5rem;color:#fff;"></i>
        <h3 class="fw-bold mt-3 mb-0" style="color:#fff;">Gym Holiday Today!</h3>
        <p class="mb-0 mt-1" style="color:rgba(255,255,255,.85);font-size:.95rem;"><?= date('l, F j, Y') ?></p>
      </div>
      <div class="modal-body text-center p-4">
        <h4 class="fw-bold text-dark mb-2"><?= htmlspecialchars($todayHoliday['title']) ?></h4>
        <?php if (!empty($todayHoliday['description'])): ?>
          <p class="text-muted mb-3"><?= htmlspecialchars($todayHoliday['description']) ?></p>
        <?php endif; ?>
        <div class="alert py-2 mb-3 fw-bold" style="background:#fdf4ff; color:#a21caf; border:1px solid #f0abfc; border-radius:10px;">
          <i class="fa-solid fa-ban me-2"></i>Trainer sessions are not available today.
        </div>
        <button type="button" class="btn px-5 py-2 fw-bold rounded-pill" style="background:linear-gradient(135deg, #4f46e5 0%, #c026d3 100%); border:none; color:white; box-shadow: 0 4px 15px rgba(192, 38, 211, 0.3);" data-bs-dismiss="modal">Got It!</button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var holidayModal = new bootstrap.Modal(document.getElementById('holidayTodayModal'), {
        backdrop: 'static',
        keyboard: false
    });
    holidayModal.show();
});
</script>
<?php elseif ($upcomingHoliday): ?>
<!-- ── Upcoming Holiday Toast ─────────────────────────────────── -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1060;">
  <div id="upcomingHolidayToast" class="toast align-items-center text-white bg-danger border-0 shadow" role="alert" data-bs-delay="12000">
    <div class="d-flex">
      <div class="toast-body">
        <i class="fa-solid fa-umbrella-beach me-2"></i>
        <strong>Upcoming Holiday:</strong> <?= htmlspecialchars($upcomingHoliday['title']) ?> &mdash; <?= $upcomingHoliday['formatted_date'] ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){ new bootstrap.Toast(document.getElementById('upcomingHolidayToast')).show(); });</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
