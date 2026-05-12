<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/site_settings_helper.php';
require_once '../helpers/upload_helper.php';
require_admin();

$pageTitle = 'About Us Editor';
$success = '';
$error = '';
$fieldErrors = [];

$settings = site_settings_get_all($pdo, true);
$formValues = [
    'about_title' => site_settings_get('about_title', 'Our Story'),
    'about_subtitle' => site_settings_get('about_subtitle', 'More Than Just A Gym'),
    'about_description' => site_settings_get('about_description', 'Founded in 2010, FITNESS DESTINATION started with a simple mission: to provide a welcoming, high-energy environment for people of all fitness levels.'),
    'about_description2' => site_settings_get('about_description2', "Over the years, we've grown from a small neighborhood facility to a state-of-the-art fitness center spanning 10,000 square feet."),
    'about_mission' => site_settings_get('about_mission', 'Empower individuals to achieve fitness goals.'),
    'about_vision' => site_settings_get('about_vision', 'Create a healthier, stronger community.'),
    'about_image' => site_settings_get('about_image'),
    'team_section_enabled' => site_settings_get('team_section_enabled', '1'),
    'team_ceo_name' => site_settings_get('team_ceo_name', 'John Doe'),
    'team_ceo_title' => site_settings_get('team_ceo_title', 'Founder & CEO'),
    'team_ceo_image' => site_settings_get('team_ceo_image'),
    'team_manager_name' => site_settings_get('team_manager_name', 'Jane Smith'),
    'team_manager_title' => site_settings_get('team_manager_title', 'General Manager'),
    'team_manager_image' => site_settings_get('team_manager_image'),
    'team_head_trainer_name' => site_settings_get('team_head_trainer_name', 'Mike Johnson'),
    'team_head_trainer_title' => site_settings_get('team_head_trainer_title', 'Head Trainer'),
    'team_head_trainer_image' => site_settings_get('team_head_trainer_image'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_about') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid security token.';
    } else {
        [$data, $fieldErrors] = site_settings_validate_about_payload($_POST);
        $formValues = array_merge($formValues, $data);

        $imageFields = ['about_image', 'team_ceo_image', 'team_manager_image', 'team_head_trainer_image'];
        $newUploadPaths = [];
        $oldImagePathsByField = [];
        foreach ($imageFields as $imgField) {
            if (!empty($_FILES[$imgField]['name'])) {
                [$imgRelPath, $imgUploadErr] = upload_image($_FILES[$imgField], 'about', 'about', 30 * 1024 * 1024);
                if ($imgUploadErr !== '') {
                    $fieldErrors[$imgField] = $imgUploadErr;
                } elseif ($imgRelPath !== '') {
                    $oldImagePathsByField[$imgField] = (string)($formValues[$imgField] ?? '');
                    $newUploadPaths[]      = $imgRelPath;
                    $data[$imgField]        = $imgRelPath;
                    $formValues[$imgField]  = $imgRelPath;
                }
            }
        }

        if (empty($fieldErrors)) {
            try {
                site_settings_upsert($pdo, $data, array_fill_keys(array_keys($data), 'about'));
                foreach ($oldImagePathsByField as $imgField => $oldPath) {
                    $newPath = (string)($data[$imgField] ?? '');
                    if ($oldPath !== '' && $oldPath !== $newPath) {
                        upload_delete_old($oldPath);
                    }
                }
                $settings = site_settings_get_all($pdo, true);
                $formValues = [
                    'about_title' => site_settings_get('about_title', 'Our Story'),
                    'about_subtitle' => site_settings_get('about_subtitle', 'More Than Just A Gym'),
                    'about_description' => site_settings_get('about_description', 'Founded in 2010, FITNESS DESTINATION started with a simple mission: to provide a welcoming, high-energy environment for people of all fitness levels.'),
                    'about_description2' => site_settings_get('about_description2', "Over the years, we've grown from a small neighborhood facility to a state-of-the-art fitness center spanning 10,000 square feet."),
                    'about_mission' => site_settings_get('about_mission', 'Empower individuals to achieve fitness goals.'),
                    'about_vision' => site_settings_get('about_vision', 'Create a healthier, stronger community.'),
                    'about_image' => site_settings_get('about_image'),
                    'team_section_enabled' => site_settings_get('team_section_enabled', '1'),
                    'team_ceo_name' => site_settings_get('team_ceo_name', 'John Doe'),
                    'team_ceo_title' => site_settings_get('team_ceo_title', 'Founder & CEO'),
                    'team_ceo_image' => site_settings_get('team_ceo_image'),
                    'team_manager_name' => site_settings_get('team_manager_name', 'Jane Smith'),
                    'team_manager_title' => site_settings_get('team_manager_title', 'General Manager'),
                    'team_manager_image' => site_settings_get('team_manager_image'),
                    'team_head_trainer_name' => site_settings_get('team_head_trainer_name', 'Mike Johnson'),
                    'team_head_trainer_title' => site_settings_get('team_head_trainer_title', 'Head Trainer'),
                    'team_head_trainer_image' => site_settings_get('team_head_trainer_image'),
                ];
                $success = 'About Us page updated successfully!';
            } catch (PDOException $e) {
                foreach ($newUploadPaths as $newUploadPath) {
                    upload_delete_old($newUploadPath);
                }
                $error = 'Failed to save About Us content.';
            }
        } else {
            foreach ($newUploadPaths as $newUploadPath) {
                upload_delete_old($newUploadPath);
            }
            $error = 'Please fix the highlighted fields and try again. Errors: ' . implode(', ', $fieldErrors);
        }
    }
}

