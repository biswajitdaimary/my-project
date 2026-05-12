<?php
$pageTitle = 'My Profile';
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/upload_helper.php';
require_user();

$user_id = $_SESSION['user_id'];
$error   = ''; $success = '';
$pwError = ''; $pwSuccess = '';

// ── Load user + membership ────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) { die("Database Error"); }

try {
    $memStmt = $pdo->prepare("
        SELECT um.*, mp.plan_name, mp.price, mp.duration_days, mp.features_json
        FROM user_memberships um
        LEFT JOIN membership_plans mp ON um.plan_id = mp.plan_id
        WHERE um.user_id = ? AND um.status = 'active'
        ORDER BY um.created_at DESC LIMIT 1
    ");
    $memStmt->execute([$user_id]);
    $membership = $memStmt->fetch();
} catch (PDOException $e) { $membership = null; }

// ── Profile Update ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone']     ?? '');
        $dob      = $_POST['date_of_birth']  ?: null;
        $gender   = $_POST['gender']         ?? '';

        if ($fullName === '') {
            $error = 'Full name is required.';
        } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s().]{7,25}$/', $phone)) {
            $error = 'Enter a valid phone number.';
        } else {
            // Handle photo upload
            $photoPath = $user['profile_photo'];
            if (!empty($_FILES['profile_photo']['name'])) {
                [$newPath, $uploadErr] = upload_image($_FILES['profile_photo'], 'profiles', 'user', 10 * 1024 * 1024);
                if ($uploadErr !== '') {
                    $error = $uploadErr;
                } elseif ($newPath !== '') {
                    upload_delete_old((string)($user['profile_photo'] ?? ''));
                    $photoPath = $newPath;
                }
            }

            if ($error === '') {
                try {
                    $pdo->prepare("
                        UPDATE users SET full_name=?, phone=?, date_of_birth=?, gender=?, profile_photo=?
                        WHERE user_id=?
                    ")->execute([$fullName, $phone ?: null, $dob, $gender ?: null, $photoPath, $user_id]);
                    $_SESSION['full_name'] = $fullName;
                    $success = 'Profile updated successfully.';
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                } catch (PDOException $e) { $error = 'Failed to update profile.'; }
            }
        }
    }
}

// ── Password Change ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $pwError = 'Invalid security token.';
    } else {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password']     ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if (!$currentPw || !$newPw || !$confirmPw) {
            $pwError = 'All password fields are required.';
        } elseif (!password_verify($currentPw, $user['password_hash'])) {
            $pwError = 'Your current password is incorrect.';
        } elseif (strlen($newPw) < 8) {
            $pwError = 'New password must be at least 8 characters.';
        } elseif ($newPw !== $confirmPw) {
            $pwError = 'New passwords do not match.';
        } else {
            try {
                $pdo->prepare("UPDATE users SET password_hash=? WHERE user_id=?")
                    ->execute([password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]), $user_id]);
                $pwSuccess = 'Password changed successfully.';
            } catch (PDOException $e) { $pwError = 'Failed to update password.'; }
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────
$photoSrc = null;
if (!empty($user['profile_photo'])) {
    $p = $user['profile_photo'];
    $photoSrc = str_starts_with($p, 'http') ? $p : SITE_URL . '/' . ltrim($p, '/');
}
$initials   = strtoupper(substr($user['full_name'] ?? 'U', 0, 1));
$memFeatures = [];
if ($membership && !empty($membership['features_json'])) {
    $memFeatures = json_decode($membership['features_json'], true) ?? [];
}
$daysLeft = 0;
if ($membership && !empty($membership['end_date'])) {
    $daysLeft = max(0, (int) ceil((strtotime($membership['end_date']) - time()) / 86400));
}

