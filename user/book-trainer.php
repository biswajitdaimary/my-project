<?php
$pageTitle = 'Book a Trainer';
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_user();
$user_id = $_SESSION['user_id'];

// Fetch trainer hourly rates for display in booking summary
$trainerRates = [];
try {
    $rStmt = $pdo->query("SELECT trainer_id, hourly_rate FROM trainers WHERE is_active = 1");
    foreach ($rStmt->fetchAll() as $r) $trainerRates[(int)$r['trainer_id']] = $r['hourly_rate'];
} catch (PDOException $e) {}

$trainers = [];
try {
    $tStmt = $pdo->query("SELECT trainer_id, full_name, specialization, rating, photo, experience_years FROM trainers WHERE is_active = 1 ORDER BY rating DESC");
    $trainers = $tStmt->fetchAll();
} catch (PDOException $e) {}

$selectedTrainer = isset($_GET['trainer_id']) ? (int)$_GET['trainer_id'] : 0;

require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<style>
/* ── Step Progress Bar ─────────────────────────────────── */
.step-progress { display:flex; align-items:center; gap:0; margin-bottom:2rem; }
.step-item { display:flex; flex-direction:column; align-items:center; flex:1; position:relative; }
.step-item:not(:last-child)::after { content:''; position:absolute; top:18px; left:calc(50% + 20px); width:calc(100% - 40px); height:2px; background:#eef0f7; z-index:0; transition:background .3s; }
.step-item.done:not(:last-child)::after, .step-item.active:not(:last-child)::after { background:linear-gradient(90deg,#FF6B35,#eef0f7); }
.step-circle { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.85rem; font-weight:800; border:2.5px solid #eef0f7; background:#fff; color:#9ca3af; z-index:1; transition:all .3s; }
.step-item.active .step-circle { border-color:#FF6B35; background:#FF6B35; color:#fff; box-shadow:0 4px 14px rgba(255,107,53,.35); }
.step-item.done .step-circle { border-color:#22c55e; background:#22c55e; color:#fff; }
.step-text { font-size:.72rem; font-weight:600; color:#9ca3af; margin-top:.4rem; white-space:nowrap; }
.step-item.active .step-text, .step-item.done .step-text { color:#1a1a2e; }

/* ── Step Badges (inside cards) ─────────────────────── */
.step-badge { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; background:#FF6B35; color:#fff; font-size:.8rem; font-weight:800; flex-shrink:0; }
.step-label { font-weight:700; color:#1a1a2e; font-size:.92rem; }

/* ── Trainer Cards ──────────────────────────────────── */
.trainer-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:.85rem; }
.trainer-pick-card { border:2px solid #eef0f7; border-radius:1.15rem; padding:1.1rem .9rem; text-align:center; cursor:pointer; transition:all .25s cubic-bezier(.4,0,.2,1); background:#fff; position:relative; overflow:hidden; }
.trainer-pick-card:hover { border-color:#FF6B35; transform:translateY(-4px); box-shadow:0 12px 28px rgba(255,107,53,.14); }
.trainer-pick-card.selected { border-color:#FF6B35; background:linear-gradient(135deg,rgba(255,107,53,.07),rgba(255,107,53,.02)); box-shadow:0 8px 24px rgba(255,107,53,.22); }
.trainer-pick-card .check-mark { position:absolute; top:12px; right:12px; width:22px; height:22px; border-radius:50%; background:#FF6B35; color:#fff; font-size:.65rem; display:none; align-items:center; justify-content:center; z-index:2; }
.trainer-pick-card.selected .check-mark { display:flex; }
.trainer-avatar { width:70px; height:70px; border-radius:50%; object-fit:cover; border:3px solid #fff; box-shadow:0 4px 12px rgba(0,0,0,.1); margin:0 auto .7rem; display:block; }
.trainer-avatar-init { width:70px; height:70px; border-radius:50%; color:#fff; font-size:1.55rem; font-weight:800; display:flex; align-items:center; justify-content:center; margin:0 auto .7rem; box-shadow:0 4px 12px rgba(0,0,0,.12); }
.trainer-name { font-weight:700; font-size:.87rem; color:#1a1a2e; margin-bottom:.15rem; }
.trainer-spec { font-size:.71rem; color:#9ca3af; margin-bottom:.35rem; }
.trainer-stars { font-size:.72rem; font-weight:700; }
.trainer-rate-pill { display:inline-block; font-size:.66rem; font-weight:700; background:rgba(255,107,53,.1); color:#FF6B35; border-radius:100px; padding:.18rem .55rem; margin-top:.3rem; }

/* ── Selected Trainer Panel ─────────────────────────── */
.trainer-selected-panel { background:#fff; border:1px solid #eef0f7; border-radius:1rem; padding:1.25rem 1.5rem; margin-bottom:1.25rem; display:none; box-shadow:0 4px 15px rgba(0,0,0,.04); }
.trainer-selected-panel.show { display:flex; align-items:center; gap:1.1rem; }
.tsp-avatar { width:56px; height:56px; border-radius:50%; object-fit:cover; border:2px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,.1); flex-shrink:0; }
.tsp-init { width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.4rem; font-weight:800; background:rgba(255,107,53,.15); color:#FF6B35; flex-shrink:0; }
.tsp-name { font-weight:800; font-size:1rem; margin-bottom:.15rem; color:#1a1a2e; }
.tsp-spec { font-size:.78rem; color:#6b7280; }
.tsp-badge { font-size:.7rem; font-weight:700; padding:.2rem .65rem; border-radius:100px; background:rgba(255,107,53,.1); color:#FF6B35; }

/* ── Form controls ──────────────────────────────────── */
.form-label-custom { font-weight:700; font-size:.85rem; color:#1a1a2e; margin-bottom:.45rem; display:block; }
.form-control-custom { border:2px solid #eef0f7; border-radius:.75rem; padding:.72rem 1rem; font-size:.9rem; width:100%; transition:border-color .2s,box-shadow .2s; background:#fff; color:#1a1a2e; outline:none; }
.form-control-custom:focus { border-color:#FF6B35; box-shadow:0 0 0 3px rgba(255,107,53,.1); }

/* ── Slot grid ──────────────────────────────────────── */
.slot-period-label { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#9ca3af; margin:.9rem 0 .4rem; }
.slot-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; }
.slot-btn { border:2px solid #eef0f7; border-radius:.65rem; padding:.55rem .25rem; font-size:.78rem; font-weight:600; cursor:pointer; background:#fff; color:#374151; transition:all .2s; text-align:center; line-height:1.3; }
.slot-btn:hover:not(.booked) { border-color:#FF6B35; color:#FF6B35; background:rgba(255,107,53,.04); }
.slot-btn.selected { background:#FF6B35; border-color:#FF6B35; color:#fff; box-shadow:0 4px 12px rgba(255,107,53,.3); }
.slot-btn.booked { background:#f9fafb; color:#d1d5db; cursor:not-allowed; border-color:#f4f6fb; text-decoration:line-through; }
.slots-empty { text-align:center; padding:2rem 1rem; color:#9ca3af; font-size:.85rem; }

/* ── Booking summary ────────────────────────────────── */
.booking-summary-card { background:#f8f9fc; border-radius:1rem; padding:1.1rem 1.25rem; border:1px solid #eef0f7; }
.bs-row { display:flex; align-items:center; gap:.65rem; padding:.4rem 0; border-bottom:1px solid #f0f2f7; font-size:.84rem; color:#374151; }
.bs-row:last-child { border-bottom:none; }
.bs-icon { width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.7rem; flex-shrink:0; }

/* ── Confirm button ─────────────────────────────────── */
.confirm-btn { background:linear-gradient(135deg,#FF6B35,#ff8c5a); color:#fff; border:none; border-radius:100px; padding:.78rem 2rem; font-size:.95rem; font-weight:700; cursor:pointer; transition:all .25s; box-shadow:0 6px 20px rgba(255,107,53,.35); width:100%; }
.confirm-btn:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 10px 28px rgba(255,107,53,.45); }
.confirm-btn:disabled { background:#e5e7eb; color:#9ca3af; box-shadow:none; cursor:not-allowed; }

/* ── Toast ──────────────────────────────────────────── */
#bk-toast { position:fixed; bottom:2rem; right:2rem; z-index:9999; min-width:280px; background:#1a1a2e; color:#fff; border-radius:1rem; padding:1rem 1.25rem; display:none; align-items:center; gap:.75rem; box-shadow:0 12px 32px rgba(0,0,0,.25); animation:slideUp .3s ease; }
@keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
#bk-toast.show { display:flex; }
#bk-toast.success .toast-icon { color:#22c55e; }
#bk-toast.error .toast-icon { color:#ef4444; }

/* ── Success overlay ────────────────────────────────── */
#successOverlay { position:fixed; inset:0; background:rgba(26,26,46,.85); z-index:9998; display:none; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
#successOverlay.show { display:flex; }
.success-card { background:#fff; border-radius:2rem; padding:3rem 2.5rem; text-align:center; max-width:380px; width:90%; animation:popIn .35s cubic-bezier(.34,1.56,.64,1); }
@keyframes popIn { from{transform:scale(.8);opacity:0} to{transform:scale(1);opacity:1} }
.success-icon { width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; font-size:2rem; display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem; box-shadow:0 8px 24px rgba(34,197,94,.35); }

@media(max-width:576px){
  .step-text { display:none; }
  .slot-grid { grid-template-columns:repeat(2,1fr); }
  .dt-slot-grid { grid-template-columns:1fr !important; }
  .bs-summary-grid { grid-template-columns:1fr !important; }
}
@media(max-width:768px){
  .dt-slot-grid { grid-template-columns:1fr !important; }
}
</style>

<div class="up-wrap"><div class="container-fluid px-0"><div class="d-flex">
<?php require_once '../includes/sidebar-user.php'; ?>
<main class="up-main flex-grow-1" style="min-width:0;">

<div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1a1a2e;">Book a Trainer</h4>
        <p class="text-muted mb-0" style="font-size:.87rem;">Select a trainer, pick a date and confirm your session</p>
    </div>
    <span class="badge rounded-pill px-3 py-2" style="background:rgba(99,102,241,.1);color:#6366f1;font-size:.82rem;font-weight:600;">
        <i class="fa-solid fa-dumbbell me-1"></i><?= count($trainers) ?> Trainers Available
    </span>
</div>

<!-- Step Progress -->
<div class="step-progress mb-4">
    <div class="step-item active" id="sp1">
        <div class="step-circle"><i class="fa-solid fa-person-running"></i></div>
        <div class="step-text">Choose Trainer</div>
    </div>
    <div class="step-item" id="sp2">
        <div class="step-circle"><i class="fa-regular fa-calendar"></i></div>
        <div class="step-text">Date &amp; Slots</div>
    </div>
    <div class="step-item" id="sp3">
        <div class="step-circle"><i class="fa-solid fa-check"></i></div>
        <div class="step-text">Confirm</div>
    </div>
</div>

<div class="row g-4">

    <!-- Left col: Choose Trainer -->
    <div class="col-md-7">
        <div class="up-card">
            <div class="up-card-header">
                <h6 class="up-card-title d-flex align-items-center gap-2">
                    <span class="step-badge">1</span>
                    <span class="step-label">Choose a Trainer</span>
                </h6>
            </div>
            <div class="up-card-body">
                <div class="trainer-grid">
                    <?php foreach ($trainers as $t):
                        $isSelected = ($selectedTrainer === (int)$t['trainer_id']);
                        $rate = $trainerRates[(int)$t['trainer_id']] ?? null;
                        $avatarColors = ['linear-gradient(135deg,#667eea,#764ba2)','linear-gradient(135deg,#f093fb,#f5576c)','linear-gradient(135deg,#4facfe,#00f2fe)','linear-gradient(135deg,#43e97b,#38f9d7)','linear-gradient(135deg,#fa709a,#fee140)'];
                        $aColor = $avatarColors[$t['trainer_id'] % count($avatarColors)];
                        $stars = round((float)$t['rating']);
                    ?>
                    <div class="trainer-pick-card <?= $isSelected ? 'selected' : '' ?>"
                         data-trainer-id="<?= $t['trainer_id'] ?>"
                         data-name="<?= htmlspecialchars(addslashes($t['full_name'])) ?>"
                         data-spec="<?= htmlspecialchars(addslashes($t['specialization'])) ?>"
                         data-photo="<?= htmlspecialchars($t['photo'] ?? '') ?>"
                         data-rate="<?= $rate ? '₹'.number_format((float)$rate,0).'/hr' : '' ?>"
                         onclick="selectTrainer(<?= $t['trainer_id'] ?>)">
                        <div class="check-mark"><i class="fa-solid fa-check"></i></div>
                        <?php
                        if (!empty($t['photo'])) {
                            $photoUrl = filter_var($t['photo'], FILTER_VALIDATE_URL) ? $t['photo'] : SITE_URL . '/' . ltrim($t['photo'], '/');
                            echo '<img src="' . htmlspecialchars($photoUrl) . '" class="trainer-avatar" alt="">';
                        } else {
                            echo '<div class="trainer-avatar-init" style="background:' . $aColor . '">' . strtoupper(substr($t['full_name'],0,1)) . '</div>';
                        }
                        ?>
                        <div class="trainer-name"><?= htmlspecialchars($t['full_name']) ?></div>
                        <div class="trainer-spec"><?= htmlspecialchars($t['specialization']) ?></div>
                        <div class="trainer-stars" style="color:#f59e0b; margin-top:.3rem;">
                            <?php for($s=1;$s<=5;$s++) echo $s<=$stars?'★':'☆'; ?>
                            <span style="color:#6b7280;font-weight:500;"> <?= number_format($t['rating'],1) ?></span>
                        </div>
                        <?php if (!empty($t['experience_years'])): ?>
                        <div style="font-size:.68rem;color:#9ca3af;margin-top:.2rem;"><?= $t['experience_years'] ?> yrs exp</div>
                        <?php endif; ?>
                        <?php if ($rate): ?>
                        <div class="trainer-rate-pill">₹<?= number_format((float)$rate,0) ?>/hr</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="selectedTrainerId" value="<?= $selectedTrainer ?>">
            </div>
        </div>
    </div>

    <!-- Right col: Date & Available Slots (sticky) -->
    <div class="col-md-5">
        <div class="up-card" style="position:sticky;top:90px;">
            <div class="up-card-header" style="border-bottom:2px solid #f4f6fb;">
                <h6 class="up-card-title d-flex align-items-center gap-2">
                    <span class="step-badge">2</span>
                    <span class="step-label">Date &amp; Available Slots</span>
                </h6>
                <small id="slotDateLabel" class="text-muted"></small>
            </div>
            <div class="up-card-body">

                <!-- Selected trainer mini-panel -->
                <div class="trainer-selected-panel" id="trainerSelectedPanel">
                    <div id="tspImg"></div>
                    <div class="flex-grow-1" style="min-width:0;">
                        <div class="tsp-name" id="tspName">—</div>
                        <div class="tsp-spec" id="tspSpec">—</div>
                        <div class="mt-1"><span class="tsp-badge" id="tspRate" style="display:none;"></span></div>
                    </div>
                </div>

                <!-- Date picker -->
                <div style="margin-top:1rem;">
                    <label class="form-label-custom"><i class="fa-regular fa-calendar me-1" style="color:#FF6B35;"></i>Session Date</label>
                    <input type="date" id="dateSelect" class="form-control-custom"
                           min="<?= date('Y-m-d') ?>"
                           max="<?= date('Y-m-d', strtotime('+90 days')) ?>">
                </div>

                <!-- Available slots -->
                <div style="margin-top:1rem;">
                    <label class="form-label-custom"><i class="fa-regular fa-clock me-1" style="color:#FF6B35;"></i>Available Slots</label>
                    <div id="slotsContainer" style="max-height:220px;overflow-y:auto;padding-right:2px;">
                        <div class="slots-empty">
                            <i class="fa-regular fa-calendar-check fa-2x mb-2 d-block" style="color:#e5e7eb;"></i>
                            Choose a trainer &amp; pick a date
                        </div>
                    </div>
                    <input type="hidden" id="selectedSlotStart" value="">
                    <input type="hidden" id="selectedSlotEnd" value="">
                </div>

                <!-- Notes -->
                <div style="margin-top:1rem;">
                    <label class="form-label-custom">Notes <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                    <textarea id="bookingNotes" class="form-control-custom" rows="2" placeholder="Any specific goals or focus for this session?"></textarea>
                </div>

                <!-- Booking summary + confirm -->
                <div class="mt-3 pt-3" style="border-top:2px solid #f4f6fb;">
                    <div id="bookingSummary" class="booking-summary-card mb-3" style="display:none;">
                        <div style="font-size:.72rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.5rem;">Booking Summary</div>
                        <div class="bs-row"><div class="bs-icon" style="background:rgba(255,107,53,.1);"><i class="fa-solid fa-person-running" style="color:#FF6B35;"></i></div><div><div style="font-size:.65rem;color:#9ca3af;">Trainer</div><span id="summTrainer" style="font-weight:700;color:#1a1a2e;font-size:.82rem;">—</span></div></div>
                        <div class="bs-row"><div class="bs-icon" style="background:rgba(99,102,241,.1);"><i class="fa-regular fa-calendar" style="color:#6366f1;"></i></div><div><div style="font-size:.65rem;color:#9ca3af;">Date</div><span id="summDate" style="color:#374151;font-size:.82rem;">—</span></div></div>
                        <div class="bs-row"><div class="bs-icon" style="background:rgba(34,197,94,.1);"><i class="fa-regular fa-clock" style="color:#22c55e;"></i></div><div><div style="font-size:.65rem;color:#9ca3af;">Time</div><span id="summTime" style="color:#374151;font-size:.82rem;">—</span></div></div>
                        <div class="bs-row" id="summRateRow" style="display:none;"><div class="bs-icon" style="background:rgba(245,158,11,.1);"><i class="fa-solid fa-indian-rupee-sign" style="color:#f59e0b;"></i></div><span id="summRate" style="color:#374151;font-size:.82rem;">—</span></div>
                    </div>
                    <button class="confirm-btn" id="confirmBookingBtn" disabled>
                        <i class="fa-solid fa-check me-2"></i>Confirm Booking
                    </button>
                    <p class="text-muted text-center mt-2" style="font-size:.73rem;"><i class="fa-solid fa-shield-halved me-1"></i>Pending trainer confirmation</p>
                </div>

            </div>
        </div>
    </div>

</div>



</main></div></div></div>

<!-- Toast -->
<div id="bk-toast">
    <i class="fa-solid fa-circle-check fa-lg toast-icon"></i>
    <span id="bk-toast-msg">Message</span>
</div>

<!-- Success Overlay -->
<div id="successOverlay">
    <div class="success-card">
        <div class="success-icon"><i class="fa-solid fa-check"></i></div>
        <h4 class="fw-bold mb-1" style="color:#1a1a2e;">Booking Requested!</h4>
        <p class="text-muted mb-3" style="font-size:.9rem;">Your session has been submitted and is awaiting trainer confirmation. You'll be notified soon.</p>
        <a href="<?= SITE_URL ?>/user/bookings.php" class="confirm-btn" style="display:inline-block;text-decoration:none;padding:.75rem 2rem;">
            <i class="fa-solid fa-calendar-check me-2"></i>View My Bookings
        </a>
    </div>
</div>

<script>
const trainerNames = {<?php foreach($trainers as $t): ?> <?= $t['trainer_id'] ?>: "<?= htmlspecialchars(addslashes($t['full_name'])) ?>", <?php endforeach; ?>};
const trainerRates = {<?php foreach($trainerRates as $tid => $rate): ?> <?= $tid ?>: "<?= $rate ? '₹'.number_format((float)$rate,0).'/hr' : '' ?>", <?php endforeach; ?>};
const trainerSpecs = {<?php foreach($trainers as $t): ?> <?= $t['trainer_id'] ?>: "<?= htmlspecialchars(addslashes($t['specialization'])) ?>", <?php endforeach; ?>};
const trainerPhotos= {<?php foreach($trainers as $t): 
    $pUrl = '';
    if (!empty($t['photo'])) {
        $pUrl = filter_var($t['photo'], FILTER_VALIDATE_URL) ? $t['photo'] : SITE_URL . '/' . ltrim($t['photo'], '/');
    }
?> <?= $t['trainer_id'] ?>: "<?= htmlspecialchars($pUrl) ?>", <?php endforeach; ?>};
const BOOKING_CSRF = "<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>";

function showToast(msg, type='success') {
    const t = document.getElementById('bk-toast');
    document.getElementById('bk-toast-msg').textContent = msg;
    t.className = `show ${type}`;
    t.querySelector('.toast-icon').className = type==='success'
        ? 'fa-solid fa-circle-check fa-lg toast-icon'
        : 'fa-solid fa-circle-xmark fa-lg toast-icon';
    setTimeout(() => t.classList.remove('show'), 3500);
}

function updateStepProgress(step) {
    [1,2,3,4].forEach(i => {
        const el = document.getElementById('sp'+i);
        if (!el) return;
        el.classList.remove('active','done');
        if (i < step) el.classList.add('done');
        else if (i === step) el.classList.add('active');
    });
}

function selectTrainer(id) {
    document.querySelectorAll('.trainer-pick-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`.trainer-pick-card[data-trainer-id="${id}"]`);
    card?.classList.add('selected');
    document.getElementById('selectedTrainerId').value = id;

    // Update trainer panel
    const panel = document.getElementById('trainerSelectedPanel');
    const photo  = trainerPhotos[id];
    const imgEl  = document.getElementById('tspImg');
    if (photo) {
        imgEl.innerHTML = `<img src="${photo}" class="tsp-avatar" alt="">`;
    } else {
        const name = trainerNames[id] || '?';
        imgEl.innerHTML = `<div class="tsp-init">${name.charAt(0).toUpperCase()}</div>`;
    }
    document.getElementById('tspName').textContent = trainerNames[id] || '';
    document.getElementById('tspSpec').textContent = trainerSpecs[id] || '';
    const rateEl = document.getElementById('tspRate');
    const rate   = trainerRates[id];
    if (rate) { rateEl.textContent = rate; rateEl.style.display = 'inline-block'; }
    else { rateEl.style.display = 'none'; }
    panel.classList.add('show');

    updateStepProgress(2);
    loadSlots();
}

function loadSlots() {
    const trainerId = document.getElementById('selectedTrainerId').value;
    const date      = document.getElementById('dateSelect').value;
    if (!trainerId || !date) return;

    const label = document.getElementById('slotDateLabel');
    if (label) label.textContent = new Date(date+'T00:00').toLocaleDateString('en-IN',{weekday:'short',month:'short',day:'numeric'});

    document.getElementById('slotsContainer').innerHTML = `<div class="slots-empty"><i class="fa-solid fa-spinner fa-spin fa-2x mb-2 d-block" style="color:#FF6B35;"></i>Loading slots…</div>`;
    document.getElementById('selectedSlotStart').value = '';
    document.getElementById('selectedSlotEnd').value   = '';
    updateSummary();

    fetch(`<?= SITE_URL ?>/api/get-slots.php?trainer_id=${trainerId}&date=${date}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                renderSlotError(data.error);
                return;
            }
            if (!data.slots || data.slots.length === 0) {
                document.getElementById('slotsContainer').innerHTML = `<div class="slots-empty"><i class="fa-regular fa-calendar-xmark fa-2x mb-2 d-block" style="color:#e5e7eb;"></i>No available slots for this date.<br><small>The trainer may not have set up slots yet.</small></div>`;
                return;
            }
            renderSlots(data.slots);
            updateStepProgress(3);
        })
        .catch(() => { document.getElementById('slotsContainer').innerHTML = `<div class="slots-empty">Could not load slots. Please try again.</div>`; });
}

function slotBtn(s) {
    const booked = s.is_booked ? 'booked' : '';
    const dis    = s.is_booked ? 'disabled title="Already booked"' : `onclick="selectSlot(this,'${s.start}','${s.end}')"`;
    return `<button class="slot-btn ${booked}" ${dis}>${formatTime(s.start)}<br><small style="opacity:.7">${formatTime(s.end)}</small></button>`;
}

function selectSlot(el, start, end) {
    document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedSlotStart').value = start;
    document.getElementById('selectedSlotEnd').value   = end;
    updateSummary();
    updateStepProgress(4);
}

function updateSummary() {
    const tid   = document.getElementById('selectedTrainerId').value;
    const date  = document.getElementById('dateSelect').value;
    const start = document.getElementById('selectedSlotStart').value;
    const end   = document.getElementById('selectedSlotEnd').value;
    const ready = tid && date && start && end;
    const summary = document.getElementById('bookingSummary');
    const btn     = document.getElementById('confirmBookingBtn');
    if (ready) {
        summary.style.display = 'block';
        document.getElementById('summTrainer').textContent = trainerNames[tid] || '—';
        document.getElementById('summDate').textContent    = new Date(date+'T00:00').toLocaleDateString('en-IN',{weekday:'long',month:'short',day:'numeric',year:'numeric'});
        document.getElementById('summTime').textContent    = `${formatTime(start)} – ${formatTime(end)}`;
        const rateRow = document.getElementById('summRateRow');
        const rate    = trainerRates[parseInt(tid)];
        if (rate) { document.getElementById('summRate').textContent = rate+' (trainer rate)'; rateRow.style.display='flex'; }
        else { rateRow.style.display='none'; }
        btn.disabled = false;
    } else {
        summary.style.display = 'none';
        btn.disabled = true;
    }
}

function formatTime(t) {
    const [h,m] = t.split(':'); const hh=+h; const ampm=hh>=12?'PM':'AM';
    return `${hh%12||12}:${m} ${ampm}`;
}

document.getElementById('confirmBookingBtn')?.addEventListener('click', () => {
    const trainerId = document.getElementById('selectedTrainerId').value;
    const date      = document.getElementById('dateSelect').value;
    const start     = document.getElementById('selectedSlotStart').value;
    const end       = document.getElementById('selectedSlotEnd').value;
    const notes     = document.getElementById('bookingNotes').value;
    if (!trainerId || !date || !start) return;
    const btn = document.getElementById('confirmBookingBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Booking…';
    fetch('<?= SITE_URL ?>/api/book_session.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({trainer_id:parseInt(trainerId), date, start_time:start, end_time:end, notes, csrf_token: BOOKING_CSRF})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('successOverlay').classList.add('show');
        } else {
            showToast(data.message || 'Booking failed. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check me-2"></i>Confirm Booking';
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.','error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check me-2"></i>Confirm Booking';
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const tid = document.getElementById('selectedTrainerId')?.value;
    if (tid) { selectTrainer(parseInt(tid)); }
    updateStepProgress(1);
});

// ── Real-Time Slot Sync ──────────────────────────────────────────────────────
let slotPollTimer = null;
let lastSlotSnapshot = null;

function startSlotPolling() {
    stopSlotPolling();
    slotPollTimer = setInterval(pollSlots, 20000); // every 20 seconds
}
function stopSlotPolling() {
    if (slotPollTimer) { clearInterval(slotPollTimer); slotPollTimer = null; }
}

function pollSlots() {
    const trainerId = document.getElementById('selectedTrainerId').value;
    const date      = document.getElementById('dateSelect').value;
    if (!trainerId || !date) return;

    fetch(`<?= SITE_URL ?>/api/realtime-stats.php?mode=slots&trainer_id=${trainerId}&date=${date}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (data.error) {
                const snapshot = `error:${data.error}`;
                if (lastSlotSnapshot !== snapshot) {
                    renderSlotError(data.error);
                }
                lastSlotSnapshot = snapshot;
                return;
            }
            const snapshot = JSON.stringify(data.slots);
            if (lastSlotSnapshot !== null && snapshot !== lastSlotSnapshot) {
                // Slots changed — silently refresh the grid
                renderSlots(data.slots);
                showToast('Slot availability updated', 'info');
            }
            lastSlotSnapshot = snapshot;
        })
        .catch(() => {}); // silent fail — don't disrupt UX
}

function renderSlotError(message) {
    document.getElementById('slotsContainer').innerHTML = `<div class="slots-empty text-danger"><i class="fa-solid fa-umbrella-beach fa-2x mb-2 d-block"></i>${message}</div>`;

    if (document.getElementById('selectedSlotStart').value || document.getElementById('selectedSlotEnd').value) {
        document.getElementById('selectedSlotStart').value = '';
        document.getElementById('selectedSlotEnd').value   = '';
        updateSummary();
        showToast('Slot availability changed for this date.', 'error');
    }
}

function renderSlots(slots) {
    const container = document.getElementById('slotsContainer');
    if (!slots || slots.length === 0) {
        container.innerHTML = `<div class="slots-empty"><i class="fa-regular fa-calendar-xmark fa-2x mb-2 d-block" style="color:#e5e7eb;"></i>No available slots for this date</div>`;
        return;
    }
    const prevStart = document.getElementById('selectedSlotStart').value;
    const am = slots.filter(s => parseInt(s.start) < 12);
    const pm = slots.filter(s => parseInt(s.start) >= 12);
    let html = '';
    if (am.length) { html += '<div class="slot-period-label"><i class="fa-solid fa-sun me-1"></i>Morning</div><div class="slot-grid">'; am.forEach(s => html += slotBtnHtml(s, prevStart)); html += '</div>'; }
    if (pm.length) { html += '<div class="slot-period-label"><i class="fa-solid fa-moon me-1"></i>Afternoon / Evening</div><div class="slot-grid">'; pm.forEach(s => html += slotBtnHtml(s, prevStart)); html += '</div>'; }
    container.innerHTML = html;
    const stillAvailable = slots.find(s => s.start === prevStart && !s.is_booked);
    if (prevStart && !stillAvailable) {
        document.getElementById('selectedSlotStart').value = '';
        document.getElementById('selectedSlotEnd').value   = '';
        showToast('Your selected slot was just booked. Please choose another.', 'error');
        updateSummary();
    }
}

function slotBtnHtml(s, selectedStart) {
    const booked   = s.is_booked ? 'booked' : '';
    const selected = (!s.is_booked && s.start === selectedStart) ? 'selected' : '';
    const dis      = s.is_booked ? 'disabled title="Already booked"' : `onclick="selectSlot(this,'${s.start}','${s.end}')"`;
    return `<button class="slot-btn ${booked} ${selected}" ${dis}>${formatTime(s.start)}<br><small style="opacity:.7">${formatTime(s.end)}</small></button>`;
}

const _origSelectTrainer = window.selectTrainer;
window.selectTrainer = function(id) {
    _origSelectTrainer(id);
    lastSlotSnapshot = null;
    const date = document.getElementById('dateSelect').value;
    if (date) startSlotPolling();
};

document.getElementById('dateSelect')?.addEventListener('change', () => {
    lastSlotSnapshot = null;
    if (document.getElementById('selectedTrainerId').value) { loadSlots(); startSlotPolling(); }
});

document.getElementById('confirmBookingBtn')?.addEventListener('click', stopSlotPolling);
</script>

<?php require_once '../includes/footer.php'; ?>
