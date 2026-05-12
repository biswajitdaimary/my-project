<?php
$pageTitle = 'My Bookings';
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/notification_helper.php';
require_user();
$user_id = $_SESSION['user_id'];
$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_booking_id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid security token.';
    } else {
        $booking_id = $_POST['cancel_booking_id'];
        try {
            $pdo->beginTransaction();
            $cStmt = $pdo->prepare("SELECT b.*, t.full_name AS trainer_name FROM trainer_bookings b JOIN trainers t ON t.trainer_id = b.trainer_id WHERE b.booking_id = ? AND b.user_id = ? AND b.status IN ('pending', 'confirmed') AND b.session_date >= CURRENT_DATE");
            $cStmt->execute([$booking_id, $user_id]);
            $bToCancel = $cStmt->fetch();
            if ($bToCancel) {
                $pdo->prepare("UPDATE trainer_bookings SET status = 'cancelled' WHERE booking_id = ?")->execute([$booking_id]);
                if (!empty($bToCancel['slot_id'])) {
                    $pdo->prepare("UPDATE availability_slots SET status = 'available' WHERE id = ?")->execute([$bToCancel['slot_id']]);
                }
                create_notification($pdo, $user_id, 'Session cancelled', 'Your session with ' . $bToCancel['trainer_name'] . ' was cancelled successfully.', 'warning');
                $pdo->commit();
                $success = "Booking cancelled successfully.";
            } else {
                $pdo->rollBack(); $error = "Cannot cancel this booking. It may have already been cancelled or occurred in the past.";
            }
        } catch(Exception $e) { $pdo->rollBack(); $error = "Error cancelling booking."; }
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT b.*, t.full_name as trainer_name, t.specialization, t.photo 
        FROM trainer_bookings b 
        JOIN trainers t ON b.trainer_id = t.trainer_id 
        WHERE b.user_id = ? 
        ORDER BY b.session_date DESC, b.start_time DESC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) { $bookings = []; }

