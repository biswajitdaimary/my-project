<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

$pageTitle = 'Admin Dashboard';

try {
    // KPI Stats
    $kpi = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'user') AS total_members,
            (SELECT COUNT(*) FROM user_memberships WHERE status = 'active') AS active_memberships,
            (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'success') AS all_time_revenue,
            (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'success' AND MONTH(payment_date) = MONTH(NOW()) AND YEAR(payment_date) = YEAR(NOW())) AS monthly_revenue,
            (SELECT COUNT(*) FROM contact_messages WHERE is_read = 0) AS unread_messages,
            (SELECT COUNT(*) FROM trainer_bookings) AS total_bookings,
            (SELECT COUNT(*) FROM trainers WHERE is_active = 1) AS active_trainers
    ")->fetch();

    // Revenue last 30 days (for chart)
    $revenueStmt = $pdo->query("
        SELECT DATE(payment_date) as day, SUM(amount) as total
        FROM payments
        WHERE status = 'success' AND payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(payment_date)
        ORDER BY day ASC
    ");
    $revenueData = $revenueStmt->fetchAll();

    // Recent signups
    $recentMembers = $pdo->query("
        SELECT u.user_id, u.full_name, u.email, u.created_at,
               mp.plan_name, um.status AS membership_status
        FROM users u
        LEFT JOIN user_memberships um ON u.user_id = um.user_id AND um.status = 'active'
        LEFT JOIN membership_plans mp ON um.plan_id = mp.plan_id
        WHERE u.role = 'user'
        ORDER BY u.created_at DESC LIMIT 8
    ")->fetchAll();

    // Booking Activity (Recent + Upcoming)
    $upcomingBookings = $pdo->query("
        SELECT tb.*, u.full_name AS client_name, t.full_name AS trainer_name
        FROM trainer_bookings tb
        LEFT JOIN users u ON tb.user_id = u.user_id
        LEFT JOIN trainers t ON tb.trainer_id = t.trainer_id
        WHERE tb.status != 'cancelled'
        ORDER BY tb.session_date DESC, tb.start_time DESC LIMIT 5
    ")->fetchAll();

    // Check for today's holiday
    $holStmt = $pdo->query("SELECT * FROM holidays WHERE holiday_date = CURDATE()");
    $todayHoliday = $holStmt->fetch();

} catch (PDOException $e) {
    $kpi = ['total_members'=>0,'active_memberships'=>0,'monthly_revenue'=>0,'unread_messages'=>0,'bookings_today'=>0,'active_trainers'=>0];
    $revenueData = []; $recentMembers = []; $upcomingBookings = []; $todayHoliday = null;
}

// Prepare Chart.js data
$chartLabels = array_column($revenueData, 'day');
$chartValues = array_column($revenueData, 'total');

require_once 'includes/admin_header.php';
?>

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
            🚫 Admin Override Active
        </span>
    </div>
</div>
<style>
@keyframes bannerPulse {
    0%, 100% { box-shadow: 0 4px 18px rgba(192, 38, 211, 0.45); }
    50%       { box-shadow: 0 4px 30px rgba(192, 38, 211, 0.75); }
}
</style>

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
          <i class="fa-solid fa-circle-info me-2"></i>Gym is closed for regular sessions.
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
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Dashboard</h3>
        <p class="text-muted small mb-0">Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>. Here's what's happening today.</p>
    </div>
    <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-circle-check me-1"></i> <?= date('D, d M Y') ?></span>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['label'=>'Total Members',       'value'=> number_format($kpi['total_members']),     'icon'=>'fa-users',               'color'=>'#4361ee', 'link'=>'members.php'],
        ['label'=>'Active Plans',        'value'=> number_format($kpi['active_memberships']), 'icon'=>'fa-id-card',             'color'=>'#06d6a0', 'link'=>'members.php'],
        ['label'=>'All-Time Revenue',    'value'=>'₹'.number_format($kpi['all_time_revenue']),'icon'=>'fa-sack-dollar',         'color'=>'#FF6B35', 'link'=>'reports.php'],
        ['label'=>'Total Bookings',      'value'=> number_format($kpi['total_bookings']),     'icon'=>'fa-calendar-check',      'color'=>'#7209b7', 'link'=>'bookings.php'],
        ['label'=>'Active Trainers',     'value'=> number_format($kpi['active_trainers']),    'icon'=>'fa-person-running',      'color'=>'#f72585', 'link'=>'trainers.php'],
        ['label'=>'Unread Messages',     'value'=> number_format($kpi['unread_messages']),    'icon'=>'fa-envelope',            'color'=>'#e63946', 'link'=>'contacts.php'],
    ];
    foreach ($kpis as $k): ?>
    <div class="col-lg-2 col-md-4 col-6">
        <a href="<?= $k['link'] ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 h-100 kpi-card">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1"><?= $k['label'] ?></p>
                            <h4 class="fw-bold mb-0"><?= $k['value'] ?></h4>
                        </div>
                        <div class="kpi-icon" style="background: <?= $k['color'] ?>22; color: <?= $k['color'] ?>;">
                            <i class="fa-solid <?= $k['icon'] ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Revenue Chart + Upcoming Bookings -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Revenue — Last 30 Days</h5>
                    <a href="reports.php" class="btn btn-sm btn-outline-secondary rounded-pill">Full Report</a>
                </div>
                <canvas id="revenueChart" height="90"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Booking Activity</h5>
                <?php if(empty($upcomingBookings)): ?>
                    <div class="text-center text-muted py-4"><i class="fa-regular fa-calendar-xmark fa-2x mb-2 d-block opacity-25"></i>No booking activity</div>
                <?php else: ?>
                    <?php foreach($upcomingBookings as $b): ?>
                    <div class="d-flex align-items-start gap-3 mb-3 pb-3 border-bottom">
                        <div class="kpi-icon" style="background: rgba(255,107,53,0.12); color: #FF6B35; flex-shrink:0;">
                            <i class="fa-solid fa-dumbbell"></i>
                        </div>
                        <div>
                            <div class="fw-bold small"><?= htmlspecialchars($b['client_name']) ?></div>
                            <div class="text-muted small">with <?= htmlspecialchars($b['trainer_name']) ?></div>
                            <div class="text-muted small"><?= date('M d', strtotime($b['session_date'])) ?> • <?= date('h:i A', strtotime($b['start_time'])) ?></div>
                        </div>
                        <span class="badge ms-auto <?= $b['status']==='confirmed'?'bg-success':'bg-warning text-dark' ?>"><?= ucfirst($b['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Member Signups -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Recent Member Signups</h5>
            <a href="members.php" class="btn btn-sm btn-outline-secondary rounded-pill">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Member</th><th>Email</th><th>Plan</th><th>Status</th><th>Joined</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($recentMembers)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No members yet</td></tr>
                    <?php else: foreach($recentMembers as $m): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm"><?= strtoupper(substr($m['full_name'],0,1)) ?></div>
                                <span class="fw-bold"><?= htmlspecialchars($m['full_name']) ?></span>
                            </div>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($m['email']) ?></td>
                        <td><?= $m['plan_name'] ? '<span class="badge bg-primary-subtle text-primary">'.htmlspecialchars($m['plan_name']).'</span>' : '<span class="text-muted small">None</span>' ?></td>
                        <td><?php
                            $s = $m['membership_status'] ?? 'inactive';
                            $cls = $s === 'active' ? 'bg-success' : 'bg-secondary';
                            echo "<span class='badge $cls'>".ucfirst($s)."</span>";
                        ?></td>
                        <td class="text-muted small"><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.kpi-card { transition: transform 0.22s ease, box-shadow 0.22s ease; }
.kpi-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.1) !important; }
.kpi-icon { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.avatar-sm { width:32px; height:32px; border-radius:50%; background: rgba(255,107,53,0.15); color:#FF6B35; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.85rem; flex-shrink:0; }
</style>

<script>
const ctx = document.getElementById('revenueChart');
if(ctx) {
    const labels = <?= json_encode($chartLabels) ?>;
    const values = <?= json_encode(array_map('floatval', $chartValues)) ?>;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.map(d => new Date(d).toLocaleDateString('en-IN',{month:'short',day:'numeric'})),
            datasets: [{
                label: 'Revenue (₹)',
                data: values,
                borderColor: '#FF6B35',
                backgroundColor: 'rgba(255,107,53,0.08)',
                borderWidth: 2.5,
                pointBackgroundColor: '#FF6B35',
                pointRadius: 4,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { callback: v => '₹' + v.toLocaleString('en-IN') } },
                x: { grid: { display: false } }
            }
        }
    });
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
