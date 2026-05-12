<?php
require_once '../../config/config.php';
require_once '../../helpers/auth_check.php';
require_once '../../helpers/upload_helper.php';
require_admin();

$pageTitle  = 'Add Trainer';
$success    = '';
$error      = '';
$fieldErrors = [];
$trainer    = [
    'trainer_id' => null, 'full_name' => '', 'email' => '', 'phone' => '',
    'specialization' => '', 'experience_years' => '', 'hourly_rate' => '',
    'bio' => '', 'photo' => '', 'rating' => '5.0', 'is_active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $fullName    = trim($_POST['full_name']       ?? '');
        $email       = trim($_POST['email']           ?? '');
        $phone       = trim($_POST['phone']           ?? '');
        $spec        = trim($_POST['specialization']  ?? '');
        $bio         = trim($_POST['bio']             ?? '');
        $expYears    = $_POST['experience_years']     ?? '';
        $hourlyRate  = $_POST['hourly_rate']          ?? '';
        $rating      = (float)($_POST['rating']       ?? 5.0);
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $password        = $_POST['password']         ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if ($fullName === '')   $fieldErrors['full_name']     = 'Full name is required.';
        if ($email === '')      $fieldErrors['email']         = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $fieldErrors['email'] = 'Enter a valid email address.';
        if ($spec === '')       $fieldErrors['specialization']= 'Specialization is required.';
        if ($rating < 1 || $rating > 5) $fieldErrors['rating'] = 'Rating must be between 1.0 and 5.0.';
        if ($expYears !== '' && (!is_numeric($expYears) || (int)$expYears < 0 || (int)$expYears > 60))
            $fieldErrors['experience_years'] = 'Enter a valid number of years (0-60).';
        if ($hourlyRate !== '' && (!is_numeric($hourlyRate) || (float)$hourlyRate < 0))
            $fieldErrors['hourly_rate'] = 'Enter a valid hourly rate.';
        // Password validation
        if ($password === '')
            $fieldErrors['password'] = 'Password is required.';
        elseif (strlen($password) < 8)
            $fieldErrors['password'] = 'Password must be at least 8 characters.';
        elseif ($password !== $confirmPassword)
            $fieldErrors['confirm_password'] = 'Passwords do not match.';

        // Check email duplicate
        if (!isset($fieldErrors['email'])) {
            $dupStmt = $pdo->prepare("SELECT trainer_id FROM trainers WHERE email = ?");
            $dupStmt->execute([$email]);
            if ($dupStmt->fetch()) {
                $fieldErrors['email'] = 'This email is already used by another trainer.';
            }
        }

        // Photo upload — uses shared upload_helper (10 MB, JPG/JPEG/PNG/WebP, unique filename)
        $photoPath = '';
        if (!empty($_FILES['photo']['name'])) {
            [$photoPath, $uploadErr] = upload_image($_FILES['photo'], 'profiles', 'trainer', 10 * 1024 * 1024);
            if ($uploadErr !== '') {
                $fieldErrors['photo'] = $uploadErr;
            }
        }

        if (empty($fieldErrors)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            try {
                $pdo->prepare("
                    INSERT INTO trainers (full_name, email, password_hash, phone, specialization, experience_years, hourly_rate, bio, photo, rating, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $fullName, $email, $hashedPassword, $phone ?: null,
                    $spec,
                    $expYears !== '' ? (int)$expYears : null,
                    $hourlyRate !== '' ? (float)$hourlyRate : null,
                    $bio ?: null, $photoPath ?: null,
                    $rating, $isActive,
                ]);
                $newTrainerId = $pdo->lastInsertId();

                // Assign permanent unique Trainer ID (TRN-XXXX)
                require_once '../../helpers/id_helper.php';
                assign_custom_id_if_missing($pdo, 'trainers', 'trainer_id', (int)$newTrainerId, 'TRN');

                header('Location: ../trainers.php?success=Trainer+added+successfully');
                exit;
            } catch (PDOException $e) {
                $error = 'Database error. Trainer email may already be in use.';
            }
        } else {
            $error = 'Please fix the highlighted errors.';
        }

        // Re-populate form values on error
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
        ]);
    }
}

