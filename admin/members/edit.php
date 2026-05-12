<?php
require_once '../../config/config.php';
require_once '../../helpers/auth_check.php';
require_once '../../helpers/upload_helper.php';
require_admin();

$pageTitle = 'Edit Member';
$userId    = (int) ($_GET['id'] ?? 0);
$success   = '';
$error     = '';
$fieldErrors = [];

if ($userId <= 0) {
    header('Location: ../members.php');
    exit;
}

// Load member
try {
    $memberStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'user'");
    $memberStmt->execute([$userId]);
    $member = $memberStmt->fetch();
    if (!$member) {
        header('Location: ../members.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: ../members.php');
    exit;
}

// Load all plans for dropdown
try {
    $plans = $pdo->query("SELECT plan_id, plan_name FROM membership_plans WHERE is_active = 1 ORDER BY price ASC")->fetchAll();
} catch (PDOException $e) {
    $plans = [];
}

// Load current active membership
try {
    $memStmt = $pdo->prepare("
        SELECT um.*, mp.plan_name
        FROM user_memberships um
        LEFT JOIN membership_plans mp ON um.plan_id = mp.plan_id
        WHERE um.user_id = ? AND um.status = 'active'
        ORDER BY um.created_at DESC LIMIT 1
    ");
    $memStmt->execute([$userId]);
    $membership = $memStmt->fetch();
} catch (PDOException $e) {
    $membership = null;
}

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Sanitize inputs
        $fullName  = trim($_POST['full_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $phone     = trim($_POST['phone']      ?? '');
        $gender    = $_POST['gender']          ?? '';
        $dob       = $_POST['date_of_birth']   ?? '';
        $isActive  = isset($_POST['is_active']) ? 1 : 0;
        $planId    = (int) ($_POST['plan_id']  ?? 0);
        $startDate = $_POST['start_date']      ?? '';
        $endDate   = $_POST['end_date']        ?? '';

        // Validation
        if ($fullName === '') {
            $fieldErrors['full_name'] = 'Full name is required.';
        } elseif (strlen($fullName) > 255) {
            $fieldErrors['full_name'] = 'Name must be 255 characters or fewer.';
        }

        if ($email === '') {
            $fieldErrors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fieldErrors['email'] = 'Enter a valid email address.';
        } else {
            // Check duplicate email (excluding this user)
            $dupStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $dupStmt->execute([$email, $userId]);
            if ($dupStmt->fetch()) {
                $fieldErrors['email'] = 'This email is already in use by another member.';
            }
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s().]{7,25}$/', $phone)) {
            $fieldErrors['phone'] = 'Enter a valid phone number.';
        }

        if (!in_array($gender, ['male', 'female', 'other', ''], true)) {
            $fieldErrors['gender'] = 'Select a valid gender.';
        }

        if ($dob !== '' && !DateTime::createFromFormat('Y-m-d', $dob)) {
            $fieldErrors['date_of_birth'] = 'Enter a valid date of birth.';
        }

        // Profile photo upload — uses shared upload_helper (10 MB, JPG/JPEG/PNG/WebP, unique filename)
        $photoPath = $member['profile_photo'];
        if (!empty($_FILES['profile_photo']['name'])) {
            [$newPath, $uploadErr] = upload_image($_FILES['profile_photo'], 'members', 'member', 10 * 1024 * 1024);
            if ($uploadErr !== '') {
                $fieldErrors['profile_photo'] = $uploadErr;
            } elseif ($newPath !== '') {
                upload_delete_old((string)($member['profile_photo'] ?? ''));
                $photoPath = $newPath;
            }
        }

        if (empty($fieldErrors)) {
            try {
                $pdo->beginTransaction();

                // Update user record
                $updateStmt = $pdo->prepare("
                    UPDATE users SET
                        full_name     = ?,
                        email         = ?,
                        phone         = ?,
                        gender        = ?,
                        date_of_birth = ?,
                        is_active     = ?,
                        profile_photo = ?
                    WHERE user_id = ?
                ");
                $updateStmt->execute([
                    $fullName,
                    $email,
                    $phone ?: null,
                    $gender ?: null,
                    $dob ?: null,
                    $isActive,
                    $photoPath,
                    $userId,
                ]);

                // Update or create membership if plan selected
                if ($planId > 0 && $startDate !== '' && $endDate !== '') {
                    if ($membership) {
                        // Update existing
                        $pdo->prepare("
                            UPDATE user_memberships SET plan_id = ?, start_date = ?, end_date = ?
                            WHERE membership_id = ?
                        ")->execute([$planId, $startDate, $endDate, $membership['membership_id']]);
                    } else {
                        // Create new membership record
                        $pdo->prepare("
                            INSERT INTO user_memberships (user_id, plan_id, start_date, end_date, status)
                            VALUES (?, ?, ?, ?, 'active')
                        ")->execute([$userId, $planId, $startDate, $endDate]);
                    }
                }

                $pdo->commit();

                // Reload member data
                $memberStmt->execute([$userId]);
                $member = $memberStmt->fetch();
                $memStmt->execute([$userId]);
                $membership = $memStmt->fetch();

                $success = 'Member details updated successfully.';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: Could not save changes. Please try again.';
            }
        } else {
            $error = 'Please fix the highlighted errors below.';
        }
    }
}

$fieldClass = fn(string $k, string $base = 'form-control') =>
    $base . (isset($fieldErrors[$k]) ? ' is-invalid' : '');

$val = fn(string $k) => htmlspecialchars((string)($member[$k] ?? ''), ENT_QUOTES, 'UTF-8');

require_once '../includes/admin_header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="../members.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Members
    </a>
    <div>
        <h3 class="fw-bold m-0">Edit Member</h3>
        <p class="text-muted small mb-0">Update member profile, status, and membership plan.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success rounded-4"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="row g-4">
        <!-- LEFT: Personal Details -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-user me-2 text-primary-custom"></i>Personal Information
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="<?= $fieldClass('full_name') ?>"
                                value="<?= $val('full_name') ?>" maxlength="255" required>
                            <?php if (isset($fieldErrors['full_name'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['full_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="<?= $fieldClass('email') ?>"
                                value="<?= $val('email') ?>" required>
                            <?php if (isset($fieldErrors['email'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Phone Number</label>
                            <input type="tel" name="phone" class="<?= $fieldClass('phone') ?>"
                                value="<?= $val('phone') ?>" placeholder="+91 98765 43210"
                                pattern="[0-9+\-\s().]{7,25}">
                            <?php if (isset($fieldErrors['phone'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['phone']) ?></div>
                            <?php else: ?>
                                <div class="form-text">Optional. Use international format.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Gender</label>
                            <select name="gender" class="<?= $fieldClass('gender', 'form-select') ?>">
                                <option value="">— Not specified —</option>
                                <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $member['gender'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($fieldErrors['gender'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['gender']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="<?= $fieldClass('date_of_birth') ?>"
                                value="<?= $val('date_of_birth') ?>" max="<?= date('Y-m-d') ?>">
                            <?php if (isset($fieldErrors['date_of_birth'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['date_of_birth']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Account Status</label>
                            <div class="d-flex gap-3 mt-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        name="is_active" id="isActiveToggle"
                                        <?= ($member['is_active'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="isActiveToggle" id="activeLabel">
                                        <?= ($member['is_active'] ?? 1) ? 'Active' : 'Blocked' ?>
                                    </label>
                                </div>
                            </div>
                            <div class="form-text">Toggle off to block this member from logging in.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Membership Plan Section -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-1 border-bottom pb-3">
                        <i class="fa-solid fa-id-card me-2 text-primary-custom"></i>Membership Plan
                    </h5>
                    <?php if ($membership): ?>
                        <div class="alert alert-info border-0 rounded-3 small mb-4">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            Currently on <strong><?= htmlspecialchars($membership['plan_name']) ?></strong>
                            — expires <strong><?= date('M d, Y', strtotime($membership['end_date'])) ?></strong>.
                            Changing the plan below will update the existing membership.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning border-0 rounded-3 small mb-4">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i>
                            This member has no active membership. Assign one below.
                        </div>
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Membership Plan</label>
                            <select name="plan_id" class="form-select">
                                <option value="0">— No change / Remove plan —</option>
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?= $plan['plan_id'] ?>"
                                        <?= ($membership && $membership['plan_id'] == $plan['plan_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($plan['plan_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select a plan and set start/end dates to assign or update membership.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Start Date</label>
                            <input type="date" name="start_date" class="form-control"
                                value="<?= htmlspecialchars($membership['start_date'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">End Date</label>
                            <input type="date" name="end_date" class="form-control"
                                value="<?= htmlspecialchars($membership['end_date'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Photo + Meta -->
        <div class="col-lg-4">
            <!-- Profile Photo Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 text-center">
                    <h6 class="fw-bold mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-camera me-2 text-primary-custom"></i>Profile Photo
                    </h6>
                    <div class="mb-3">
                        <?php
                        $photo = $member['profile_photo'] ?? '';
                        $isUrl = str_starts_with($photo, 'http');
                        $photoSrc = $photo
                            ? ($isUrl ? $photo : SITE_URL . '/' . ltrim($photo, '/'))
                            : null;
                        ?>
                        <?php if ($photoSrc): ?>
                            <img src="<?= htmlspecialchars($photoSrc) ?>"
                                id="photoPreview"
                                alt="Profile Photo"
                                class="rounded-circle border shadow-sm"
                                style="width:100px;height:100px;object-fit:cover;">
                        <?php else: ?>
                            <div id="photoPlaceholder"
                                class="rounded-circle border mx-auto d-flex align-items-center justify-content-center fw-bold fs-1"
                                style="width:100px;height:100px;background:rgba(255,107,53,0.12);color:#FF6B35;">
                                <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
                            </div>
                            <img id="photoPreview" src="" alt="" class="rounded-circle border shadow-sm d-none"
                                style="width:100px;height:100px;object-fit:cover;">
                        <?php endif; ?>
                    </div>
                    <label for="photoInput" class="btn btn-outline-secondary btn-sm rounded-pill px-3 mb-2">
                        <i class="fa-solid fa-upload me-1"></i> Choose Photo
                    </label>
                    <input type="file" name="profile_photo" id="photoInput" class="d-none" accept="image/*">
                    <div class="form-text">JPG, JPEG, PNG, WebP — max 10 MB</div>
                    <?php if (isset($fieldErrors['profile_photo'])): ?>
                        <div class="text-danger small mt-1"><?= htmlspecialchars($fieldErrors['profile_photo']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Member Meta Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">Member Info</h6>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Member ID</span>
                        <span class="fw-bold">#<?= $member['user_id'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Joined</span>
                        <span class="fw-bold"><?= date('M d, Y', strtotime($member['created_at'])) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Email Verified</span>
                        <span class="fw-bold"><?= $member['is_verified'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Current Plan</span>
                        <span class="fw-bold"><?= $membership ? htmlspecialchars($membership['plan_name']) : '—' ?></span>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="d-grid">
                <button type="submit" class="btn btn-primary-custom py-3 rounded-pill fw-bold">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Update Member
                </button>
                <a href="../members.php" class="btn btn-outline-secondary mt-2 rounded-pill">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</form>

<script>
// Live photo preview
document.getElementById('photoInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    const reader = new FileReader();
    reader.onload = e => {
        preview.src = e.target.result;
        preview.classList.remove('d-none');
        if (placeholder) placeholder.classList.add('d-none');
    };
    reader.readAsDataURL(file);
});

// Active/inactive label toggle
document.getElementById('isActiveToggle').addEventListener('change', function() {
    document.getElementById('activeLabel').textContent = this.checked ? 'Active' : 'Blocked';
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>