$displayValue = function (string $key, string $fallback = '') use ($formValues): string {
    $value = $formValues[$key] ?? $fallback;
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$fieldClass = function (string $key, string $base = 'form-control') use ($fieldErrors): string {
    return $base . (isset($fieldErrors[$key]) ? ' is-invalid' : '');
};

$currentImg = (string) ($formValues['about_image'] ?? '');
$imgSrc = '';
if ($currentImg !== '') {
    $imgPath = __DIR__ . '/../' . ltrim($currentImg, '/');
    if (file_exists($imgPath)) {
        $imgSrc = SITE_URL . '/' . ltrim($currentImg, '/');
    }
}

require_once 'includes/admin_header.php';
?>

<style>
.about-editor-card {
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f5;
}
.about-preview-panel {
    background: #f8f9ff;
    border-radius: 1rem;
    border: 1px solid #e8eaf0;
    padding: 1.5rem;
    position: sticky;
    top: 80px;
}
.img-drop-zone {
    border: 2px dashed #dee2e6;
    border-radius: 0.75rem;
    padding: 2rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #fafafa;
}
.img-drop-zone:hover,
.img-drop-zone.drag-over {
    border-color: #FF6B35;
    background: #fff5f0;
    color: #FF6B35;
}
.img-drop-zone input[type=file] {
    display: none;
}
.section-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(255,107,53,0.1);
    color: #FF6B35;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    padding: 0.3rem 0.75rem;
    border-radius: 100px;
    margin-bottom: 1.25rem;
}
.preview-mission-card {
    background: #fff;
    border-radius: 0.65rem;
    border: 1px solid #eee;
    padding: 0.85rem 1rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}