$fieldClass = fn(string $k, string $b = 'form-control') => $b . (isset($fieldErrors[$k]) ? ' is-invalid' : '');
require_once '../includes/admin_header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="../trainers.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <div>
        <h3 class="fw-bold m-0">Add New Trainer</h3>
        <p class="text-muted small mb-0">Fill in the details below to onboard a new trainer.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="row g-4">
        <!-- LEFT: main details -->
        <div class="col-lg-8">
            <!-- Account Information -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-user-shield me-2 text-primary-custom"></i>Account & Login Details
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="<?= $fieldClass('full_name') ?>"
                                value="<?= htmlspecialchars($trainer['full_name']) ?>" maxlength="255" required>
                            <?php if (isset($fieldErrors['full_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['full_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="<?= $fieldClass('email') ?>"
                                value="<?= htmlspecialchars($trainer['email']) ?>" required>
                            <?php if (isset($fieldErrors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Phone</label>
                            <input type="tel" name="phone" class="<?= $fieldClass('phone') ?>"
                                value="<?= htmlspecialchars($trainer['phone']) ?>" placeholder="+91 98765 43210">
                            <?php if (isset($fieldErrors['phone'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['phone']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="trainerPassword" class="<?= $fieldClass('password') ?>" placeholder="Min. 8 characters" autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('trainerPassword','eyePass1')" tabindex="-1">
                                    <i class="fa-solid fa-eye" id="eyePass1"></i>
                                </button>
                            </div>
                            <?php if (isset($fieldErrors['password'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['password']) ?></div><?php endif; ?>
                            <div class="form-text">The trainer will use this to log in.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="trainerConfirmPassword" class="<?= $fieldClass('confirm_password') ?>" placeholder="Re-enter password" autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('trainerConfirmPassword','eyePass2')" tabindex="-1">
                                    <i class="fa-solid fa-eye" id="eyePass2"></i>
                                </button>
                            </div>
                            <?php if (isset($fieldErrors['confirm_password'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($fieldErrors['confirm_password']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Professional Information -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-briefcase me-2 text-primary-custom"></i>Professional Details
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Specialization <span class="text-danger">*</span></label>
                            <input type="text" name="specialization" class="<?= $fieldClass('specialization') ?>"
                                value="<?= htmlspecialchars($trainer['specialization']) ?>"
                                placeholder="e.g. Yoga, CrossFit, Bodybuilding" required>
                            <?php if (isset($fieldErrors['specialization'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['specialization']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Experience (Years)</label>
                            <input type="number" name="experience_years" class="<?= $fieldClass('experience_years') ?>"
                                value="<?= htmlspecialchars($trainer['experience_years'] ?? '') ?>" min="0" max="60" placeholder="e.g. 5">
                            <?php if (isset($fieldErrors['experience_years'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['experience_years']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Hourly Rate (₹)</label>
                            <input type="number" name="hourly_rate" class="<?= $fieldClass('hourly_rate') ?>"
                                value="<?= htmlspecialchars($trainer['hourly_rate'] ?? '') ?>" min="0" step="50" placeholder="e.g. 500">
                            <?php if (isset($fieldErrors['hourly_rate'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['hourly_rate']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Rating (1.0 – 5.0)</label>
                            <input type="number" name="rating" class="<?= $fieldClass('rating') ?>"
                                value="<?= htmlspecialchars($trainer['rating']) ?>" step="0.1" min="1.0" max="5.0" required>
                            <?php if (isset($fieldErrors['rating'])): ?><div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['rating']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Bio / Description</label>
                            <textarea name="bio" class="<?= $fieldClass('bio') ?>" rows="4"
                                placeholder="Brief professional background and expertise..."><?= htmlspecialchars($trainer['bio']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                    <?= ($trainer['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="isActive">Active (visible on website)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: photo + save -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 text-center">
                    <h6 class="fw-bold mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-camera me-2 text-primary-custom"></i>Profile Photo
                    </h6>
                    <div class="mb-3">
                        <div id="photoPlaceholder" class="trainer-photo-placeholder mx-auto mb-3">
                            <i class="fa-solid fa-person-running fa-2x"></i>
                        </div>
                        <img id="photoPreview" src="" alt="" class="trainer-photo-preview d-none mx-auto mb-3">
                    </div>
                    <label for="photoInput" class="btn btn-outline-secondary btn-sm rounded-pill px-4 mb-2">
                        <i class="fa-solid fa-upload me-1"></i>Choose Photo
                    </label>
                    <input type="file" name="photo" id="photoInput" class="d-none" accept="image/*">
                    <div class="form-text">JPG, JPEG, PNG, WebP — max 10 MB</div>
                    <?php if (isset($fieldErrors['photo'])): ?>
                        <div class="text-danger small mt-2"><?= htmlspecialchars($fieldErrors['photo']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary-custom py-3 rounded-pill fw-bold">
                    <i class="fa-solid fa-plus me-2"></i>Add Trainer
                </button>
                <a href="../trainers.php" class="btn btn-outline-secondary rounded-pill">Cancel</a>
            </div>
        </div>
    </div>
</form>

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
        const preview = document.getElementById('photoPreview');
        document.getElementById('photoPlaceholder').classList.add('d-none');
        preview.src = e.target.result;
        preview.classList.remove('d-none');
    };
    reader.readAsDataURL(file);
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
