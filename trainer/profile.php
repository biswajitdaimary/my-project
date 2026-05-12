<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_trainer();

$pageTitle = 'My Profile';
$trainer_id = $_SESSION['user_id'];
$success = ''; $error = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM trainers WHERE trainer_id = ?");
    $stmt->execute([$trainer_id]);
    $trainer = $stmt->fetch();
} catch (PDOException $e) {
    die("Database Error");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token.";
    } else {
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $experience_years = (!empty($_POST['experience_years']) && is_numeric($_POST['experience_years'])) ? (int)$_POST['experience_years'] : null;
        $hourly_rate = (!empty($_POST['hourly_rate']) && is_numeric($_POST['hourly_rate'])) ? (float)$_POST['hourly_rate'] : null;
        
        // Handle Photo Upload
        $photoPath = $trainer['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['name'] !== '') {
            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                $error = "Image upload failed (error code " . $_FILES['photo']['error'] . ").";
                if ($_FILES['photo']['error'] == UPLOAD_ERR_INI_SIZE) {
                     $error = "File exceeds the server upload limit.";
                }
            } elseif ($_FILES['photo']['size'] > 10 * 1024 * 1024) {
                $error = "Image size must be smaller than 10MB.";
            } else {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (in_array($_FILES['photo']['type'], $allowedTypes)) {
                    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                    $newFilename = 'trainer_' . $trainer_id . '_' . time() . '.' . $ext;
                    $uploadDir = '../uploads/trainers/';
                    
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newFilename)) {
                        $photoPath = 'uploads/trainers/' . $newFilename;
                    } else {
                        $error = "Failed to save the uploaded image. Check folder permissions.";
                    }
                } else {
                    $error = "Invalid image format. Only JPG, PNG, and WebP are allowed.";
                }
            }
        }

        if (!$error) {
            try {
                $update = $pdo->prepare("UPDATE trainers SET phone = ?, bio = ?, specialization = ?, experience_years = ?, hourly_rate = ?, photo = ? WHERE trainer_id = ?");
                $update->execute([$phone, $bio, $specialization, $experience_years, $hourly_rate, $photoPath, $trainer_id]);
                $success = "Profile updated successfully.";
                
                // Handle Password Change
                $new_password = trim($_POST['new_password'] ?? '');
                $confirm_password = trim($_POST['confirm_password'] ?? '');
                
                if ($new_password !== '') {
                    if (strlen($new_password) < 8) {
                        $error = "Password must be at least 8 characters.";
                        $success = ''; // Clear success if password fails
                    } elseif ($new_password !== $confirm_password) {
                        $error = "New passwords do not match.";
                        $success = '';
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $pwdUpdate = $pdo->prepare("UPDATE trainers SET password_hash = ? WHERE trainer_id = ?");
                        $pwdUpdate->execute([$hashed_password, $trainer_id]);
                        $success = "Profile and password updated successfully.";
                    }
                }
                
                if (!$error) {
                    // Update local var to reflect instantly
                    $trainer['phone'] = $phone;
                    $trainer['bio'] = $bio;
                    $trainer['specialization'] = $specialization;
                    $trainer['experience_years'] = $experience_years;
                    $trainer['hourly_rate'] = $hourly_rate;
                    $trainer['photo'] = $photoPath;
                    $_SESSION['full_name'] = $trainer['full_name']; // In case name updates in future
                }
            } catch(PDOException $e) {
                $error = "Failed to update profile.";
            }
        }
    }
}

// Helpers for UI
$photoSrc = !empty($trainer['photo']) 
            ? (str_starts_with($trainer['photo'], 'http') ? $trainer['photo'] : SITE_URL . '/' . ltrim($trainer['photo'], '/')) 
            : '';

