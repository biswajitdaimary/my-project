<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/notification_helper.php';
require_trainer();

$pageTitle  = 'My Sessions';
$trainer_id = $_SESSION['user_id'];
$success = ''; $error = '';

// Mark all trainer notifications as read when viewing this page
try {
    $pdo->prepare("UPDATE trainer_notifications SET is_read = 1 WHERE trainer_id = ? AND is_read = 0")
        ->execute([$trainer_id]);
} catch (Exception $e) { /* non-critical */ }

// ── Handle Status Updates ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['status_action'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed.';
    } else {
        $b_id   = (int)$_POST['booking_id'];
        $action = $_POST['status_action'];

        $allowed = ['confirm' => 'confirmed', 'complete' => 'completed', 'cancel' => 'cancelled'];
        if (isset($allowed[$action])) {
            $newStatus = $allowed[$action];
            try {
                $pdo->beginTransaction();

                // Fetch booking details first
                $bStmt = $pdo->prepare("
                    SELECT tb.*, u.full_name AS client_name, u.user_id AS client_user_id
                    FROM trainer_bookings tb
                    JOIN users u ON u.user_id = tb.user_id
                    WHERE tb.booking_id = ? AND tb.trainer_id = ?
                ");
                $bStmt->execute([$b_id, $trainer_id]);
                $bRow = $bStmt->fetch();

                if ($bRow) {
                    $pdo->prepare("UPDATE trainer_bookings SET status = ? WHERE booking_id = ? AND trainer_id = ?")
                        ->execute([$newStatus, $b_id, $trainer_id]);

                    if ($newStatus === 'cancelled' && !empty($bRow['slot_id'])) {
                        $pdo->prepare("UPDATE availability_slots SET status = 'available' WHERE id = ?")
                            ->execute([$bRow['slot_id']]);
                    }

                    $dateStr = date('D, M j Y', strtotime($bRow['session_date']));
                    $timeStr = date('h:i A', strtotime($bRow['start_time']));
                    $trainerName = $_SESSION['full_name'];

                    // Notify the client based on action
                    $msgs = [
                        'confirmed'  => ["✅ Session Confirmed", "Your session with $trainerName on $dateStr at $timeStr has been confirmed!", 'success'],
                        'cancelled'  => ["❌ Session Cancelled", "Your session with $trainerName on $dateStr at $timeStr was cancelled by the trainer.", 'warning'],
                        'completed'  => ["🎉 Session Completed", "Your session with $trainerName on $dateStr at $timeStr is marked complete. Don't forget to rate your trainer!", 'success'],
                    ];
                    [$nTitle, $nMsg, $nType] = $msgs[$newStatus];

                    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)")
                        ->execute([$bRow['client_user_id'], $nTitle, $nMsg, $nType]);

                    // ✅ FIX Bug 3: Create calendar_reminders on confirm (mirrors api/calendar.php logic)
                    if ($newStatus === 'confirmed') {
                        $remDate = date('Y-m-d H:i:s', strtotime($bRow['session_date'] . ' ' . $bRow['start_time'] . ' -1 hour'));
                        try {
                            $pdo->prepare("INSERT INTO calendar_reminders (user_id, title, reminder_date) VALUES (?,?,?)")
                                ->execute([$bRow['client_user_id'], 'Session Reminder: Upcoming workout today', $remDate]);
                            $pdo->prepare("INSERT INTO calendar_reminders (trainer_id, title, reminder_date) VALUES (?,?,?)")
                                ->execute([$trainer_id, 'Session Reminder: Upcoming client session', $remDate]);
                        } catch (Exception $e) { /* non-critical, reminders already notify */ }
                    }

                    $pdo->commit();
                    $success = "Session marked as $newStatus.";
                } else {
                    $pdo->rollBack();
                    $error = 'Booking not found.';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Failed to update session.';
            }
        }
    }
}

