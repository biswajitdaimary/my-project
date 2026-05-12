<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

$pageTitle = 'Booking Management';
$success = ''; $error = '';

// ── Handle Admin Actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['action'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed.';
    } else {
        $b_id   = (int)$_POST['booking_id'];
        $action = $_POST['action'];
        $allowed = ['confirm' => 'confirmed', 'cancel' => 'cancelled', 'complete' => 'completed'];

        if (isset($allowed[$action])) {
            $newStatus = $allowed[$action];
            try {
                $pdo->beginTransaction();
                $bStmt = $pdo->prepare("SELECT tb.*, u.user_id AS uid FROM trainer_bookings tb JOIN users u ON u.user_id = tb.user_id WHERE tb.booking_id = ?");
                $bStmt->execute([$b_id]);
                $bRow = $bStmt->fetch();

                if ($bRow) {
                    $pdo->prepare("UPDATE trainer_bookings SET status = ? WHERE booking_id = ?")
                        ->execute([$newStatus, $b_id]);

                    if ($newStatus === 'cancelled' && !empty($bRow['slot_id'])) {
                        $pdo->prepare("UPDATE availability_slots SET status = 'available' WHERE id = ?")
                            ->execute([$bRow['slot_id']]);
                    }

                    // Notify user based on status
                    if ($newStatus === 'cancelled') {
                        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,'warning')")
                            ->execute([$bRow['uid'], '❌ Session Cancelled', 'Your session was cancelled by the administrator.']);
                    } elseif ($newStatus === 'confirmed') {
                        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,'success')")
                            ->execute([$bRow['uid'], '✅ Session Confirmed', 'Your trainer session has been confirmed by the admin.']);
                    }

                    $pdo->commit();
                    $success = "Booking #$b_id marked as $newStatus.";
                } else {
                    $pdo->rollBack();
                    $error = 'Booking not found.';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Failed to update booking.';
            }
        }
    }
}

// ── Filters ─────────────────────────────────────────────────────────────────
$filterStatus  = $_GET['status']  ?? 'all';
$filterTrainer = (int)($_GET['trainer_id'] ?? 0);
$dateFrom      = $_GET['from']    ?? '';
$dateTo        = $_GET['to']      ?? '';

// ── Load Trainers for filter ────────────────────────────────────────────────
try {
    $trainerList = $pdo->query("SELECT trainer_id, full_name FROM trainers WHERE is_active = 1 ORDER BY full_name ASC")->fetchAll();
} catch (PDOException $e) { $trainerList = []; }

// ── Main Query ───────────────────────────────────────────────────────────────
$where = ['1=1'];
$params = [];
if ($filterStatus !== 'all') { $where[] = 'tb.status = ?'; $params[] = $filterStatus; }
if ($filterTrainer > 0)       { $where[] = 'tb.trainer_id = ?'; $params[] = $filterTrainer; }
if ($dateFrom)                { $where[] = 'tb.session_date >= ?'; $params[] = $dateFrom; }
if ($dateTo)                  { $where[] = 'tb.session_date <= ?'; $params[] = $dateTo; }

try {
    $bookings = $pdo->prepare("
        SELECT tb.*,
               u.full_name   AS client_name, u.email AS client_email,
               t.full_name   AS trainer_name, t.specialization
        FROM trainer_bookings tb
        JOIN users    u ON u.user_id    = tb.user_id
        JOIN trainers t ON t.trainer_id = tb.trainer_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY tb.session_date DESC, tb.start_time ASC
    ");
    $bookings->execute($params);
    $bookings = $bookings->fetchAll();
} catch (PDOException $e) { $bookings = []; }

// ── Analytics ─────────────────────────────────────────────────────────────
try {
    $stats = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'pending')   AS pending,
            SUM(status = 'confirmed') AS confirmed,
            SUM(status = 'completed') AS completed,
            SUM(status = 'cancelled') AS cancelled,
            SUM(CASE WHEN session_date = CURDATE() AND status IN ('confirmed','pending') THEN 1 ELSE 0 END) AS today
        FROM trainer_bookings
    ")->fetch();
} catch (PDOException $e) { $stats = array_fill_keys(['total','pending','confirmed','completed','cancelled','today'], 0); }

