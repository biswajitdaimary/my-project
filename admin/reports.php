<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

$pageTitle = 'Reports & Analytics';

try {
    // Monthly revenue last 12 months
    $monthlyRevenue = $pdo->query("
        SELECT DATE_FORMAT(payment_date,'%b %Y') AS month, SUM(amount) AS total, COUNT(*) AS transactions
        FROM payments WHERE status='success' AND payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY YEAR(payment_date), MONTH(payment_date)
        ORDER BY payment_date ASC
    ")->fetchAll();

    // Member growth last 12 months
    $memberGrowth = $pdo->query("
        SELECT DATE_FORMAT(created_at,'%b %Y') AS month, COUNT(*) AS new_members
        FROM users WHERE role='user' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY created_at ASC
    ")->fetchAll();

    // Trainer booking stats
    $trainerStats = $pdo->query("
        SELECT t.full_name, t.specialization,
               COUNT(tb.booking_id) AS total_bookings,
               SUM(CASE WHEN tb.status IN ('confirmed', 'completed') THEN 1 ELSE 0 END) AS successful,
               SUM(CASE WHEN tb.status = 'pending' THEN 1 ELSE 0 END) AS pending,
               SUM(CASE WHEN tb.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
        FROM trainers t
        LEFT JOIN trainer_bookings tb ON t.trainer_id = tb.trainer_id
        WHERE t.is_active = 1
        GROUP BY t.trainer_id
        ORDER BY total_bookings DESC
    ")->fetchAll();

    // Plan popularity
    $planStats = $pdo->query("
        SELECT mp.plan_name, COUNT(um.membership_id) AS signups, COALESCE(SUM(p.amount),0) AS revenue
        FROM membership_plans mp
        LEFT JOIN user_memberships um ON mp.plan_id = um.plan_id
        LEFT JOIN payments p ON um.payment_id = p.payment_id AND p.status='success'
        GROUP BY mp.plan_id ORDER BY signups DESC
    ")->fetchAll();

    // Summary totals
    $totals = $pdo->query("
        SELECT
            (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success') AS all_time_revenue,
            (SELECT COUNT(*) FROM users WHERE role='user') AS total_members,
            (SELECT COUNT(*) FROM user_memberships WHERE status='active') AS active_memberships,
            (SELECT COUNT(*) FROM trainer_bookings) AS total_bookings
    ")->fetch();

    // ── Generate full 12-month timeline to fill gaps ────────────────────────
    $revenueDataMap = [];
    $growthDataMap  = [];
    $timelineLabels = [];

    for ($i = 11; $i >= 0; $i--) {
        $m = date('b Y', strtotime("-$i months")); // Match MySQL DATE_FORMAT %b %Y (e.g. May 2026)
        // Wait, DATE_FORMAT %b is short month name. date('M') is also short month name.
        $m = date('M Y', strtotime("-$i months"));
        $timelineLabels[] = $m;
        $revenueDataMap[$m] = 0;
        $growthDataMap[$m]  = 0;
    }

    foreach ($monthlyRevenue as $row) {
        if (isset($revenueDataMap[$row['month']])) {
            $revenueDataMap[$row['month']] = (float)$row['total'];
        }
    }
    foreach ($memberGrowth as $row) {
        if (isset($growthDataMap[$row['month']])) {
            $growthDataMap[$row['month']] = (int)$row['new_members'];
        }
    }

    $finalRevenueData = array_values($revenueDataMap);
    $finalGrowthData  = array_values($growthDataMap);

} catch(PDOException $e) {
    $finalRevenueData = $finalGrowthData = array_fill(0,12,0);
    $timelineLabels = [];
    $trainerStats=$planStats=[]; $totals=['all_time_revenue'=>0,'total_members'=>0,'active_memberships'=>0,'total_bookings'=>0];
}

require_once 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold m-0">Reports & Analytics</h3>
    <span class="text-muted small">Data updated in real-time from your database</span>
</div>

<!-- Summary row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg,#FF6B35,#e85a25);">
            <div class="card-body p-4 text-center"><p class="mb-1 opacity-75 small fw-bold">All-Time Revenue</p><h2 class="fw-bold mb-0">₹<?= number_format($totals['all_time_revenue'],0) ?></h2></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg,#4361ee,#3a0ca3);">
            <div class="card-body p-4 text-center"><p class="mb-1 opacity-75 small fw-bold">Total Members</p><h2 class="fw-bold mb-0"><?= number_format($totals['total_members']) ?></h2></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg,#7209b7,#560bad);">
            <div class="card-body p-4 text-center"><p class="mb-1 opacity-75 small fw-bold">Active Memberships</p><h2 class="fw-bold mb-0"><?= number_format($totals['active_memberships']) ?></h2></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg,#06d6a0,#028a6a);">
            <div class="card-body p-4 text-center"><p class="mb-1 opacity-75 small fw-bold">Total Bookings</p><h2 class="fw-bold mb-0"><?= number_format($totals['total_bookings']) ?></h2></div>
        </div>
    </div>
</div>

<!-- Charts row -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Monthly Revenue (12 Months)</h5>
                <canvas id="monthlyRevenueChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">New Member Growth (12 Months)</h5>
                <canvas id="memberGrowthChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Plan Popularity + Trainer Stats -->
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Plan Popularity</h5>
                <canvas id="planChart" height="160"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Trainer Booking Stats</h5>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th>Trainer</th><th>Specialty</th><th>Total</th><th>Success</th><th>Pending</th><th>Rate</th></tr></thead>
                        <tbody>
                            <?php foreach($trainerStats as $t): $rate = $t['total_bookings'] > 0 ? round($t['successful']/$t['total_bookings']*100) : 0; ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($t['full_name']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($t['specialization']) ?></td>
                                <td><?= $t['total_bookings'] ?></td>
                                <td><span class="badge bg-success"><?= $t['successful'] ?></span></td>
                                <td><span class="badge bg-warning text-dark"><?= $t['pending'] ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:6px;">
                                            <div class="progress-bar bg-primary" style="width:<?= $rate ?>%"></div>
                                        </div>
                                        <small class="fw-bold"><?= $rate ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($trainerStats)): ?><tr><td colspan="6" class="text-center text-muted py-4">No data yet</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const palette = ['#FF6B35','#4361ee','#06d6a0','#f72585','#7209b7','#e63946'];

new Chart(document.getElementById('monthlyRevenueChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($timelineLabels) ?>,
        datasets: [{ label:'Revenue ₹', data: <?= json_encode($finalRevenueData) ?>, backgroundColor:'rgba(255,107,53,0.7)', borderRadius:8 }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, ticks:{callback:v=>'₹'+v.toLocaleString('en-IN')}},x:{grid:{display:false}}} }
});

new Chart(document.getElementById('memberGrowthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($timelineLabels) ?>,
        datasets: [{ label:'New Members', data: <?= json_encode($finalGrowthData) ?>, borderColor:'#4361ee', backgroundColor:'rgba(67,97,238,0.08)', fill:true, tension:0.4, pointRadius:4 }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, ticks:{stepSize:1}},x:{grid:{display:false}}} }
});

new Chart(document.getElementById('planChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($planStats,'plan_name')) ?>,
        datasets: [{ data: <?= json_encode(array_column($planStats,'signups')) ?>, backgroundColor: palette, borderWidth:0, hoverOffset:8 }]
    },
    options: { responsive:true, cutout:'65%', plugins:{legend:{position:'bottom'}} }
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