.preview-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.9rem;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold m-0" style="color:#1a1a2e;"><i class="fa-solid fa-file-pen text-muted me-2"></i>About Us Editor</h3>
        <p class="text-muted small mb-0 mt-1">Edit the content shown on the public About Us page. Changes take effect immediately.</p>
    </div>
    <a href="<?= SITE_URL ?>/about.php" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill">
        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Live Page
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger rounded-3 mb-4"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success rounded-3 mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-7">
        <form method="POST" action="about.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="save_about">

            <div class="about-editor-card p-4 mb-4">
                <div class="section-badge"><i class="fa-solid fa-heading"></i>Banner Text</div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Section Label <span class="text-danger">*</span></label>
                    <input type="text" name="about_title" class="<?= $fieldClass('about_title') ?>"
                        value="<?= $displayValue('about_title', 'Our Story') ?>"
                        placeholder="e.g. Our Story" maxlength="60" required>
                    <?php if (isset($fieldErrors['about_title'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['about_title']) ?></div>
                    <?php else: ?>
                        <div class="form-text">Small orange label shown above the main heading.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold small">Main Heading <span class="text-danger">*</span></label>
                    <input type="text" name="about_subtitle" class="<?= $fieldClass('about_subtitle') ?>"
                        value="<?= $displayValue('about_subtitle', 'More Than Just A Gym') ?>"
                        placeholder="e.g. More Than Just A Gym" maxlength="100" required>
                    <?php if (isset($fieldErrors['about_subtitle'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['about_subtitle']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="about-editor-card p-4 mb-4">
                <div class="section-badge"><i class="fa-solid fa-align-left"></i>Description</div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Paragraph 1 <span class="text-danger">*</span></label>
                    <textarea name="about_description" class="<?= $fieldClass('about_description') ?>" rows="4"
                        placeholder="First paragraph about the gym..." required><?= $displayValue('about_description', 'Founded in 2010, FITNESS DESTINATION started with a simple mission: to provide a welcoming, high-energy environment for people of all fitness levels.') ?></textarea>
                    <?php if (isset($fieldErrors['about_description'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['about_description']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold small">Paragraph 2 <span class="text-muted">(optional)</span></label>
                    <textarea name="about_description2" class="<?= $fieldClass('about_description2') ?>" rows="3"
                        placeholder="Second paragraph..."><?= $displayValue('about_description2', "Over the years, we've grown from a small neighborhood facility to a state-of-the-art fitness center spanning 10,000 square feet.") ?></textarea>
                    <?php if (isset($fieldErrors['about_description2'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['about_description2']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="about-editor-card p-4 mb-4">
                <div class="section-badge"><i class="fa-solid fa-bullseye"></i>Mission &amp; Vision</div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small"><i class="fa-solid fa-bullseye text-primary me-1"></i>Mission Statement</label>
                    <textarea name="about_mission" class="<?= $fieldClass('about_mission') ?>" rows="2"
                        placeholder="Our mission..."><?= $displayValue('about_mission', 'Empower individuals to achieve fitness goals.') ?></textarea>
                    <?php if (isset($fieldErrors['about_mission'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['about_mission']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold small"><i class="fa-solid fa-eye text-dark me-1"></i>Vision Statement</label>
                    <textarea name="about_vision" class="<?= $fieldClass('about_vision') ?>" rows="2"
                        placeholder="Our vision..."><?= $displayValue('about_vision', 'Create a healthier, stronger community.') ?></textarea>
                    <?php if (isset($fieldErrors['about_vision'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['about_vision']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="about-editor-card p-4 mb-4">
                <div class="section-badge"><i class="fa-solid fa-image"></i>About Image</div>

                <?php if ($imgSrc !== ''): ?>
                <div class="mb-3">
                    <p class="small text-muted mb-2">Current image:</p>
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="About image" class="img-thumbnail rounded-3" style="max-height:180px; object-fit:cover; width:100%;">
                </div>
                <?php endif; ?>

                <label class="img-drop-zone w-100" id="dropZone">
                    <input type="file" name="about_image" id="aboutImageInput" accept="image/*">
                    <i class="fa-solid fa-cloud-arrow-up fa-2x mb-2 d-block" style="color:#ccc;"></i>
                    <p class="mb-0 fw-semibold" id="dropLabel">Click to upload or drag & drop</p>
                    <small class="text-muted">JPG, PNG, WEBP · Max 30MB</small>
                </label>
                <?php if (isset($fieldErrors['about_image'])): ?>
                    <div class="text-danger small mt-2"><?= htmlspecialchars($fieldErrors['about_image']) ?></div>
                <?php endif; ?>
            </div>

            <div class="about-editor-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="section-badge mb-0"><i class="fa-solid fa-users"></i>Core Team</div>
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" role="switch" name="team_section_enabled" id="teamToggle" value="1" <?= $formValues['team_section_enabled'] == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fs-6 fw-bold" for="teamToggle">Show Section</label>
                    </div>
                </div>
                
                <div id="teamFieldsContainer" class="<?= $formValues['team_section_enabled'] == '1' ? '' : 'opacity-50' ?>" style="transition: opacity 0.3s;">
                    <!-- CEO -->
                    <div class="bg-light p-3 rounded-3 mb-4 border">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="fa-solid fa-user-tie text-primary-custom"></i> CEO / Founder</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Name</label>
                                <input type="text" name="team_ceo_name" class="<?= $fieldClass('team_ceo_name') ?>" value="<?= $displayValue('team_ceo_name', 'John Doe') ?>" placeholder="John Doe">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Title</label>
                                <input type="text" name="team_ceo_title" class="<?= $fieldClass('team_ceo_title') ?>" value="<?= $displayValue('team_ceo_title', 'Founder & CEO') ?>" placeholder="Founder & CEO">
                            </div>
                            <div class="col-12 d-flex align-items-center gap-3 mt-3">
                                <?php if ($formValues['team_ceo_image']): ?>
                                    <img src="<?= SITE_URL ?>/<?= htmlspecialchars(ltrim($formValues['team_ceo_image'], '/')) ?>" class="rounded-circle shadow-sm" style="width: 50px; height: 50px; object-fit: cover;" alt="CEO">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <label class="form-label fw-semibold small mb-1">Profile Photo</label>
                                    <input type="file" name="team_ceo_image" class="form-control form-control-sm <?= $fieldClass('team_ceo_image') ?>" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Manager -->
                    <div class="bg-light p-3 rounded-3 mb-4 border">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="fa-solid fa-briefcase text-primary-custom"></i> General Manager</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Name</label>
                                <input type="text" name="team_manager_name" class="<?= $fieldClass('team_manager_name') ?>" value="<?= $displayValue('team_manager_name', 'Jane Smith') ?>" placeholder="Jane Smith">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Title</label>
                                <input type="text" name="team_manager_title" class="<?= $fieldClass('team_manager_title') ?>" value="<?= $displayValue('team_manager_title', 'General Manager') ?>" placeholder="General Manager">
                            </div>
                            <div class="col-12 d-flex align-items-center gap-3 mt-3">
                                <?php if ($formValues['team_manager_image']): ?>
                                    <img src="<?= SITE_URL ?>/<?= htmlspecialchars(ltrim($formValues['team_manager_image'], '/')) ?>" class="rounded-circle shadow-sm" style="width: 50px; height: 50px; object-fit: cover;" alt="Manager">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <label class="form-label fw-semibold small mb-1">Profile Photo</label>
                                    <input type="file" name="team_manager_image" class="form-control form-control-sm <?= $fieldClass('team_manager_image') ?>" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Head Trainer -->
                    <div class="bg-light p-3 rounded-3 border">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="fa-solid fa-dumbbell text-primary-custom"></i> Head Trainer</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Name</label>
                                <input type="text" name="team_head_trainer_name" class="<?= $fieldClass('team_head_trainer_name') ?>" value="<?= $displayValue('team_head_trainer_name', 'Mike Johnson') ?>" placeholder="Mike Johnson">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Title</label>
                                <input type="text" name="team_head_trainer_title" class="<?= $fieldClass('team_head_trainer_title') ?>" value="<?= $displayValue('team_head_trainer_title', 'Head Trainer') ?>" placeholder="Head Trainer">
                            </div>
                            <div class="col-12 d-flex align-items-center gap-3 mt-3">
                                <?php if ($formValues['team_head_trainer_image']): ?>
                                    <img src="<?= SITE_URL ?>/<?= htmlspecialchars(ltrim($formValues['team_head_trainer_image'], '/')) ?>" class="rounded-circle shadow-sm" style="width: 50px; height: 50px; object-fit: cover;" alt="Head Trainer">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <label class="form-label fw-semibold small mb-1">Profile Photo</label>
                                    <input type="file" name="team_head_trainer_image" class="form-control form-control-sm <?= $fieldClass('team_head_trainer_image') ?>" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-lg w-100 rounded-3 fw-bold" style="background:#FF6B35;color:#fff;">
                <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
            </button>
        </form>
    </div>

    <div class="col-xl-5">
        <div class="about-preview-panel">
            <p class="small fw-bold text-muted text-uppercase mb-3" style="letter-spacing:0.07em;">
                <i class="fa-solid fa-eye me-1"></i>Live Preview
            </p>

            <?php if ($imgSrc !== ''): ?>
            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Preview" id="previewImg"
                style="width:100%; height:190px; object-fit:cover; border-radius:0.75rem; margin-bottom:1rem;">
            <?php else: ?>
            <div id="previewImg"
                style="width:100%; height:190px; background:linear-gradient(135deg,#FF6B35,#1a1a2e); border-radius:0.75rem; margin-bottom:1rem; display:flex; align-items:center; justify-content:center;">
                <i class="fa-solid fa-dumbbell fa-3x text-white opacity-50"></i>
            </div>
            <?php endif; ?>

            <span style="font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:#FF6B35;" id="previewTitle">
                <?= $displayValue('about_title', 'Our Story') ?>
            </span>
            <h5 class="fw-bold mt-1 mb-2" id="previewSubtitle" style="color:#1a1a2e;">
                <?= $displayValue('about_subtitle', 'More Than Just A Gym') ?>
            </h5>
            <p class="small text-muted mb-2" id="previewDesc">
                <?= $displayValue('about_description', 'Founded in 2010...') ?>
            </p>
            <p class="small text-muted mb-3" id="previewDesc2">
                <?= $displayValue('about_description2', '') ?>
            </p>

            <div class="preview-mission-card">
                <div class="preview-icon" style="background:#fff3ee;">
                    <i class="fa-solid fa-bullseye" style="color:#FF6B35;"></i>
                </div>
                <div>
                    <div class="fw-bold small" style="color:#1a1a2e;">Our Mission</div>
                    <small class="text-muted" id="previewMission"><?= $displayValue('about_mission', 'Empower individuals to achieve fitness goals.') ?></small>
                </div>
            </div>
            <div class="preview-mission-card">
                <div class="preview-icon" style="background:#f0f0f5;">
                    <i class="fa-solid fa-eye" style="color:#1a1a2e;"></i>
                </div>
                <div>
                    <div class="fw-bold small" style="color:#1a1a2e;">Our Vision</div>
                    <small class="text-muted" id="previewVision"><?= $displayValue('about_vision', 'Create a healthier, stronger community.') ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const bindPreview = (name, previewId) => {
    const input = document.querySelector(`[name="${name}"]`);
    const output = document.getElementById(previewId);
    if (!input || !output) return;
    input.addEventListener('input', () => {
        output.textContent = input.value;
    });
};

bindPreview('about_title', 'previewTitle');
bindPreview('about_subtitle', 'previewSubtitle');
bindPreview('about_description', 'previewDesc');
bindPreview('about_description2', 'previewDesc2');
bindPreview('about_mission', 'previewMission');
bindPreview('about_vision', 'previewVision');

const dropZone = document.getElementById('dropZone');
const imgInput = document.getElementById('aboutImageInput');
const dropLabel = document.getElementById('dropLabel');
let previewEl = document.getElementById('previewImg');

imgInput.addEventListener('change', handleFile);

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, event => {
        event.preventDefault();
        dropZone.classList.add('drag-over');
    });
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, event => {
        event.preventDefault();
        dropZone.classList.remove('drag-over');
    });
});

dropZone.addEventListener('drop', event => {
    imgInput.files = event.dataTransfer.files;
    handleFile();
});

function handleFile() {
    const file = imgInput.files[0];
    if (!file) return;

    dropLabel.textContent = file.name;

    const reader = new FileReader();
    reader.onload = event => {
        if (previewEl.tagName === 'IMG') {
            previewEl.src = event.target.result;
            return;
        }

        const image = document.createElement('img');
        image.id = 'previewImg';
        image.src = event.target.result;
        image.alt = 'Preview';
        image.style.cssText = 'width:100%;height:190px;object-fit:cover;border-radius:0.75rem;margin-bottom:1rem;';
        previewEl.replaceWith(image);
        previewEl = image;
    };
    reader.readAsDataURL(file);
}
const teamToggle = document.getElementById('teamToggle');
const teamContainer = document.getElementById('teamFieldsContainer');
if (teamToggle && teamContainer) {
    teamToggle.addEventListener('change', () => {
        teamContainer.classList.toggle('opacity-50', !teamToggle.checked);
    });
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