require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<style>
/* ── Page Layout & Typography ───────────────────────── */
.up-wrap { background-color: #f4f6fb; min-height: 100vh; }
.page-title-box {
    background: #fff;
    padding: 1.5rem 2rem;
    border-radius: 1rem;
    box-shadow: 0 4px 15px rgba(0,0,0,.03);
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.page-title-box h4 { margin: 0; font-weight: 800; color: #1a1a2e; }

/* ── Tabs Navigation ──────────────────────────────────── */
.booking-tabs {
    display: flex; gap: .75rem; flex-wrap: wrap; margin-bottom: 2rem;
    border-bottom: 2px solid #eef0f7; padding-bottom: 1rem;
}
.booking-tab {
    background: transparent; border: none; color: #9ca3af;
    font-size: .95rem; font-weight: 700; padding: .6rem 1.25rem;
    border-radius: 100px; cursor: pointer; transition: all .2s;
}
.booking-tab:hover { background: #f8f9fc; color: #1a1a2e; }
.booking-tab.active { background: #1a1a2e; color: #fff; box-shadow: 0 4px 12px rgba(26,26,46,.2); }

/* ── Booking Cards ────────────────────────────────────── */
.booking-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.25rem; }
.booking-card {
    background: #fff; border-radius: 1.25rem; border: 1px solid #eef0f7;
    padding: 1.5rem; transition: all .25s cubic-bezier(.4,0,.2,1);
    box-shadow: 0 4px 15px rgba(0,0,0,.02);
    display: flex; flex-direction: column;
}
.booking-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,.06); border-color: #d1d5db; }
.bc-header { display: flex; align-items: flex-start; gap: 1.1rem; margin-bottom: 1.25rem; }
.bc-photo { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #f4f6fb; flex-shrink: 0; }
.bc-initials { width: 56px; height: 56px; border-radius: 50%; background: rgba(99,102,241,.1); color: #6366f1; font-weight: 800; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.bc-trainer-info { flex-grow: 1; min-width: 0; }
.bc-trainer-name { font-weight: 800; font-size: 1.05rem; color: #1a1a2e; margin-bottom: .2rem; }
.bc-trainer-spec { font-size: .8rem; color: #6b7280; font-weight: 500; }

.bc-status-badge {
    padding: .35rem .75rem; border-radius: 100px; font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em;
}
.status-pending { background: rgba(245,158,11,.1); color: #d97706; }
.status-confirmed { background: rgba(34,197,94,.1); color: #16a34a; }
.status-completed { background: rgba(99,102,241,.1); color: #4f46e5; }
.status-cancelled { background: rgba(239,68,68,.1); color: #dc2626; }

.bc-details { background: #f8f9fc; border-radius: .85rem; padding: 1rem 1.1rem; margin-bottom: 1.25rem; flex-grow: 1; }
.bc-detail-row { display: flex; align-items: center; gap: .75rem; font-size: .85rem; color: #374151; margin-bottom: .5rem; }
.bc-detail-row:last-child { margin-bottom: 0; }
.bc-detail-icon { width: 24px; color: #9ca3af; text-align: center; font-size: 1rem; }
.bc-notes-area {
    margin-top: .75rem; padding-top: .75rem; border-top: 1px dashed #d1d5db;
    font-size: .8rem; color: #6b7280; line-height: 1.4;
}
.bc-notes-label { font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #9ca3af; margin-bottom: .25rem; }

.bc-actions { display: flex; gap: .75rem; margin-top: auto; }
.bc-btn { flex: 1; padding: .65rem; border-radius: .75rem; font-size: .85rem; font-weight: 700; text-align: center; cursor: pointer; transition: all .2s; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: .5rem; }
.bc-btn-primary { background: #FF6B35; color: #fff; }
.bc-btn-primary:hover { background: #e85d2b; color: #fff; transform: translateY(-1px); }
.bc-btn-outline { background: transparent; border: 2px solid #eef0f7; color: #4b5563; }
.bc-btn-outline:hover { background: #f4f6fb; border-color: #d1d5db; color: #1a1a2e; }

/* ── Empty State ──────────────────────────────────────── */
.empty-state { text-align: center; padding: 5rem 1rem; background: #fff; border-radius: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,.02); border: 1px dashed #d1d5db; }
.empty-icon { width: 100px; height: 100px; border-radius: 50%; background: rgba(99,102,241,.08); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem; color: #6366f1; }

/* ── Modal ────────────────────────────────────────────── */
.cancel-modal { display: none; position: fixed; inset: 0; background: rgba(26,26,46,.75); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.cancel-modal.show { display: flex; animation: fadeIn .2s ease; }
.cancel-modal-content { background: #fff; border-radius: 1.5rem; padding: 2.5rem; width: 90%; max-width: 420px; text-align: center; animation: popIn .3s cubic-bezier(.34,1.56,.64,1); }
.cancel-icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(239,68,68,.1); color: #dc2626; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; }

@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@keyframes popIn { from{transform:scale(.9);opacity:0} to{transform:scale(1);opacity:1} }
@media(max-width:576px) { .booking-grid { grid-template-columns: 1fr; } }
</style>

<div class="up-wrap">
    <div class="container-fluid px-0">
        <div class="d-flex">
            <?php require_once '../includes/sidebar-user.php'; ?>
            <main class="up-main flex-grow-1 p-4">
                
                <div class="page-title-box">
                    <div>
                        <h4>My Bookings</h4>
                        <p class="text-muted mb-0" style="font-size:.9rem;">Manage and track your personal training sessions</p>
                    </div>
                    <a href="book-trainer.php" class="bc-btn bc-btn-primary px-4 py-2 rounded-pill" style="flex:none;">
                        <i class="fa-solid fa-plus"></i> Book a Session
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger rounded-3 mb-4 border-0 shadow-sm" style="background:#fee2e2;color:#991b1b;"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success rounded-3 mb-4 border-0 shadow-sm" style="background:#dcfce3;color:#166534;"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-regular fa-calendar-plus"></i></div>
                        <h4 class="fw-bold mb-2">No Sessions Booked Yet</h4>
                        <p class="text-muted mb-4 mx-auto" style="max-width:400px;">You haven't scheduled any personal training sessions. Book a trainer to reach your fitness goals faster!</p>
                        <a href="book-trainer.php" class="bc-btn bc-btn-primary px-5 py-3 rounded-pill" style="display:inline-flex;width:auto;font-size:1rem;"><i class="fa-solid fa-dumbbell"></i> Explore Trainers</a>
                    </div>
                <?php else: ?>
                    
                    <div class="booking-tabs">
                        <button class="booking-tab active" data-filter="all">All Sessions</button>
                        <button class="booking-tab" data-filter="upcoming">Upcoming</button>
                        <button class="booking-tab" data-filter="past">Completed & Cancelled</button>
                    </div>

                    <div class="booking-grid" id="bookingsList">
                        <?php foreach ($bookings as $book):
                            $isFuture  = strtotime($book['session_date'] . ' ' . $book['start_time']) > time();
                            $canCancel = $isFuture && in_array($book['status'], ['pending','confirmed']);
                            
                            $tag = '';
                            if ($book['status'] === 'completed' || $book['status'] === 'cancelled') {
                                $tag = 'past';
                            } else {
                                $tag = $isFuture ? 'upcoming' : 'past';
                            }
                            
                            // Photo logic
                            $photoUrl = '';
                            if (!empty($book['photo'])) {
                                $photoUrl = filter_var($book['photo'], FILTER_VALIDATE_URL) ? $book['photo'] : SITE_URL . '/' . ltrim($book['photo'], '/');
                            }
                        ?>
                        <div class="booking-card" data-filter="<?= $tag ?>">
                            
                            <div class="bc-header">
                                <?php if ($photoUrl): ?>
                                    <img src="<?= htmlspecialchars($photoUrl) ?>" class="bc-photo" alt="">
                                <?php else: ?>
                                    <div class="bc-initials"><?= strtoupper(substr($book['trainer_name'],0,1)) ?></div>
                                <?php endif; ?>
                                <div class="bc-trainer-info">
                                    <div class="bc-trainer-name"><?= htmlspecialchars($book['trainer_name']) ?></div>
                                    <div class="bc-trainer-spec"><?= htmlspecialchars($book['specialization']) ?></div>
                                </div>
                                <span class="bc-status-badge status-<?= $book['status'] ?>">
                                    <?= htmlspecialchars(ucfirst($book['status'])) ?>
                                </span>
                            </div>

                            <div class="bc-details">
                                <div class="bc-detail-row">
                                    <div class="bc-detail-icon"><i class="fa-regular fa-calendar" style="color:#FF6B35;"></i></div>
                                    <div><strong style="color:#1a1a2e;"><?= date('D, M d, Y', strtotime($book['session_date'])) ?></strong></div>
                                </div>
                                <div class="bc-detail-row">
                                    <div class="bc-detail-icon"><i class="fa-regular fa-clock" style="color:#6366f1;"></i></div>
                                    <div><?= date('h:i A', strtotime($book['start_time'])) ?> – <?= date('h:i A', strtotime($book['end_time'])) ?></div>
                                </div>
                                
                                <?php if (!empty($book['notes'])): ?>
                                <div class="bc-notes-area">
                                    <div class="bc-notes-label">Your Notes</div>
                                    <?= nl2br(htmlspecialchars($book['notes'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="bc-actions">
                                <a href="book-trainer.php?trainer_id=<?= $book['trainer_id'] ?>" class="bc-btn bc-btn-primary">
                                    <i class="fa-solid fa-rotate-right"></i> Book Again
                                </a>
                                <?php if ($canCancel): ?>
                                    <button type="button" class="bc-btn bc-btn-outline" onclick="openCancelModal(<?= $book['booking_id'] ?>, '<?= htmlspecialchars(addslashes($book['trainer_name'])) ?>', '<?= date('M d', strtotime($book['session_date'])) ?>')">
                                        Cancel
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Filter Empty State -->
                    <div id="filterEmptyState" class="empty-state" style="display:none; padding:4rem 1rem;">
                        <i class="fa-regular fa-folder-open mb-3" style="font-size:2.5rem;color:#d1d5db;"></i>
                        <h5 class="fw-bold mb-1">No sessions found</h5>
                        <p class="text-muted" style="font-size:.9rem;">Try selecting a different tab.</p>
                    </div>

                <?php endif; ?>

            </main>
        </div>
    </div>
</div>

<!-- Modern Cancel Confirmation Modal -->
<div id="cancelModal" class="cancel-modal">
    <div class="cancel-modal-content">
        <div class="cancel-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h4 class="fw-bold mb-2">Cancel Session?</h4>
        <p class="text-muted mb-4" style="font-size:.9rem;line-height:1.5;">Are you sure you want to cancel your session with <strong id="cmTrainerName" style="color:#1a1a2e;"></strong> on <strong id="cmDate" style="color:#1a1a2e;"></strong>? This action cannot be undone.</p>
        <form method="POST" id="cancelForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="cancel_booking_id" id="cancelBookingId" value="">
            <div class="d-flex gap-2">
                <button type="button" class="bc-btn bc-btn-outline" onclick="closeCancelModal()" style="flex:1;border:2px solid #eef0f7;background:#fff;">Keep it</button>
                <button type="submit" class="bc-btn bc-btn-primary" style="flex:1;background:#dc2626;border-radius:.75rem;">Yes, Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab Filtering Logic
const tabs = document.querySelectorAll('.booking-tab');
const cards = document.querySelectorAll('.booking-card');
const filterEmpty = document.getElementById('filterEmptyState');

tabs.forEach(btn => {
    btn.addEventListener('click', () => {
        tabs.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        const filter = btn.dataset.filter;
        let visibleCount = 0;
        
        cards.forEach(card => {
            if (filter === 'all' || card.dataset.filter === filter) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        if (visibleCount === 0 && filterEmpty) {
            filterEmpty.style.display = 'block';
        } else if (filterEmpty) {
            filterEmpty.style.display = 'none';
        }
    });
});

// Modal Logic
function openCancelModal(id, trainerName, dateStr) {
    document.getElementById('cancelBookingId').value = id;
    document.getElementById('cmTrainerName').textContent = trainerName;
    document.getElementById('cmDate').textContent = dateStr;
    document.getElementById('cancelModal').classList.add('show');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('show');
}

// Close modal if clicked outside
document.getElementById('cancelModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});
</script>

<?php require_once '../includes/footer.php'; ?>
