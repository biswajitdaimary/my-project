<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_trainer();

$pageTitle = 'Dashboard';
$trainer_id = $_SESSION['user_id'];

// ── Holiday check (server-side for reliable popup) ─────────────
$todayHoliday = null;
$upcomingHoliday = null;
try {
    $hStmt = $pdo->prepare("
        SELECT * FROM holidays
        WHERE holiday_date >= CURDATE()
        AND holiday_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        ORDER BY holiday_date ASC
    ");
    $hStmt->execute();
    foreach ($hStmt->fetchAll() as $h) {
        $applies = $h['target_type'] === 'all' ||
                   in_array($trainer_id, json_decode($h['trainer_ids'] ?? '[]', true) ?: []);
        if (!$applies) continue;
        $h['formatted_date'] = date('M d, Y', strtotime($h['holiday_date']));
        if ($h['holiday_date'] === date('Y-m-d')) {
            $todayHoliday = $h;
        } elseif (!$upcomingHoliday) {
            $upcomingHoliday = $h;
        }
    }
} catch (Exception $e) {}

// Fetch Stats
try {
    // 1. Total Upcoming Sessions
    $stmt1 = $pdo->prepare("
        SELECT COUNT(*)
        FROM trainer_bookings
        WHERE trainer_id = ?
          AND status IN ('pending', 'confirmed')
          AND session_date > CURDATE()
    ");
    $stmt1->execute([$trainer_id]);
    $upcomingSessions = $stmt1->fetchColumn();

    // 2. Today's Sessions
    $stmt2 = $pdo->prepare("
        SELECT COUNT(*)
        FROM trainer_bookings
        WHERE trainer_id = ?
          AND session_date = CURDATE()
          AND status IN ('pending', 'confirmed')
    ");
    $stmt2->execute([$trainer_id]);
    $todaysSessions = $stmt2->fetchColumn();

    // 3. Unique Clients
    $stmt3 = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM trainer_bookings WHERE trainer_id = ?");
    $stmt3->execute([$trainer_id]);
    $totalClients = $stmt3->fetchColumn();

    // Fetch Today's Schedule
    $stmtSchedule = $pdo->prepare("
        SELECT tb.*, u.full_name AS client_name, u.profile_photo AS client_photo
        FROM trainer_bookings tb
        JOIN users u ON tb.user_id = u.user_id
        WHERE tb.trainer_id = ?
          AND tb.session_date = CURDATE()
          AND tb.status IN ('pending', 'confirmed')
        ORDER BY tb.start_time ASC
    ");
    $stmtSchedule->execute([$trainer_id]);
    $todaySchedule = $stmtSchedule->fetchAll();

} catch (PDOException $e) {
    $upcomingSessions = $todaysSessions = $totalClients = 0;
    $todaySchedule = [];
}

require_once 'includes/trainer_header.php';
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
    box-shadow: 0 4px 18px rgba(192, 38, 211, 0.45);
    border-radius: 12px;
    margin-bottom: 1.25rem;
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
            🚫 No Sessions Today
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?>! 👋</h3>
        <p class="text-muted mb-0">Here is what's happening with your clients today.</p>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white;">
            <div class="card-body p-4 position-relative">
                <i class="fa-solid fa-calendar-day position-absolute" style="font-size: 5rem; right: -10px; bottom: -10px; opacity: 0.15;"></i>
                <h6 class="text-white-50 fw-bold text-uppercase mb-2">Today's Sessions</h6>
                <h2 class="display-5 fw-bold mb-0"><?= $todaysSessions ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="text-muted fw-bold text-uppercase mb-0">Upcoming Bookings</h6>
                    <div class="icon-circle bg-primary-subtle text-primary"><i class="fa-solid fa-clock"></i></div>
                </div>
                <h2 class="display-6 fw-bold mb-0"><?= $upcomingSessions ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="text-muted fw-bold text-uppercase mb-0">Total Unique Clients</h6>
                    <div class="icon-circle bg-success-subtle text-success"><i class="fa-solid fa-users"></i></div>
                </div>
                <h2 class="display-6 fw-bold mb-0"><?= $totalClients ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Today's Agenda -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold m-0"><i class="fa-solid fa-clipboard-list text-primary me-2"></i> Today's Agenda</h5>
        <span class="badge bg-primary rounded-pill px-3 py-2"><?= date('F j, Y') ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($todaySchedule)): ?>
            <div class="text-center py-5">
                <i class="fa-regular fa-calendar-xmark fs-1 text-muted opacity-50 mb-3"></i>
                <h5 class="fw-bold text-muted">No sessions scheduled for today.</h5>
                <p class="text-muted">Enjoy your free time!</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($todaySchedule as $session): 
                    $statusColors = [
                        'pending' => 'warning',
                        'confirmed' => 'primary',
                        'completed' => 'success',
                    ];
                    $color = $statusColors[$session['status']] ?? 'secondary';
                ?>
                <div class="list-group-item p-4 d-flex align-items-center gap-4">
                    <div class="text-center border-end pe-4" style="min-width: 120px;">
                        <h5 class="fw-bold mb-0 text-dark"><?= date('h:i A', strtotime($session['start_time'])) ?></h5>
                        <small class="text-muted fw-semibold">to <?= date('h:i A', strtotime($session['end_time'])) ?></small>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="avatar-circle">
                            <?php if(!empty($session['client_photo'])): ?>
                                <img src="<?= SITE_URL ?>/<?= htmlspecialchars($session['client_photo']) ?>" alt="Photo" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                            <?php else: ?>
                                <?= strtoupper(substr($session['client_name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($session['client_name']) ?></h6>
                            <span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> text-uppercase" style="font-size:0.65rem; letter-spacing:0.5px;">
                                <?= htmlspecialchars($session['status']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div>
                        <a href="bookings.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Manage</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.icon-circle { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
.avatar-circle { width:48px; height:48px; border-radius:50%; background:rgba(14,165,233,0.15); color:#0ea5e9; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.1rem;}
</style>

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
          <i class="fa-solid fa-ban me-2"></i>No sessions or bookings are allowed today.
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

<?php require_once 'includes/trainer_footer.php'; ?>