// Revenue estimate (completed sessions × trainer hourly rate)
try {
    $revenue = $pdo->query("
        SELECT COALESCE(SUM(t.hourly_rate), 0) AS est_revenue
        FROM trainer_bookings tb
        JOIN trainers t ON t.trainer_id = tb.trainer_id
        WHERE tb.status = 'completed'
    ")->fetchColumn();
} catch (PDOException $e) { $revenue = 0; }

require_once 'includes/admin_header.php';
?>

<style>
.stat-card{background:#fff;border-radius:1rem;padding:1.25rem 1.5rem;border:1px solid #f0f2f7;box-shadow:0 2px 12px rgba(0,0,0,.04);display:flex;align-items:center;gap:1rem}
.stat-icon{width:52px;height:52px;border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.stat-val{font-size:1.75rem;font-weight:800;line-height:1;color:#1a1a2e}
.stat-lbl{font-size:.75rem;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.booking-row:hover{background:#f9fafb}
.status-badge{display:inline-block;padding:.3rem .85rem;border-radius:100px;font-size:.75rem;font-weight:700}
.filter-bar{background:#fff;border-radius:1rem;padding:1rem 1.25rem;border:1px solid #f0f2f7;box-shadow:0 2px 8px rgba(0,0,0,.03);margin-bottom:1.25rem}
</style>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold m-0" style="color:#1a1a2e;">Booking Management</h3>
        <p class="text-muted small mb-0 mt-1">View and manage all trainer session bookings across the platform.</p>
    </div>
    <a href="trainer_schedule.php" class="btn btn-outline-secondary rounded-pill btn-sm px-3">
        <i class="fa-solid fa-calendar-week me-1"></i>Trainer Availability
    </a>
</div>

<?php if ($error): ?><div class="alert alert-danger rounded-4 border-0 mb-3"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success rounded-4 border-0 mb-3"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(255,107,53,.1);color:#FF6B35;"><i class="fa-solid fa-calendar-check"></i></div>
            <div><div class="stat-val"><?= (int)$stats['total'] ?></div><div class="stat-lbl">Total</div></div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1);color:#d97706;"><i class="fa-solid fa-hourglass-half"></i></div>
            <div><div class="stat-val text-warning"><?= (int)$stats['pending'] ?></div><div class="stat-lbl">Pending</div></div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.1);color:#16a34a;"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="stat-val text-success"><?= (int)$stats['confirmed'] ?></div><div class="stat-lbl">Confirmed</div></div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(99,102,241,.1);color:#6366f1;"><i class="fa-solid fa-flag-checkered"></i></div>
            <div><div class="stat-val text-primary"><?= (int)$stats['completed'] ?></div><div class="stat-lbl">Completed</div></div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(255,107,53,.1);color:#FF6B35;"><i class="fa-solid fa-sun"></i></div>
            <div><div class="stat-val" style="color:#FF6B35"><?= (int)$stats['today'] ?></div><div class="stat-lbl">Today</div></div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1);color:#059669;"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div><div class="stat-val text-success" style="font-size:1.4rem;">₹<?= number_format((float)$revenue, 0) ?></div><div class="stat-lbl">Est. Revenue</div></div>
        </div>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label fw-bold small text-muted mb-1">Status</label>
            <select name="status" class="form-select form-select-sm rounded-3">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <?php foreach (['pending','confirmed','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold small text-muted mb-1">Trainer</label>
            <select name="trainer_id" class="form-select form-select-sm rounded-3">
                <option value="0">All Trainers</option>
                <?php foreach ($trainerList as $tr): ?>
                <option value="<?= $tr['trainer_id'] ?>" <?= $filterTrainer === (int)$tr['trainer_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tr['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-bold small text-muted mb-1">From Date</label>
            <input type="date" name="from" class="form-control form-control-sm rounded-3" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-bold small text-muted mb-1">To Date</label>
            <input type="date" name="to" class="form-control form-control-sm rounded-3" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm rounded-3 fw-bold px-3" style="background:#FF6B35;color:#fff;">
                <i class="fa-solid fa-filter me-1"></i>Filter
            </button>
            <a href="bookings.php" class="btn btn-sm btn-outline-secondary rounded-3 px-3">Reset</a>
        </div>
    </div>
</form>

<!-- Bookings Table -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <?php if (empty($bookings)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fa-regular fa-calendar-xmark fa-3x mb-3 d-block" style="color:#e5e7eb;"></i>
            <h5 class="fw-bold text-dark">No Bookings Found</h5>
            <p class="small">Try adjusting your filters or check back later.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.88rem;">
                <thead>
                    <tr class="border-bottom" style="background:#fafafa;">
                        <th class="ps-4 py-3 fw-bold text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;">#</th>
                        <th class="py-3 fw-bold text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;">Client</th>
                        <th class="py-3 fw-bold text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;">Trainer</th>
                        <th class="py-3 fw-bold text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;">Date & Time</th>
                        <th class="py-3 fw-bold text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;">Status</th>
                        <th class="py-3 fw-bold text-muted pe-4" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $b):
                    $today = date('Y-m-d');
                    $statusCfg = match($b['status']) {
                        'confirmed'  => ['#22c55e','#f0fdf4','Confirmed'],
                        'pending'    => ['#f59e0b','#fffbeb','Pending'],
                        'cancelled'  => ['#ef4444','#fef2f2','Cancelled'],
                        'completed'  => ['#6366f1','#f5f3ff','Completed'],
                        default      => ['#9ca3af','#f9fafb', ucfirst($b['status'])],
                    };
                ?>
                <tr class="booking-row" style="border-bottom:1px solid #f0f2f7;">
                    <td class="ps-4 py-3 text-muted fw-bold">#<?= $b['booking_id'] ?></td>
                    <td class="py-3">
                        <div class="fw-bold text-dark"><?= htmlspecialchars($b['client_name']) ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($b['client_email']) ?></div>
                    </td>
                    <td class="py-3">
                        <div class="fw-bold text-dark"><?= htmlspecialchars($b['trainer_name']) ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($b['specialization']) ?></div>
                    </td>
                    <td class="py-3">
                        <div class="fw-bold" style="color:#1a1a2e;">
                            <?= date('D, M j Y', strtotime($b['session_date'])) ?>
                            <?php if ($b['session_date'] === $today): ?>
                                <span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size:.6rem;">TODAY</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted" style="font-size:.78rem;">
                            <i class="fa-regular fa-clock me-1"></i>
                            <?= date('h:i A', strtotime($b['start_time'])) ?> – <?= date('h:i A', strtotime($b['end_time'])) ?>
                        </div>
                    </td>
                    <td class="py-3">
                        <span class="status-badge" style="background:<?= $statusCfg[1] ?>;color:<?= $statusCfg[0] ?>;">
                            <?= $statusCfg[2] ?>
                        </span>
                    </td>
                    <td class="py-3 pe-4">
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if ($b['status'] === 'pending'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button class="btn btn-sm btn-success rounded-3 px-2" title="Confirm"><i class="fa-solid fa-check"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php if (in_array($b['status'], ['pending','confirmed'])): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this booking?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button class="btn btn-sm btn-outline-danger rounded-3 px-2" title="Cancel"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($b['status'] === 'confirmed'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                <input type="hidden" name="action" value="complete">
                                <button class="btn btn-sm btn-primary rounded-3 px-2" title="Mark Complete"><i class="fa-solid fa-flag-checkered"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php if (!empty($b['notes'])): ?>
                            <button class="btn btn-sm btn-outline-secondary rounded-3 px-2" title="<?= htmlspecialchars($b['notes']) ?>">
                                <i class="fa-solid fa-note-sticky"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 text-muted" style="font-size:.8rem;border-top:1px solid #f0f2f7;">
            Showing <?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?>
            <span id="rtLastUpdated" style="float:right;color:#94a3b8;"></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ── Real-Time Admin Sync ─────────────────────────────────────────────────────
// Polls every 30 seconds. If new pending bookings arrive or counts change,
// shows a live notification bar without requiring a page reload.
let adminPollTimer = null;
let lastPendingCount = <?= (int)($pendingCount ?? 0) ?>;
let lastTotalCount   = <?= (int)(count($bookings ?? [])) ?>;

function startAdminPolling() {
    adminPollTimer = setInterval(pollAdminStats, 30000);
}

function pollAdminStats() {
    fetch('<?= SITE_URL ?>/api/realtime-stats.php?mode=admin')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const d = data.data;

            // Update pending badge in sidebar / topbar if it exists
            document.querySelectorAll('[data-rt-pending]').forEach(el => {
                el.textContent = d.pending;
                el.style.display = d.pending > 0 ? 'inline-flex' : 'none';
            });

            // New bookings arrived since page load?
            if (d.total > lastTotalCount) {
                const diff = d.total - lastTotalCount;
                showRtBanner(`${diff} new booking${diff > 1 ? 's' : ''} received — <a href="" style="color:#fff;font-weight:800;text-decoration:underline;">Reload to view</a>`);
                lastTotalCount = d.total;
            }

            // New pending bookings need action?
            if (d.pending > lastPendingCount) {
                showRtBanner(`${d.pending} booking${d.pending > 1 ? 's' : ''} awaiting confirmation`);
                lastPendingCount = d.pending;
            }

            // Update last-updated timestamp
            const ts = document.getElementById('rtLastUpdated');
            if (ts) {
                const now = new Date();
                ts.textContent = `Live · ${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}:${now.getSeconds().toString().padStart(2,'0')}`;
            }
        })
        .catch(() => {});
}

function showRtBanner(msg) {
    // Remove existing banner
    document.getElementById('rtBanner')?.remove();
    const banner = document.createElement('div');
    banner.id = 'rtBanner';
    banner.style.cssText = `
        position:fixed;top:0;left:0;right:0;z-index:9999;
        background:linear-gradient(90deg,#FF6B35,#f59e0b);
        color:#fff;padding:.65rem 1.5rem;
        display:flex;align-items:center;justify-content:space-between;
        font-size:.85rem;font-weight:600;
        box-shadow:0 4px 20px rgba(255,107,53,.4);
        animation:slideDown .3s ease;
    `;
    banner.innerHTML = `
        <span><i class="fa-solid fa-bell me-2"></i>${msg}</span>
        <button onclick="this.parentElement.remove()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:24px;height:24px;border-radius:50%;cursor:pointer;font-size:.9rem;">✕</button>
    `;
    document.body.prepend(banner);
    setTimeout(() => banner?.remove(), 8000);
}

// Inject keyframe if not already present
if (!document.getElementById('rtStyles')) {
    const s = document.createElement('style');
    s.id = 'rtStyles';
    s.textContent = '@keyframes slideDown{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}';
    document.head.appendChild(s);
}

document.addEventListener('DOMContentLoaded', startAdminPolling);
</script>

<?php require_once 'includes/admin_footer.php'; ?>