require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<div class="up-wrap">
    <div class="container-fluid px-0">
        <div class="d-flex">
            <?php require_once '../includes/sidebar-user.php'; ?>

            <main class="up-main flex-grow-1">
                <div class="container-fluid" style="max-width: 900px; margin: 0 auto;">

                <!-- ── Profile Card Header ──────────────────────────────── -->
                <div class="profile-hero-card mb-4">
                    <div class="profile-hero-bg"></div>
                    <div class="profile-hero-body">
                        <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-end gap-4">
                            <div class="profile-avatar-wrapper">
                                <?php if ($photoSrc): ?>
                                    <img src="<?= htmlspecialchars($photoSrc) ?>" id="avatarPreviewImg"
                                         alt="Profile Photo" class="profile-avatar-img">
                                <?php else: ?>
                                    <div class="profile-avatar-initials" id="avatarInitials"><?= $initials ?></div>
                                    <img id="avatarPreviewImg" src="" alt="" class="profile-avatar-img d-none">
                                <?php endif; ?>
                                <label for="photoInputHero" class="profile-avatar-edit-btn" title="Change Photo">
                                    <i class="fa-solid fa-camera"></i>
                                </label>
                            </div>
                            <div class="flex-grow-1 text-center text-sm-start">
                                <h4 class="fw-bold mb-1 text-white"><?= htmlspecialchars($user['full_name']) ?></h4>
                                <p class="text-white-50 mb-2 small"><?= htmlspecialchars($user['email']) ?></p>
                                <div class="d-flex flex-wrap justify-content-center justify-content-sm-start gap-2">
                                    <span class="profile-meta-badge">
                                        <i class="fa-solid fa-calendar-days me-1"></i>
                                        Joined <?= date('M Y', strtotime($user['created_at'])) ?>
                                    </span>
                                    <?php if (!empty($user['custom_id'])): ?>
                                    <span class="profile-meta-badge" style="background:rgba(99,102,241,0.25);color:#c7d2fe;letter-spacing:1px;font-family:monospace;">
                                        <i class="fa-solid fa-fingerprint me-1"></i>
                                        <?= htmlspecialchars($user['custom_id']) ?>
                                    </span>
                                    <?php endif; ?>
                                    <span class="profile-meta-badge <?= $user['is_active'] ? 'active' : 'blocked' ?>">
                                        <i class="fa-solid fa-circle me-1" style="font-size:0.5rem;vertical-align:middle;"></i>
                                        <?= $user['is_active'] ? 'Active Member' : 'Account Blocked' ?>
                                    </span>
                                    <?php if ($membership): ?>
                                        <span class="profile-meta-badge plan">
                                            <i class="fa-solid fa-crown me-1"></i>
                                            <?= htmlspecialchars($membership['plan_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Membership Card ─────────────────────────────────── -->
                <?php if ($membership): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="membership-banner d-flex flex-wrap justify-content-between align-items-center gap-3 p-4">
                            <div>
                                <div class="small text-white-50 text-uppercase fw-semibold mb-1">Active Plan</div>
                                <h5 class="fw-bold text-white mb-0">
                                    <i class="fa-solid fa-crown me-2 text-warning"></i>
                                    <?= htmlspecialchars($membership['plan_name']) ?>
                                </h5>
                                <div class="small text-white-50 mt-1">
                                    <?= date('M d, Y', strtotime($membership['start_date'])) ?> —
                                    <?= date('M d, Y', strtotime($membership['end_date'])) ?>
                                </div>
                            </div>
                            <div class="text-center text-sm-end">
                                <div class="display-6 fw-bold text-white"><?= $daysLeft ?></div>
                                <div class="small text-white-50">days remaining</div>
                                <div class="progress mt-2" style="height:6px;width:120px;background:rgba(255,255,255,0.2);">
                                    <?php
                                    $totalDays = max(1, (int)$membership['duration_days']);
                                    $pct = min(100, round(($daysLeft / $totalDays) * 100));
                                    ?>
                                    <div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($memFeatures)): ?>
                        <div class="px-4 py-3 bg-white">
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($memFeatures as $feat): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-normal px-3 py-2 rounded-pill">
                                        <i class="fa-solid fa-check me-1"></i><?= htmlspecialchars($feat) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4 d-flex align-items-center gap-4">
                        <div class="no-plan-icon"><i class="fa-solid fa-id-card"></i></div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1">No Active Membership</h6>
                            <p class="text-muted small mb-0">You don't have an active plan. Join one to unlock all features.</p>
                        </div>
                        <a href="<?= SITE_URL ?>/plans.php" class="btn btn-primary-custom rounded-pill px-4 flex-shrink-0">View Plans</a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── Edit Profile Form ───────────────────────────────── -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-user-pen me-2 text-primary-custom"></i>Personal Information</h5>
                        <p class="text-muted small mb-4">Update your name, contact number, and personal details.</p>

                        <?php if ($error): ?>
                            <div class="alert alert-danger rounded-3 small"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success rounded-3 small"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="profile.php" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="update_profile">

                            <!-- Hidden photo input for the hero area -->
                            <input type="file" name="profile_photo" id="photoInputHero" class="d-none" accept="image/*">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                                        <input type="text" name="full_name" class="form-control"
                                            value="<?= htmlspecialchars($user['full_name']) ?>" required maxlength="255">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
                                        <input type="email" class="form-control bg-light text-muted"
                                            value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                    </div>
                                    <div class="form-text">Contact admin to change your email.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-solid fa-phone text-muted"></i></span>
                                        <input type="tel" name="phone" class="form-control"
                                            value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                            placeholder="+91 98765 43210"
                                            pattern="[0-9+\-\s().]{7,25}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">Date of Birth</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-solid fa-cake-candles text-muted"></i></span>
                                        <input type="date" name="date_of_birth" class="form-control"
                                            value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>"
                                            max="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">Gender</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-solid fa-venus-mars text-muted"></i></span>
                                        <select name="gender" class="form-select">
                                            <option value="">— Not specified —</option>
                                            <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $v => $l): ?>
                                                <option value="<?= $v ?>" <?= ($user['gender'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">Member Since</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-solid fa-calendar text-muted"></i></span>
                                        <input type="text" class="form-control bg-light text-muted" readonly
                                            value="<?= date('F d, Y', strtotime($user['created_at'])) ?>">
                                    </div>
                                </div>
                                <?php if (!empty($user['custom_id'])): ?>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">Your Member ID <span class="text-muted fw-normal">(permanent)</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-solid fa-fingerprint text-primary"></i></span>
                                        <input type="text" class="form-control bg-light fw-bold text-primary" readonly
                                            value="<?= htmlspecialchars($user['custom_id']) ?>"
                                            style="letter-spacing:2px;font-family:monospace;">
                                        <button type="button" class="btn btn-outline-secondary" title="Copy ID"
                                            onclick="navigator.clipboard.writeText('<?= htmlspecialchars($user['custom_id']) ?>');this.innerHTML='<i class=\'fa-solid fa-check\'></i>';setTimeout(()=>this.innerHTML='<i class=\'fa-solid fa-copy\'></i>',1500)">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">This ID is permanent and cannot be changed.</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary-custom px-5 rounded-pill">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── Change Password ────────────────────────────────── -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-lock me-2 text-primary-custom"></i>Change Password</h5>
                        <p class="text-muted small mb-4">Choose a strong password with at least 8 characters.</p>

                        <?php if ($pwError): ?>
                            <div class="alert alert-danger rounded-3 small"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($pwError) ?></div>
                        <?php endif; ?>
                        <?php if ($pwSuccess): ?>
                            <div class="alert alert-success rounded-3 small"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($pwSuccess) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="profile.php" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="change_password">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">Current Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                                        <input type="password" name="current_password" class="form-control" required placeholder="••••••••">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">New Password <span class="text-muted fw-normal">(min 8 chars)</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-solid fa-key text-muted"></i></span>
                                        <input type="password" name="new_password" id="newPw" class="form-control" required placeholder="••••••••">
                                    </div>
                                    <!-- Strength bar -->
                                    <div class="mt-2" id="pwStrengthBar" style="display:none;">
                                        <div class="progress" style="height:4px;">
                                            <div class="progress-bar" id="pwStrengthFill" style="width:0%;transition:width 0.3s;"></div>
                                        </div>
                                        <small id="pwStrengthLabel" class="text-muted"></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa-solid fa-check-double text-muted"></i></span>
                                        <input type="password" name="confirm_password" class="form-control" required placeholder="••••••••">
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-outline-dark px-5 rounded-pill">
                                    <i class="fa-solid fa-shield-halved me-2"></i>Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                </div><!-- /.container-fluid -->
            </main><!-- /.up-main -->
        </div><!-- /.d-flex -->
    </div><!-- /.container-fluid -->
</div><!-- /.up-wrap -->

<style>
/* Hero card */
.profile-hero-card { position:relative; border-radius:1.5rem; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.10); }
.profile-hero-bg { position:absolute; inset:0; background:linear-gradient(135deg,#1A1A2E 0%,#FF6B35 100%); z-index:0; }
.profile-hero-body { position:relative; z-index:1; padding:2rem 2rem 1.5rem; }

/* Avatar */
.profile-avatar-wrapper { position:relative; flex-shrink:0; }
.profile-avatar-img { width:100px; height:100px; border-radius:50%; border:4px solid rgba(255,255,255,0.35); object-fit:cover; display:block; }
.profile-avatar-initials { width:100px; height:100px; border-radius:50%; border:4px solid rgba(255,255,255,0.35); background:rgba(255,107,53,0.2); color:#FF6B35; font-size:2.2rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
.profile-avatar-edit-btn { position:absolute; bottom:4px; right:4px; width:30px; height:30px; border-radius:50%; background:#FF6B35; color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:0.75rem; border:2px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,0.2); transition:transform .2s; }
.profile-avatar-edit-btn:hover { transform:scale(1.1); }

/* Meta badges */
.profile-meta-badge { display:inline-flex; align-items:center; font-size:0.78rem; font-weight:600; padding:0.3rem 0.75rem; border-radius:999px; background:rgba(255,255,255,0.12); color:#fff; gap:0.3rem; }
.profile-meta-badge.active { background:rgba(25,200,100,0.25); color:#a0f0c0; }
.profile-meta-badge.blocked { background:rgba(220,53,69,0.25); color:#ffaaaa; }
.profile-meta-badge.plan { background:rgba(255,193,7,0.2); color:#ffe07a; }

/* Membership banner */
.membership-banner { background:linear-gradient(135deg,#1A1A2E 0%,#16213e 100%); }

/* No plan icon */
.no-plan-icon { width:54px; height:54px; border-radius:50%; background:rgba(255,107,53,0.1); color:#FF6B35; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
</style>

<script>
// Live avatar preview from hero camera button
document.getElementById('photoInputHero').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('avatarPreviewImg');
        const initials = document.getElementById('avatarInitials');
        img.src = e.target.result;
        img.classList.remove('d-none');
        if (initials) initials.classList.add('d-none');
    };
    reader.readAsDataURL(file);
});

// Password strength indicator
const newPwInput = document.getElementById('newPw');
if (newPwInput) {
    newPwInput.addEventListener('input', function() {
        const val = this.value;
        const bar  = document.getElementById('pwStrengthBar');
        const fill = document.getElementById('pwStrengthFill');
        const lbl  = document.getElementById('pwStrengthLabel');
        if (!val) { bar.style.display = 'none'; return; }
        bar.style.display = 'block';
        let score = 0;
        if (val.length >= 8)  score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        const levels = [
            { pct:'25%', cls:'bg-danger',  txt:'Weak' },
            { pct:'50%', cls:'bg-warning', txt:'Fair' },
            { pct:'75%', cls:'bg-info',    txt:'Good' },
            { pct:'100%',cls:'bg-success', txt:'Strong' },
        ];
        const lvl = levels[Math.max(0, score - 1)];
        fill.style.width = lvl.pct;
        fill.className   = 'progress-bar ' + lvl.cls;
        lbl.textContent  = lvl.txt;
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