require_once 'includes/trainer_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold m-0" style="color:#1a1a2e;">My Profile</h3>
        <p class="text-muted small mb-0 mt-1">Manage your personal information, specialization, and public bio.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm"><i class="fa-solid fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="row g-4">
        <!-- LEFT COLUMN: Personal Info & Bio -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-user me-2" style="color: #FF6B35;"></i>Personal Information
                    </h5>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Full Name <i class="fa-solid fa-lock ms-1" title="Managed by Admin"></i></label>
                            <input type="text" class="form-control bg-light border-0 py-2 text-muted" value="<?= htmlspecialchars($trainer['full_name']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Email <i class="fa-solid fa-lock ms-1" title="Managed by Admin"></i></label>
                            <input type="email" class="form-control bg-light border-0 py-2 text-muted" value="<?= htmlspecialchars($trainer['email']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Phone Number</label>
                            <input type="tel" name="phone" class="form-control bg-light border-0 py-2" value="<?= htmlspecialchars($trainer['phone'] ?? '') ?>" placeholder="+1 234 567 8900">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Specialization</label>
                            <input type="text" name="specialization" class="form-control bg-light border-0 py-2" value="<?= htmlspecialchars($trainer['specialization'] ?? '') ?>" placeholder="e.g. Weightlifting, Yoga">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Experience (Years)</label>
                            <div class="input-group input-group-lg border-0 shadow-sm" style="border-radius: 0.5rem; overflow: hidden;">
                                <input type="number" name="experience_years" class="form-control bg-light border-0 fs-6" value="<?= htmlspecialchars($trainer['experience_years'] ?? '') ?>" placeholder="e.g. 5" min="0">
                                <span class="input-group-text bg-light border-0 text-muted small fw-bold">YRS</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Hourly Rate</label>
                            <div class="input-group input-group-lg border-0 shadow-sm" style="border-radius: 0.5rem; overflow: hidden;">
                                <span class="input-group-text bg-light border-0 text-muted small fw-bold">₹</span>
                                <input type="number" step="0.01" name="hourly_rate" class="form-control bg-light border-0 fs-6" value="<?= htmlspecialchars($trainer['hourly_rate'] ?? '') ?>" placeholder="e.g. 500.00" min="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-align-left me-2" style="color: #FF6B35;"></i>Public Biography
                    </h5>
                    <p class="text-muted small mb-3">This bio is visible to clients when they are browsing trainers to book a session. Make it engaging!</p>
                    <textarea name="bio" class="form-control bg-light border-0 py-3" rows="6" placeholder="Write a short bio about your fitness journey, training philosophy, and what clients can expect..."><?= htmlspecialchars($trainer['bio'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Security / Change Password -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-shield-halved me-2" style="color: #FF6B35;"></i>Security & Password
                    </h5>
                    <p class="text-muted small mb-4">Leave these fields blank if you do not wish to change your current password.</p>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="newPwd" class="form-control bg-light border-0 py-2" placeholder="Min. 8 characters" autocomplete="new-password">
                                <button class="btn bg-light border-0 text-muted px-3" type="button" onclick="togglePwd('newPwd','eyeNew')" tabindex="-1">
                                    <i class="fa-solid fa-eye" id="eyeNew"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirmPwd" class="form-control bg-light border-0 py-2" placeholder="Re-enter password" autocomplete="new-password">
                                <button class="btn bg-light border-0 text-muted px-3" type="button" onclick="togglePwd('confirmPwd','eyeConfirm')" tabindex="-1">
                                    <i class="fa-solid fa-eye" id="eyeConfirm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Photo & Actions -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-camera-retro me-2" style="color: #FF6B35;"></i>Profile Photo
                    </h5>
                    
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <?php if ($photoSrc): ?>
                                <img src="<?= $photoSrc ?>" alt="Profile Photo" id="photoPreview" style="width: 160px; height: 160px; object-fit: cover; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <?php else: ?>
                                <div id="photoPreview" style="width: 160px; height: 160px; border-radius: 50%; background: linear-gradient(135deg, #1a1a2e, #2d2d55); color: #FF6B35; display: flex; align-items: center; justify-content: center; font-size: 4rem; font-weight: 800; border: 4px solid #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin: 0 auto;">
                                    <?= strtoupper(substr($trainer['full_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            
                            <label class="btn btn-primary rounded-circle shadow" style="position: absolute; bottom: 5px; right: 5px; width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #FF6B35; border-color: #FF6B35;">
                                <i class="fa-solid fa-pen text-white"></i>
                                <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewImage(this)">
                            </label>
                        </div>
                        <p class="text-muted small mt-3 mb-0">Allowed: JPG, PNG, WebP<br>Max size: 10MB</p>
                    </div>
                </div>
            </div>

            <!-- Stats Card (Read Only) -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary text-white position-relative overflow-hidden">
                <!-- Decorative background elements -->
                <div style="position: absolute; top: -20px; right: -20px; font-size: 8rem; opacity: 0.1; transform: rotate(15deg);">
                    <i class="fa-solid fa-medal"></i>
                </div>
                
                <div class="card-body p-4 position-relative z-1">
                    <h5 class="fw-bold mb-4 border-bottom border-light pb-3 opacity-75">
                        <i class="fa-solid fa-chart-line me-2"></i>Your Statistics
                    </h5>

                    <?php if (!empty($trainer['custom_id'])): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-light">
                        <span class="fw-bold opacity-75 text-uppercase small">Trainer ID</span>
                        <span class="badge rounded-pill px-3 py-2 fw-bold"
                              style="background:rgba(255,255,255,0.15);letter-spacing:2px;font-family:monospace;font-size:.85rem;">
                            <i class="fa-solid fa-fingerprint me-1"></i>
                            <?= htmlspecialchars($trainer['custom_id']) ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold opacity-75 text-uppercase small">Current Rating</span>
                        <div class="fs-4 fw-bold">
                            <?= number_format($trainer['rating'] ?? 5.0, 1) ?> <i class="fa-solid fa-star text-warning" style="font-size: 1rem; position: relative; top: -3px;"></i>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold opacity-75 text-uppercase small">Account Status</span>
                        <div>
                            <?php if ($trainer['is_active']): ?>
                                <span class="badge bg-success rounded-pill px-3 py-2">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger rounded-pill px-3 py-2">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary-custom py-3 rounded-4 fw-bold fs-6 shadow-sm">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save All Changes
                </button>
            </div>
        </div>
    </div>
</form>

<script>
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
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('photoPreview');
            if (preview.tagName.toLowerCase() === 'img') {
                preview.src = e.target.result;
            } else {
                // It was a div initial placeholder, replace with img
                const img = document.createElement('img');
                img.src = e.target.result;
                img.id = 'photoPreview';
                img.style.width = '160px';
                img.style.height = '160px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '50%';
                img.style.border = '4px solid #fff';
                img.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
                preview.parentNode.replaceChild(img, preview);
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once 'includes/trainer_footer.php'; ?>
