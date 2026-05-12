<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';

// Must verify trainer before doing any processing
require_trainer();
$trainerId = $_SESSION['user_id'];

// Handle form submissions before any HTML output to allow redirects
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_note') {
    $clientId = (int)$_POST['client_id'];
    $noteText = trim($_POST['note_text']);
    $stmt = $pdo->prepare("SELECT id FROM trainer_client_notes WHERE trainer_id = ? AND user_id = ?");
    $stmt->execute([$trainerId, $clientId]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE trainer_client_notes SET note_text = ? WHERE trainer_id = ? AND user_id = ?")->execute([$noteText, $trainerId, $clientId]);
    } else {
        $pdo->prepare("INSERT INTO trainer_client_notes (trainer_id, user_id, note_text) VALUES (?, ?, ?)")->execute([$trainerId, $clientId, $noteText]);
    }
    header("Location: clients.php?client_id=" . $clientId . "&saved=1");
    exit;
}

// Fetch clients list
$stmt = $pdo->prepare("SELECT DISTINCT u.user_id, u.full_name, u.email, u.phone, u.profile_photo as photo FROM users u JOIN trainer_bookings tb ON u.user_id = tb.user_id WHERE tb.trainer_id = ? ORDER BY u.full_name ASC");
$stmt->execute([$trainerId]);
$clients = $stmt->fetchAll();

$pageTitle = 'My Clients';
require_once 'includes/trainer_header.php';

$selectedClient = null; $bmiHistory = []; $clientNote = ''; $clientBookings = [];

if (isset($_GET['client_id'])) {
    $clientId = (int)$_GET['client_id'];
    foreach ($clients as $c) {
        if ($c['user_id'] == $clientId) { $selectedClient = $c; break; }
    }
    if ($selectedClient) {
        $bmiStmt = $pdo->prepare("SELECT * FROM bmi_records WHERE user_id = ? ORDER BY recorded_at DESC");
        $bmiStmt->execute([$clientId]);
        $bmiHistory = $bmiStmt->fetchAll();

        $noteRow = $pdo->prepare("SELECT note_text FROM trainer_client_notes WHERE trainer_id = ? AND user_id = ?");
        $noteRow->execute([$trainerId, $clientId]);
        $nr = $noteRow->fetch();
        if ($nr) $clientNote = $nr['note_text'];

        $bookingStmt = $pdo->prepare("SELECT * FROM trainer_bookings WHERE trainer_id = ? AND user_id = ? ORDER BY session_date DESC, start_time DESC");
        $bookingStmt->execute([$trainerId, $clientId]);
        $clientBookings = $bookingStmt->fetchAll();
    }
}

$avatarGradients = ['linear-gradient(135deg,#667eea,#764ba2)','linear-gradient(135deg,#f093fb,#f5576c)','linear-gradient(135deg,#4facfe,#00f2fe)','linear-gradient(135deg,#43e97b,#38f9d7)','linear-gradient(135deg,#fa709a,#fee140)','linear-gradient(135deg,#a18cd1,#fbc2eb)'];
?>
<style>
/* ── Page Layout ─────────────────── */
.cl-wrap { display:grid; grid-template-columns:320px 1fr; gap:1.5rem; align-items:start; }
@media(max-width:768px){ .cl-wrap{ grid-template-columns:1fr; } }