// ── Fetch All Bookings for this Trainer ──────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT tb.*,
               u.full_name AS client_name, u.email AS client_email,
               u.phone AS client_phone, u.profile_photo AS client_photo
        FROM trainer_bookings tb
        JOIN users u ON tb.user_id = u.user_id
        WHERE tb.trainer_id = ?
        ORDER BY tb.session_date DESC, tb.start_time ASC
    ");
    $stmt->execute([$trainer_id]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) { $bookings = []; }

// ── Stats ─────────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$todaySessions  = array_filter($bookings, fn($b) => $b['session_date'] === $today && in_array($b['status'], ['confirmed','pending']));
$upcomingSessions = array_filter($bookings, fn($b) => $b['session_date'] > $today && in_array($b['status'], ['confirmed','pending']));
$pendingCount   = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));

require_once 'includes/trainer_header.php';
?>

<style>
.session-card{background:#fff;border-radius:1rem;border:1px solid #f0f2f7;padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;transition:all .2s;margin-bottom:.75rem;box-shadow:0 2px 8px rgba(0,0,0,.03)}
.session-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.07);border-color:#e5e7eb}
.sc-photo{width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.1);flex-shrink:0}
.sc-init{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-weight:700;font-size:1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sc-actions{margin-left:auto;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}
.filter-tabs{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem}
.filter-tab{background:#f4f6fb;border:none;color:#6b7280;font-size:.85rem;font-weight:600;padding:.5rem 1.25rem;border-radius:100px;cursor:pointer;transition:all .2s}
.filter-tab:hover{background:#ffe8df;color:#FF6B35}
.filter-tab.active{background:#FF6B35;color:#fff;box-shadow:0 4px 15px rgba(255,107,53,.3)}
.stat-mini{background:#fff;border-radius:1rem;padding:1rem 1.25rem;border:1px solid #f0f2f7;box-shadow:0 2px 8px rgba(0,0,0,.03);text-align:center}
.stat-mini .val{font-size:1.8rem;font-weight:800;line-height:1}
.stat-mini .lbl{font-size:.75rem;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-top:.3rem}
.empty-state{text-align:center;padding:3rem 1rem;color:#9ca3af}
</style>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold m-0" style="color:#1a1a2e;">My Sessions</h3>
        <p class="text-muted small mb-0 mt-1">View and manage all your client bookings.</p>
    </div>
    <?php if ($pendingCount > 0): ?>
    <span class="badge rounded-pill px-3 py-2 fs-6" style="background:rgba(245,158,11,.15);color:#d97706;">
        <i class="fa-solid fa-bell me-1"></i><?= $pendingCount ?> Pending
    </span>
    <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert alert-danger rounded-4 border-0 mb-3"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success rounded-4 border-0 mb-3"><i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-mini"><div class="val text-warning"><?= $pendingCount ?></div><div class="lbl">Pending</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><div class="val" style="color:#FF6B35"><?= count($todaySessions) ?></div><div class="lbl">Today</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><div class="val text-primary"><?= count($upcomingSessions) ?></div><div class="lbl">Upcoming</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><div class="val text-success"><?= count(array_filter($bookings, fn($b) => $b['status'] === 'completed')) ?></div><div class="lbl">Completed</div></div></div>
</div>

<!-- Sessions List -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">All</button>
            <button class="filter-tab" data-filter="pending">Pending</button>
            <button class="filter-tab" data-filter="today">Today</button>
            <button class="filter-tab" data-filter="upcoming">Upcoming</button>
            <button class="filter-tab" data-filter="past">Past / Done</button>
        </div>

        <?php if (empty($bookings)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-calendar-xmark fa-3x mb-3 d-block"></i>
            <h5 class="fw-bold text-dark">No Sessions Yet</h5>
            <p class="small">Client bookings will appear here once they book a session with you.</p>
        </div>
        <?php else: ?>
        <div id="sessionsList">
        <?php foreach ($bookings as $b):
            $isFuture = strtotime($b['session_date'] . ' ' . $b['start_time']) > time();
            $isToday  = $b['session_date'] === $today;
            $filter   = 'past';
            if ($b['status'] === 'pending') $filter = 'pending';
            elseif ($isToday && in_array($b['status'], ['confirmed','pending'])) $filter = 'today';
            elseif ($isFuture && in_array($b['status'], ['confirmed','pending'])) $filter = 'upcoming';

            $statusBadge = match($b['status']) {
                'confirmed'  => ['bg-success',    'Confirmed'],
                'pending'    => ['bg-warning text-dark', 'Pending'],
                'cancelled'  => ['bg-danger',     'Cancelled'],
                'completed'  => ['bg-primary',    'Completed'],
                default      => ['bg-secondary',  ucfirst($b['status'])],
            };
        ?>
        <div class="session-card" data-filter="<?= $filter ?>" data-status="<?= $b['status'] ?>">
            <!-- Avatar -->
            <?php if (!empty($b['client_photo'])): ?>
                <img src="<?= SITE_URL ?>/<?= htmlspecialchars(ltrim($b['client_photo'],'/')) ?>" class="sc-photo" alt="">
            <?php else: ?>
                <div class="sc-init"><?= strtoupper(substr($b['client_name'],0,1)) ?></div>
            <?php endif; ?>

            <!-- Client Info -->
            <div style="min-width:130px;">
                <div class="fw-bold" style="font-size:.92rem;color:#1a1a2e;"><?= htmlspecialchars($b['client_name']) ?></div>
                <div class="text-muted" style="font-size:.75rem;">
                    <?php if (!empty($b['client_email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($b['client_email']) ?>" style="color:inherit;"><?= htmlspecialchars($b['client_email']) ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Date & Time -->
            <div>
                <div class="fw-bold" style="font-size:.88rem;color:#1a1a2e;">
                    <i class="fa-regular fa-calendar me-1" style="color:#FF6B35;"></i>
                    <?= date('D, M j Y', strtotime($b['session_date'])) ?>
                    <?php if ($isToday): ?><span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size:.65rem;">TODAY</span><?php endif; ?>
                </div>
                <div class="text-muted" style="font-size:.78rem;">
                    <i class="fa-regular fa-clock me-1"></i>
                    <?= date('h:i A', strtotime($b['start_time'])) ?> – <?= date('h:i A', strtotime($b['end_time'])) ?>
                </div>
                <?php if (!empty($b['notes'])): ?>
                <div class="text-muted mt-1" style="font-size:.75rem;font-style:italic;">
                    <i class="fa-solid fa-note-sticky me-1"></i><?= htmlspecialchars(mb_substr($b['notes'],0,60)) ?><?= strlen($b['notes']) > 60 ? '…' : '' ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="sc-actions">
                <span class="badge <?= $statusBadge[0] ?> rounded-pill px-3 py-2"><?= $statusBadge[1] ?></span>

                <?php if ($b['status'] === 'pending'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                    <input type="hidden" name="status_action" value="confirm">
                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-3" title="Accept booking">
                        <i class="fa-solid fa-check me-1"></i>Accept
                    </button>
                </form>
                <form method="POST" class="d-inline" onsubmit="return confirm('Decline this booking?');">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                    <input type="hidden" name="status_action" value="cancel">
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                        <i class="fa-solid fa-xmark me-1"></i>Decline
                    </button>
                </form>
                <?php elseif ($b['status'] === 'confirmed' && (strtotime($b['session_date']) <= strtotime('today'))): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                    <input type="hidden" name="status_action" value="complete">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">
                        <i class="fa-solid fa-flag-checkered me-1"></i>Mark Complete
                    </button>
                </form>
                <?php endif; ?>

                <?php if (!empty($b['client_phone'])): ?>
                <a href="tel:<?= htmlspecialchars($b['client_phone']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill" title="Call client">
                    <i class="fa-solid fa-phone"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.filter-tab').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const f = this.dataset.filter;
        document.querySelectorAll('.session-card').forEach(card => {
            const match = f === 'all' || card.dataset.filter === f;
            card.style.display = match ? 'flex' : 'none';
        });
    });
});
</script>

<?php require_once 'includes/trainer_footer.php'; ?>
