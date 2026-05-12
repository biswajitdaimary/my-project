<?php
require_once '../../config/config.php';
require_once '../../helpers/auth_check.php';
require_once '../../helpers/upload_helper.php';
require_admin();

$pageTitle   = 'Edit Trainer';
$trainerId   = (int) ($_GET['id'] ?? 0);
$success     = '';
$error       = '';
$fieldErrors = [];

if ($trainerId <= 0) { header('Location: ../trainers.php'); exit; }

// Load trainer
try {
    $stmt = $pdo->prepare("SELECT * FROM trainers WHERE trainer_id = ?");
    $stmt->execute([$trainerId]);
    $trainer = $stmt->fetch();
    if (!$trainer) { header('Location: ../trainers.php'); exit; }
} catch (PDOException $e) { header('Location: ../trainers.php'); exit; }

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $fullName   = trim($_POST['full_name']      ?? '');
        $email      = trim($_POST['email']          ?? '');
        $phone      = trim($_POST['phone']          ?? '');
        $spec       = trim($_POST['specialization'] ?? '');
        $bio        = trim($_POST['bio']            ?? '');
        $expYears   = $_POST['experience_years']    ?? '';
        $hourlyRate = $_POST['hourly_rate']         ?? '';
        $rating     = (float)($_POST['rating']      ?? 5.0);
        $isActive   = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        if ($fullName === '')  $fieldErrors['full_name']      = 'Full name is required.';
        if ($email === '')     $fieldErrors['email']          = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
                               $fieldErrors['email']          = 'Enter a valid email address.';
        if ($spec === '')      $fieldErrors['specialization'] = 'Specialization is required.';
        if ($rating < 1 || $rating > 5)
                               $fieldErrors['rating']         = 'Rating must be between 1.0 and 5.0.';
        if ($expYears !== '' && (!is_numeric($expYears) || (int)$expYears < 0 || (int)$expYears > 60))
                               $fieldErrors['experience_years'] = 'Enter a valid number (0–60).';
        if ($hourlyRate !== '' && (!is_numeric($hourlyRate) || (float)$hourlyRate < 0))
                               $fieldErrors['hourly_rate']    = 'Enter a valid positive amount.';

        // Email duplicate (exclude self)
        if (!isset($fieldErrors['email'])) {
            $dup = $pdo->prepare("SELECT trainer_id FROM trainers WHERE email = ? AND trainer_id != ?");
            $dup->execute([$email, $trainerId]);
            if ($dup->fetch()) $fieldErrors['email'] = 'This email is used by another trainer.';
        }

        // Photo upload — uses shared upload_helper (10 MB, JPG/JPEG/PNG/WebP, unique filename)
        $photoPath = $trainer['photo'];

        // Handle photo removal request
        if (!empty($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
            upload_delete_old((string)($trainer['photo'] ?? ''));
            $photoPath = null;
        } elseif (!empty($_FILES['photo']['name'])) {
            [$newPath, $uploadErr] = upload_image($_FILES['photo'], 'profiles', 'trainer', 10 * 1024 * 1024);
            if ($uploadErr !== '') {
                $fieldErrors['photo'] = $uploadErr;
            } elseif ($newPath !== '') {
                // Delete old local photo safely
                upload_delete_old((string)($trainer['photo'] ?? ''));
                $photoPath = $newPath;
            }
        }

        if (empty($fieldErrors)) {
            // Optional password reset
            $pwdSql = '';
            $pwdParams = [];
            $newPwd = trim($_POST['new_password'] ?? '');
            $confirmNewPwd = trim($_POST['confirm_new_password'] ?? '');
            if ($newPwd !== '') {
                if (strlen($newPwd) < 8) {
                    $fieldErrors['new_password'] = 'Password must be at least 8 characters.';
                } elseif ($newPwd !== $confirmNewPwd) {
                    $fieldErrors['confirm_new_password'] = 'Passwords do not match.';
                } else {
                    $pwdSql = ', password_hash = ?';
                    $pwdParams = [password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12])];
                }
            }

            if (empty($fieldErrors)) {
            try {
                $pdo->prepare("
                    UPDATE trainers SET
                        full_name        = ?,
                        email            = ?,
                        phone            = ?,
                        specialization   = ?,
                        experience_years = ?,
                        hourly_rate      = ?,
                        bio              = ?,
                        photo            = ?,
                        rating           = ?,
                        is_active        = ?
                        $pwdSql
                    WHERE trainer_id = ?
                ")->execute(array_merge([
                    $fullName, $email, $phone ?: null,
                    $spec,
                    $expYears   !== '' ? (int)$expYears   : null,
                    $hourlyRate !== '' ? (float)$hourlyRate : null,
                    $bio ?: null, $photoPath,
                    $rating, $isActive,
                ], $pwdParams, [$trainerId]));

                // Reload fresh data
                $stmt->execute([$trainerId]);
                $trainer = $stmt->fetch();
                $success = 'Trainer updated successfully.' . ($newPwd ? ' Password was also reset.' : '');
            } catch (PDOException $e) {
                $error = 'Database error. Could not save changes.';
            }
            } // end inner if
        } else {
            $error = 'Please fix the highlighted errors.';
        }

        if ($success === '') {
            $trainer = array_merge($trainer, [
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'specialization' => $spec,
                'bio' => $bio,
                'experience_years' => $expYears,
                'hourly_rate' => $hourlyRate,
                'rating' => (string) $rating,
                'is_active' => $isActive,
                'photo' => $photoPath,
            ]);
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────
$fieldClass = fn(string $k, string $b = 'form-control') => $b . (isset($fieldErrors[$k]) ? ' is-invalid' : '');
$val        = fn(string $k) => htmlspecialchars((string)($trainer[$k] ?? ''), ENT_QUOTES, 'UTF-8');

$photo    = $trainer['photo'] ?? '';
$isUrl    = str_starts_with($photo, 'http');
$photoSrc = $photo ? ($isUrl ? $photo : SITE_URL . '/' . ltrim($photo, '/')) : null;

require_once '../includes/admin_header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="../trainers.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <div>
        <h3 class="fw-bold m-0">Edit Trainer</h3>
        <p class="text-muted small mb-0">Update <?= htmlspecialchars($trainer['full_name']) ?>'s profile and photo.</p>
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

        <!-- LEFT: Main Details -->
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
                            <label class="form-label fw-bold small">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="<?= $fieldClass('email') ?>"
                                value="<?= $val('email') ?>" required>
                            <?php if (isset($fieldErrors['email'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Phone</label>
                            <input type="tel" name="phone" class="<?= $fieldClass('phone') ?>"
                                value="<?= $val('phone') ?>" placeholder="+91 98765 43210">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Specialization <span class="text-danger">*</span></label>
                            <input type="text" name="specialization" class="<?= $fieldClass('specialization') ?>"
                                value="<?= $val('specialization') ?>"
                                placeholder="e.g. Yoga, CrossFit, Bodybuilding" required>
                            <?php if (isset($fieldErrors['specialization'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['specialization']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Experience (Years)</label>
                            <input type="number" name="experience_years" class="<?= $fieldClass('experience_years') ?>"
                                value="<?= $val('experience_years') ?>" min="0" max="60" placeholder="e.g. 5">
                            <?php if (isset($fieldErrors['experience_years'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['experience_years']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Hourly Rate (₹)</label>
                            <input type="number" name="hourly_rate" class="<?= $fieldClass('hourly_rate') ?>"
                                value="<?= $val('hourly_rate') ?>" min="0" step="50" placeholder="e.g. 500">
                            <?php if (isset($fieldErrors['hourly_rate'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['hourly_rate']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Rating (1.0 – 5.0)</label>
                            <input type="number" name="rating" class="<?= $fieldClass('rating') ?>"
                                value="<?= $val('rating') ?>" step="0.1" min="1.0" max="5.0" required>
                            <?php if (isset($fieldErrors['rating'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['rating']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Bio / Description</label>
                            <textarea name="bio" class="<?= $fieldClass('bio') ?>" rows="4"
                                placeholder="Brief professional background..."><?= $val('bio') ?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch pt-1">
                                <input class="form-check-input" type="checkbox" role="switch"
                                    name="is_active" id="isActiveToggle"
                                    <?= ($trainer['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="isActiveToggle" id="activeLabel">
                                    <?= ($trainer['is_active'] ?? 1) ? 'Active — visible on website' : 'Inactive — hidden from website' ?>
                                </label>
                            </div>
                            <div class="form-text">Inactive trainers are hidden from the public trainers page.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Photo + Meta + Save -->
        <div class="col-lg-4">
            <!-- Photo Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 text-center">
                    <h6 class="fw-bold mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-camera me-2 text-primary-custom"></i>Profile Photo
                    </h6>
                    <div class="mb-3">
                        <?php if ($photoSrc): ?>
                            <img src="<?= htmlspecialchars($photoSrc) ?>" id="photoPreview"
                                 alt="Trainer Photo" class="trainer-photo-preview mx-auto mb-3">
                            <div id="photoPlaceholder" class="trainer-photo-placeholder mx-auto mb-3 d-none">
                                <i class="fa-solid fa-person-running fa-2x"></i>
                            </div>
                        <?php else: ?>
                            <div id="photoPlaceholder" class="trainer-photo-placeholder mx-auto mb-3">
                                <i class="fa-solid fa-person-running fa-2x"></i>
                            </div>
                            <img id="photoPreview" src="" alt="" class="trainer-photo-preview mx-auto mb-3 d-none">
                        <?php endif; ?>
                    </div>

                    <!-- Hidden flag sent when admin removes the photo -->
                    <input type="hidden" name="remove_photo" id="removePhotoFlag" value="0">

                    <div class="d-flex flex-column align-items-center gap-2">
                        <label for="photoInput" class="btn btn-outline-secondary btn-sm rounded-pill px-4">
                            <i class="fa-solid fa-upload me-1"></i>
                            <span id="uploadBtnLabel"><?= $photoSrc ? 'Change Photo' : 'Upload Photo' ?></span>
                        </label>
                        <input type="file" name="photo" id="photoInput" class="d-none" accept="image/*">

                        <!-- Remove button — only shown when a photo exists -->
                        <button type="button" id="removePhotoBtn"
                                class="btn btn-sm btn-outline-danger rounded-pill px-4<?= $photoSrc ? '' : ' d-none' ?>"
                                onclick="confirmRemovePhoto()">
                            <i class="fa-solid fa-trash-can me-1"></i>Remove Photo
                        </button>
                    </div>

                    <div class="form-text mt-2">JPG, JPEG, PNG, WebP — max 10 MB</div>
                    <?php if (isset($fieldErrors['photo'])): ?>
                        <div class="text-danger small mt-2"><?= htmlspecialchars($fieldErrors['photo']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Trainer Meta -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">Trainer Info</h6>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Trainer ID</span>
                        <span class="fw-bold">#<?= $trainer['trainer_id'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Current Rating</span>
                        <span class="fw-bold text-warning">
                            <?= str_repeat('★', (int)$trainer['rating']) ?>
                            <span class="text-muted">(<?= number_format((float)$trainer['rating'], 1) ?>)</span>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Status</span>
                        <span class="badge <?= $trainer['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $trainer['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <?php if ($trainer['experience_years']): ?>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Experience</span>
                        <span class="fw-bold"><?= $trainer['experience_years'] ?> years</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reset Password Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">
                        <i class="fa-solid fa-key me-2 text-warning"></i>Reset Password
                    </h6>
                    <p class="text-muted small mb-3">Leave blank to keep the current password.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">New Password</label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="newPwd" class="<?= $fieldClass('new_password') ?>" placeholder="Min. 8 characters" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('newPwd','eyeNew')" tabindex="-1"><i class="fa-solid fa-eye" id="eyeNew"></i></button>
                        </div>
                        <?php if (isset($fieldErrors['new_password'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['new_password']) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label fw-bold small">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" name="confirm_new_password" id="confirmNewPwd" class="<?= $fieldClass('confirm_new_password') ?>" placeholder="Re-enter new password" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('confirmNewPwd','eyeConfirm')" tabindex="-1"><i class="fa-solid fa-eye" id="eyeConfirm"></i></button>
                        </div>
                        <?php if (isset($fieldErrors['confirm_new_password'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['confirm_new_password']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Save -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary-custom py-3 rounded-pill fw-bold">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Update Trainer
                </button>
                <?php if ($trainer['is_active']): ?>
                <button type="submit" form="impersonateForm" class="btn btn-dark rounded-pill fw-bold py-2">
                    <i class="fa-solid fa-user-secret me-2"></i>View Trainer Panel
                </button>
                <?php endif; ?>
                <a href="../trainers.php" class="btn btn-outline-secondary rounded-pill py-2">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php if ($trainer['is_active']): ?>
<form id="impersonateForm" method="POST" action="../impersonate_trainer.php" class="d-none" onsubmit="return confirm('Any unsaved changes will be lost. Continue?')">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="id" value="<?= (int)$trainerId ?>">
</form>
<?php endif; ?>

<style>
.trainer-photo-placeholder { width:110px;height:110px;border-radius:50%;background:rgba(255,107,53,0.1);color:#FF6B35;display:flex;align-items:center;justify-content:center; }
.trainer-photo-preview { width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid #FF6B35;display:block; }
</style>
<script>
document.getElementById('photoInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview     = document.getElementById('photoPreview');
        const placeholder = document.getElementById('photoPlaceholder');
        preview.src = e.target.result;
        preview.classList.remove('d-none');
        if (placeholder) placeholder.classList.add('d-none');
        // If user picks a new file, cancel any pending remove
        document.getElementById('removePhotoFlag').value = '0';
        const removeBtn = document.getElementById('removePhotoBtn');
        if (removeBtn) removeBtn.classList.remove('d-none');
        document.getElementById('uploadBtnLabel').textContent = 'Change Photo';
    };
    reader.readAsDataURL(file);
});

function confirmRemovePhoto() {
    if (!confirm('Are you sure you want to remove this profile photo? This will be saved when you click \'Update Trainer\'.')) return;

    const preview     = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    const removeBtn   = document.getElementById('removePhotoBtn');
    const photoInput  = document.getElementById('photoInput');

    // Show placeholder, hide preview
    if (preview)     { preview.classList.add('d-none'); preview.src = ''; }
    if (placeholder) { placeholder.classList.remove('d-none'); }

    // Hide remove button, update upload label
    if (removeBtn)   removeBtn.classList.add('d-none');
    document.getElementById('uploadBtnLabel').textContent = 'Upload Photo';

    // Clear file input & set the removal flag
    photoInput.value = '';
    document.getElementById('removePhotoFlag').value = '1';
}

document.getElementById('isActiveToggle').addEventListener('change', function() {
    document.getElementById('activeLabel').textContent =
        this.checked ? 'Active — visible on website' : 'Inactive — hidden from website';
});

function togglePwd(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye','fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash','fa-eye');
    }
}
</script>

<?php require_once '../includes/admin_footer.php'; ?>