/* ── Header ─────────────────────── */
.cl-page-header { background:linear-gradient(135deg,#1A1A2E,#0f3460); border-radius:1.25rem; padding:1.75rem 2rem; color:#fff; margin-bottom:1.75rem; position:relative; overflow:hidden; }
.cl-page-header::before { content:''; position:absolute; top:-30px; right:-30px; width:140px; height:140px; background:rgba(255,107,53,.12); border-radius:50%; }
.cl-page-header h4 { font-size:1.4rem; font-weight:800; margin:0; }
.cl-page-header p { opacity:.7; margin:.25rem 0 0; font-size:.88rem; }

/* ── Roster Card ─────────────────── */
.roster-card { background:#fff; border-radius:1.25rem; box-shadow:0 4px 24px rgba(0,0,0,.07); border:1px solid #f0f2f7; overflow:hidden; position:sticky; top:90px; }
.roster-header { padding:1.25rem 1.25rem .75rem; border-bottom:1px solid #f4f6fb; }
.roster-title { font-size:.92rem; font-weight:800; color:#1a1a2e; margin:0 0 .75rem; }
.roster-search { display:flex; align-items:center; gap:.5rem; background:#f4f6fb; border-radius:.75rem; padding:.5rem .85rem; }
.roster-search input { border:none; background:transparent; outline:none; font-size:.85rem; color:#374151; width:100%; }
.roster-search i { color:#9ca3af; font-size:.85rem; flex-shrink:0; }
.roster-list { max-height:520px; overflow-y:auto; }
.client-row { display:flex; align-items:center; gap:.85rem; padding:.85rem 1.25rem; cursor:pointer; text-decoration:none; border-bottom:1px solid #f8f9fc; transition:all .18s; }
.client-row:hover { background:#f8f9fc; }
.client-row.active { background:linear-gradient(135deg,rgba(255,107,53,.08),rgba(255,107,53,.03)); border-left:3px solid #FF6B35; }
.client-row.active .cr-name { color:#FF6B35; }
.cr-avatar { width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.95rem; font-weight:700; color:#fff; flex-shrink:0; }
.cr-avatar img { width:44px; height:44px; border-radius:50%; object-fit:cover; }
.cr-name { font-size:.88rem; font-weight:700; color:#1a1a2e; margin:0; }
.cr-email { font-size:.73rem; color:#9ca3af; margin:.1rem 0 0; }
.roster-count { font-size:.72rem; color:#9ca3af; padding:.5rem 1.25rem; text-align:center; border-top:1px solid #f4f6fb; }
.empty-roster { text-align:center; padding:3rem 1rem; color:#9ca3af; }
.empty-roster i { font-size:2.5rem; opacity:.3; margin-bottom:.75rem; display:block; }

/* ── Detail Area ─────────────────── */
.detail-card { background:#fff; border-radius:1.25rem; box-shadow:0 4px 24px rgba(0,0,0,.07); border:1px solid #f0f2f7; }
.client-hero { padding:1.75rem 2rem; border-bottom:1px solid #f4f6fb; display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap; }
.client-hero-avatar { width:80px; height:80px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.8rem; font-weight:800; color:#fff; flex-shrink:0; }
.client-hero-avatar img { width:80px; height:80px; border-radius:50%; object-fit:cover; }
.hero-name { font-size:1.35rem; font-weight:800; color:#1a1a2e; margin:0 0 .3rem; }
.hero-meta { display:flex; flex-wrap:wrap; gap:.75rem; margin:.4rem 0 0; }
.hero-meta a, .hero-meta span { font-size:.8rem; color:#6b7280; text-decoration:none; display:flex; align-items:center; gap:.35rem; }
.hero-meta a:hover { color:#FF6B35; }
.hero-actions { margin-left:auto; }
.btn-assign { background:#FF6B35; color:#fff; border:none; border-radius:100px; padding:.5rem 1.25rem; font-size:.85rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:all .2s; }
.btn-assign:hover { background:#e85a22; color:#fff; transform:translateY(-1px); }

/* ── Stats Row ───────────────────── */
.stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; padding:1.25rem 2rem; border-bottom:1px solid #f4f6fb; }
.stat-item { text-align:center; }
.stat-num { font-size:1.4rem; font-weight:800; color:#1a1a2e; }
.stat-label { font-size:.7rem; color:#9ca3af; font-weight:600; text-transform:uppercase; letter-spacing:.05em; margin-top:.1rem; }

/* ── Tabs ────────────────────────── */
.cl-tabs { display:flex; gap:0; padding:0 2rem; border-bottom:2px solid #f4f6fb; }
.cl-tab-btn { background:none; border:none; border-bottom:2px solid transparent; padding:.9rem 1.1rem; font-size:.85rem; font-weight:700; color:#9ca3af; cursor:pointer; margin-bottom:-2px; transition:all .2s; display:flex; align-items:center; gap:.4rem; }
.cl-tab-btn.active { color:#FF6B35; border-bottom-color:#FF6B35; }
.cl-tab-btn:hover:not(.active) { color:#374151; }
.cl-tab-content { padding:1.5rem 2rem 2rem; }
.cl-pane { display:none; }
.cl-pane.active { display:block; }

/* ── BMI Cards ───────────────────── */
.bmi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:.85rem; }
.bmi-record-card { background:#f8f9fc; border-radius:1rem; padding:1rem; border:1px solid #eef0f7; transition:all .2s; }
.bmi-record-card:hover { background:#fff; box-shadow:0 4px 16px rgba(0,0,0,.07); }
.bmi-val { font-size:1.75rem; font-weight:900; line-height:1; }
.bmi-date { font-size:.7rem; color:#9ca3af; margin-top:.3rem; }
.bmi-pill { display:inline-block; border-radius:100px; padding:.2rem .6rem; font-size:.7rem; font-weight:700; color:#fff; margin:.4rem 0 .2rem; }
.bmi-hw { font-size:.72rem; color:#6b7280; }

/* ── Booking List ────────────────── */
.booking-item { display:flex; align-items:center; gap:1rem; padding:.85rem 0; border-bottom:1px solid #f4f6fb; }
.booking-item:last-child { border-bottom:none; }
.bk-date-box { background:#f4f6fb; border-radius:.75rem; padding:.4rem .7rem; text-align:center; min-width:52px; flex-shrink:0; }
.bk-day { font-size:1.1rem; font-weight:800; color:#1a1a2e; line-height:1; }
.bk-mon { font-size:.62rem; color:#9ca3af; font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
.bk-status { border-radius:100px; padding:.2rem .65rem; font-size:.72rem; font-weight:700; }
.bk-status.confirmed { background:rgba(34,197,94,.1); color:#22c55e; }
.bk-status.pending   { background:rgba(245,158,11,.1); color:#f59e0b; }
.bk-status.cancelled { background:rgba(239,68,68,.1); color:#ef4444; }
.bk-status.completed { background:rgba(99,102,241,.1); color:#6366f1; }
.bk-time { font-size:.75rem; color:#6b7280; margin:.15rem 0 0; }

/* ── Notes ───────────────────────── */
.notes-area { width:100%; background:#f8f9fc; border:1px solid #eef0f7; border-radius:.85rem; padding:1rem; font-size:.9rem; color:#374151; resize:vertical; outline:none; transition:border .2s; min-height:160px; }
.notes-area:focus { border-color:#FF6B35; background:#fff; }
.btn-save-notes { background:#FF6B35; color:#fff; border:none; border-radius:100px; padding:.6rem 1.5rem; font-size:.87rem; font-weight:700; cursor:pointer; transition:all .2s; }
.btn-save-notes:hover { background:#e85a22; transform:translateY(-1px); }

/* ── Empty placeholder ───────────── */
.select-placeholder { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:440px; text-align:center; padding:2rem; }
.select-placeholder .icon-wrap { width:90px; height:90px; border-radius:50%; background:linear-gradient(135deg,rgba(255,107,53,.1),rgba(255,107,53,.04)); display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem; font-size:2rem; color:#FF6B35; }
.select-placeholder h5 { font-weight:800; color:#1a1a2e; margin:0 0 .5rem; }
.select-placeholder p { color:#9ca3af; font-size:.88rem; max-width:280px; margin:0 auto; }

.saved-toast { position:fixed; bottom:1.5rem; right:1.5rem; background:#22c55e; color:#fff; padding:.65rem 1.25rem; border-radius:100px; font-size:.85rem; font-weight:700; z-index:9999; box-shadow:0 4px 20px rgba(34,197,94,.35); display:none; align-items:center; gap:.5rem; }
.saved-toast.show { display:flex; animation:fadeSlide .3s ease; }
@keyframes fadeSlide { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="container-fluid px-3 px-md-4 pb-5">

<?php if (isset($_GET['saved'])): ?>
<div class="saved-toast show" id="savedToast"><i class="fa-solid fa-check-circle"></i> Notes saved successfully!</div>
<script>setTimeout(()=>document.getElementById('savedToast')?.remove(), 3000)</script>
<?php endif; ?>

<!-- Page Header -->
<div class="cl-page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h4><i class="fa-solid fa-users me-2"></i>My Clients</h4>
            <p>Manage your athletes and track their progress</p>
        </div>
        <div style="background:rgba(255,255,255,.1);border-radius:1rem;padding:.6rem 1.25rem;font-size:.85rem;font-weight:700;">
            <i class="fa-solid fa-person-running me-2" style="color:#FF6B35;"></i><?= count($clients) ?> Client<?= count($clients) !== 1 ? 's' : '' ?>
        </div>
    </div>
</div>

<div class="cl-wrap">

    <!-- Left: Roster -->
    <div class="roster-card">
        <div class="roster-header">
            <div class="roster-title"><i class="fa-solid fa-list-ul me-2" style="color:#FF6B35;"></i>Client Roster</div>
            <div class="roster-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="clientSearch" placeholder="Search clients...">
            </div>
        </div>
        <div class="roster-list" id="clientList">
            <?php if (empty($clients)): ?>
            <div class="empty-roster">
                <i class="fa-solid fa-users"></i>
                <p style="font-size:.88rem;margin:0;">No clients yet.<br>Clients appear here after booking.</p>
            </div>
            <?php else: ?>
            <?php foreach ($clients as $i => $client):
                $isActive = ($selectedClient && $selectedClient['user_id'] == $client['user_id']);
                $grad = $avatarGradients[$client['user_id'] % count($avatarGradients)];
            ?>
            <a href="clients.php?client_id=<?= $client['user_id'] ?>" class="client-row <?= $isActive ? 'active' : '' ?>">
                <div class="cr-avatar" style="background:<?= $grad ?>">
                    <?php if (!empty($client['photo'])): ?>
                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars(ltrim($client['photo'],'/')) ?>" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($client['full_name'],0,1)) ?>
                    <?php endif; ?>
                </div>
                <div style="min-width:0;">
                    <div class="cr-name"><?= htmlspecialchars($client['full_name']) ?></div>
                    <div class="cr-email"><?= htmlspecialchars($client['email']) ?></div>
                </div>
                <?php if ($isActive): ?>
                <i class="fa-solid fa-chevron-right ms-auto" style="color:#FF6B35;font-size:.7rem;"></i>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($clients)): ?>
        <div class="roster-count"><?= count($clients) ?> total client<?= count($clients) !== 1 ? 's' : '' ?></div>
        <?php endif; ?>
    </div>

    <!-- Right: Detail -->
    <div>
        <?php if ($selectedClient):
            $grad = $avatarGradients[$selectedClient['user_id'] % count($avatarGradients)];
            $totalSessions  = count($clientBookings);
            $upcoming       = array_filter($clientBookings, fn($b) => strtotime($b['session_date'].' '.$b['start_time']) >= time());
            $completed      = array_filter($clientBookings, fn($b) => $b['status'] === 'completed');
        ?>
        <div class="detail-card">
            <!-- Hero -->
            <div class="client-hero">
                <div class="client-hero-avatar" style="background:<?= $grad ?>">
                    <?php if (!empty($selectedClient['photo'])): ?>
                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars(ltrim($selectedClient['photo'],'/')) ?>" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($selectedClient['full_name'],0,1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="hero-name"><?= htmlspecialchars($selectedClient['full_name']) ?></div>
                    <div class="hero-meta">
                        <a href="mailto:<?= htmlspecialchars($selectedClient['email']) ?>"><i class="fa-solid fa-envelope"></i><?= htmlspecialchars($selectedClient['email']) ?></a>
                        <?php if (!empty($selectedClient['phone'])): ?>
                        <a href="tel:<?= htmlspecialchars($selectedClient['phone']) ?>"><i class="fa-solid fa-phone"></i><?= htmlspecialchars($selectedClient['phone']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hero-actions">
                    <a href="assign_plans.php?preselect_client=<?= $selectedClient['user_id'] ?>" class="btn-assign">
                        <i class="fa-solid fa-file-signature"></i> Assign Plan
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-num" style="color:#6366f1;"><?= $totalSessions ?></div>
                    <div class="stat-label">Total Sessions</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num" style="color:#22c55e;"><?= count($completed) ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num" style="color:#FF6B35;"><?= count($bmiHistory) ?></div>
                    <div class="stat-label">BMI Readings</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="cl-tabs">
                <button class="cl-tab-btn active" onclick="switchTab(this,'tab-bmi')"><i class="fa-solid fa-chart-line"></i> BMI History</button>
                <button class="cl-tab-btn" onclick="switchTab(this,'tab-bookings')"><i class="fa-solid fa-calendar-check"></i> Sessions</button>
                <button class="cl-tab-btn" onclick="switchTab(this,'tab-notes')"><i class="fa-solid fa-clipboard-user"></i> Notes</button>
            </div>

            <div class="cl-tab-content">

                <!-- BMI Tab -->
                <div id="tab-bmi" class="cl-pane active">
                    <?php if (empty($bmiHistory)): ?>
                    <div style="text-align:center;padding:3rem 1rem;color:#9ca3af;">
                        <i class="fa-solid fa-weight-scale" style="font-size:2.5rem;opacity:.25;display:block;margin-bottom:.75rem;"></i>
                        <p style="font-size:.9rem;margin:0;">No BMI records logged yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="bmi-grid">
                        <?php foreach ($bmiHistory as $record):
                            $bv = (float)($record['bmi_value'] ?? $record['bmi'] ?? 0);
                            $cat = $record['category'] ?? '—';
                            $bmiColor = $bv < 18.5 ? '#17a2b8' : ($bv < 25 ? '#22c55e' : ($bv < 30 ? '#f59e0b' : '#ef4444'));
                        ?>
                        <div class="bmi-record-card">
                            <div class="bmi-val" style="color:<?= $bmiColor ?>"><?= number_format($bv,1) ?></div>
                            <div><span class="bmi-pill" style="background:<?= $bmiColor ?>"><?= htmlspecialchars($cat) ?></span></div>
                            <div class="bmi-hw">
                                <?php if (!empty($record['weight_kg'])): ?><?= $record['weight_kg'] ?> kg · <?= $record['height_cm'] ?> cm<br><?php endif; ?>
                            </div>
                            <div class="bmi-date"><i class="fa-regular fa-clock me-1"></i><?= date('M d, Y', strtotime($record['recorded_at'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Bookings Tab -->
                <div id="tab-bookings" class="cl-pane">
                    <?php if (empty($clientBookings)): ?>
                    <div style="text-align:center;padding:3rem 1rem;color:#9ca3af;">
                        <i class="fa-regular fa-calendar-xmark" style="font-size:2.5rem;opacity:.25;display:block;margin-bottom:.75rem;"></i>
                        <p style="font-size:.9rem;margin:0;">No sessions booked yet.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($clientBookings as $b):
                        $s = $b['status'];
                        $isPast = strtotime($b['session_date'].' '.$b['start_time']) < time();
                    ?>
                    <div class="booking-item" style="<?= $isPast ? 'opacity:.65' : '' ?>">
                        <div class="bk-date-box">
                            <div class="bk-day"><?= date('d', strtotime($b['session_date'])) ?></div>
                            <div class="bk-mon"><?= date('M', strtotime($b['session_date'])) ?></div>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:.87rem;color:#1a1a2e;"><?= date('l', strtotime($b['session_date'])) ?></div>
                            <div class="bk-time"><i class="fa-regular fa-clock me-1"></i><?= date('h:i A', strtotime($b['start_time'])) ?> – <?= date('h:i A', strtotime($b['end_time'])) ?></div>
                        </div>
                        <span class="bk-status <?= $s ?>"><?= ucfirst($s) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Notes Tab -->
                <div id="tab-notes" class="cl-pane">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_note">
                        <input type="hidden" name="client_id" value="<?= $selectedClient['user_id'] ?>">
                        <label style="font-size:.85rem;font-weight:700;color:#1a1a2e;margin-bottom:.5rem;display:block;"><i class="fa-solid fa-lock me-1" style="color:#9ca3af;"></i> Private Notes (only visible to you)</label>
                        <textarea class="notes-area" name="note_text" placeholder="Record injuries, goals, progress, workout preferences..."><?= htmlspecialchars($clientNote) ?></textarea>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn-save-notes"><i class="fa-solid fa-floppy-disk me-2"></i>Save Notes</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <?php else: ?>
        <div class="detail-card">
            <div class="select-placeholder">
                <div class="icon-wrap"><i class="fa-solid fa-person-running"></i></div>
                <h5>Select a Client</h5>
                <p>Choose a client from the roster to view their sessions, BMI history, and add private coaching notes.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- .cl-wrap -->
</div>

<script>
function switchTab(btn, paneId) {
    document.querySelectorAll('.cl-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.cl-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(paneId)?.classList.add('active');
}

document.getElementById('clientSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#clientList .client-row').forEach(row => {
        const name = row.querySelector('.cr-name')?.textContent.toLowerCase() || '';
        row.style.display = name.includes(q) ? '' : 'none';
    });
});
</script>

<?php require_once 'includes/trainer_footer.php'; ?>
