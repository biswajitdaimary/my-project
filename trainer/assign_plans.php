<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';

// Must verify trainer before doing any processing
require_trainer();

$trainerId = $_SESSION['user_id'];
$preselectClient = isset($_GET['preselect_client']) ? (int)$_GET['preselect_client'] : 0;

// Fetch all unique clients who have booked this trainer
$stmt = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.full_name 
    FROM users u
    JOIN trainer_bookings tb ON u.user_id = tb.user_id
    WHERE tb.trainer_id = ?
    ORDER BY u.full_name ASC
");
$stmt->execute([$trainerId]);
$clients = $stmt->fetchAll();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_plan') {
        $clientId = (int)$_POST['client_id'];
        $planTitle = trim($_POST['plan_title']);
        $planType = $_POST['plan_type'];
        $planContent = trim($_POST['plan_content']);
        $videoLink = trim($_POST['video_link'] ?? '');
        
        $filePath = null;
        $fileType = null;
        $originalFilename = null;

        if (!empty($clientId) && !empty($planTitle) && !empty($planContent)) {
            // Handle File Upload
            if (isset($_FILES['plan_file']) && $_FILES['plan_file']['error'] === UPLOAD_ERR_OK) {
                // Use __DIR__ to build a clean absolute path — avoids config/../uploads traversal
                $plansDir = realpath(__DIR__ . '/..') . '/uploads/plans/';
                if (!is_dir($plansDir)) {
                    @mkdir($plansDir, 0777, true);
                }

                $fileInfo = pathinfo($_FILES['plan_file']['name']);
                $ext = strtolower($fileInfo['extension'] ?? '');

                $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
                if (in_array($ext, $allowedExts)) {
                    $newFilename = uniqid('plan_') . '_' . time() . '.' . $ext;
                    $destination = $plansDir . $newFilename;

                    $maxSize = 10 * 1024 * 1024; // 10 MB
                    if ($_FILES['plan_file']['size'] > $maxSize) {
                        $error = "File is too large. Maximum size allowed is 10 MB.";
                    } else if (move_uploaded_file($_FILES['plan_file']['tmp_name'], $destination)) {
                        $filePath = 'uploads/plans/' . $newFilename;
                        $fileType = $ext === 'pdf' ? 'pdf' : 'image';
                        $originalFilename = $fileInfo['basename'];
                    } else {
                        $error = "Upload failed. Path: " . $destination . " — ensure this folder is writable.";
                    }
                } else {
                    $error = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
                }
            } else if (isset($_FILES['plan_file']) && $_FILES['plan_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $phpUploadErrors = [1=>'File too large (php.ini)',2=>'File too large (form)',3=>'Partial upload',4=>'No file',6=>'Missing temp folder',7=>'Write failed',8=>'Extension blocked'];
                $error = "Upload error: " . ($phpUploadErrors[$_FILES['plan_file']['error']] ?? 'Code '.$_FILES['plan_file']['error']);
            }

            if (!isset($error)) {
                $stmt = $pdo->prepare("INSERT INTO client_workout_plans (trainer_id, user_id, plan_title, plan_type, plan_content, file_path, file_type, original_filename, video_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$trainerId, $clientId, $planTitle, $planType, $planContent, $filePath, $fileType, $originalFilename, $videoLink]);
                $success = "Plan assigned successfully to the client!";
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    } elseif ($_POST['action'] === 'delete_plan') {
        $planId = (int)$_POST['plan_id'];
        
        // Fetch to delete file
        $stmt = $pdo->prepare("SELECT file_path FROM client_workout_plans WHERE plan_id = ? AND trainer_id = ?");
        $stmt->execute([$planId, $trainerId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['file_path'])) {
            $pathToDelete = '../' . $row['file_path'];
            if (file_exists($pathToDelete)) {
                unlink($pathToDelete);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM client_workout_plans WHERE plan_id = ? AND trainer_id = ?");
        $stmt->execute([$planId, $trainerId]);
        $success = "Plan deleted successfully.";
    }
}

// Fetch recently created plans
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name as client_name 
    FROM client_workout_plans p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.trainer_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$trainerId]);
$recentPlans = $stmt->fetchAll();

$pageTitle = 'Assign Workout Plans';
require_once 'includes/trainer_header.php';
?>

<style>
/* Modern UI Styles for Plans */
.plan-header-banner {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    border-radius: 1rem;
    padding: 2rem 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.15);
    position: relative;
    overflow: hidden;
}

.plan-header-banner::after {
    content: '';
    position: absolute;
    right: -5%;
    top: -20%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
    border-radius: 50%;
}

.modern-card {
    background: #ffffff;
    border: none;
    border-radius: 1.25rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    overflow: hidden;
}

.form-control-modern {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    transition: all 0.2s;
}
.form-control-modern:focus {
    background-color: #ffffff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.file-upload-wrapper {
    position: relative;
    border: 2px dashed #cbd5e1;
    border-radius: 1rem;
    padding: 1.75rem 1.25rem;
    text-align: center;
    background: #f8fafc;
    transition: all 0.3s;
    cursor: pointer;
    overflow: hidden;
}
.file-upload-wrapper:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}
.file-upload-wrapper.has-file {
    border-color: #22c55e;
    background: #f0fdf4;
    cursor: default;
}
.file-upload-input {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}
.file-upload-wrapper.has-file .file-upload-input {
    pointer-events: none; /* prevent re-triggering when file is shown */
}
/* Empty state */
.upload-empty-state { transition: opacity 0.2s; }
/* Selected state */
.upload-selected-state {
    display: none;
    align-items: center;
    gap: 1rem;
    background: #fff;
    border: 1.5px solid #bbf7d0;
    border-radius: 0.75rem;
    padding: 0.85rem 1rem;
    position: relative;
    z-index: 3;
    text-align: left;
}
.upload-selected-state.show { display: flex; }
.upload-file-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; flex-shrink: 0;
}
.upload-file-icon.pdf  { background: #fee2e2; color: #dc2626; }
.upload-file-icon.img  { background: #e0e7ff; color: #4f46e5; }
.upload-file-name {
    font-size: 0.82rem; font-weight: 700; color: #1e293b;
    word-break: break-all; flex: 1;
}
.upload-file-size { font-size: 0.72rem; color: #64748b; margin-top: 2px; }
.upload-remove-btn {
    background: none; border: none; color: #ef4444; font-size: 1.1rem;
    cursor: pointer; padding: 0.25rem; border-radius: 50%; z-index: 4;
    line-height: 1; flex-shrink: 0;
    transition: background 0.2s;
}
.upload-remove-btn:hover { background: #fee2e2; }

.plan-item-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 1rem;
    margin-bottom: 1rem;
    transition: transform 0.2s, box-shadow 0.2s;
}
.plan-item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}

.attachment-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 1rem;
    background: #f1f5f9;
    border-radius: 20px;
    font-size: 0.85rem;
    color: #475569;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.attachment-badge:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.attachment-badge.pdf { background: #fee2e2; color: #dc2626; }
.attachment-badge.pdf:hover { background: #fecaca; }
.attachment-badge.image { background: #e0e7ff; color: #4f46e5; }
.attachment-badge.image:hover { background: #c7d2fe; }

.video-preview-btn {
    background: #ffe4e6;
    color: #e11d48;
}
.video-preview-btn:hover {
    background: #fecdd3;
    color: #be123c;
}

</style>

<div class="container-fluid pt-2 pb-4">
    <div class="plan-header-banner">
        <h2 class="fw-bold mb-1" style="position:relative; z-index:2;"><i class="fa-solid fa-dumbbell me-2"></i>Workout & Diet Plans</h2>
        <p class="mb-0 opacity-75" style="position:relative; z-index:2;">Create and assign custom routines, diets, and resources to your clients.</p>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4 fw-bold">
            <i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 fw-bold">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Create Plan Form -->
        <div class="col-lg-5">
            <div class="modern-card h-100">
                <div class="p-4 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-plus-circle text-primary me-2"></i>Create New Plan</h5>
                </div>
                <div class="p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create_plan">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Select Client <span class="text-danger">*</span></label>
                            <select class="form-select form-control-modern" name="client_id" required>
                                <option value="">Choose a client...</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['user_id'] ?>" <?= $client['user_id'] == $preselectClient ? 'selected' : '' ?>><?= htmlspecialchars($client['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row mb-3 g-3">
                            <div class="col-md-7">
                                <label class="form-label fw-bold text-dark">Plan Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-modern" name="plan_title" placeholder="e.g. 4-Week Hypertrophy" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold text-dark">Type</label>
                                <select class="form-select form-control-modern" name="plan_type">
                                    <option value="workout">Workout</option>
                                    <option value="diet">Diet / Nutrition</option>
                                    <option value="hybrid">Hybrid</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark">Plan Instructions <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-modern" name="plan_content" rows="6" placeholder="Day 1: Chest & Triceps...&#10;1. Bench Press: 4 sets of 8-10 reps..." required></textarea>
                        </div>
                        
                        <!-- Rich Attachments Section -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-paperclip text-secondary me-2"></i>Attachments (Optional)</h6>
                            
                            <div class="file-upload-wrapper mb-3" id="dropZone">
                                <!-- Invisible file input (active only in empty state) -->
                                <input type="file" name="plan_file" class="file-upload-input" id="planFile" accept=".jpg,.jpeg,.png,.pdf" onchange="updateFileName(this)">

                                <!-- Empty / idle state -->
                                <div class="upload-empty-state" id="uploadEmptyState">
                                    <i class="fa-solid fa-cloud-arrow-up fa-2x text-primary mb-2"></i>
                                    <h6 class="fw-bold text-dark mb-1">Click or drag file to upload</h6>
                                    <p class="text-muted small mb-0">Supports PDF, JPG, PNG &bull; Max 10 MB</p>
                                </div>

                                <!-- File selected state (shown after picking a file) -->
                                <div class="upload-selected-state" id="uploadSelectedState">
                                    <div class="upload-file-icon" id="uploadFileIcon"><i class="fa-solid fa-file"></i></div>
                                    <div class="flex-grow-1" style="min-width:0;">
                                        <div class="upload-file-name" id="uploadFileName">—</div>
                                        <div class="upload-file-size" id="uploadFileSize"></div>
                                    </div>
                                    <button type="button" class="upload-remove-btn" id="uploadRemoveBtn" title="Remove file">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label fw-bold text-dark small"><i class="fa-brands fa-youtube text-danger me-1"></i>Video Link</label>
                                <input type="url" class="form-control form-control-modern" name="video_link" placeholder="https://youtube.com/watch?v=...">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm" style="background: linear-gradient(135deg, #3b82f6, #4f46e5); border: none;">
                            <i class="fa-solid fa-paper-plane me-2"></i>Assign Plan
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recent Plans -->
        <div class="col-lg-7">
            <div class="modern-card h-100">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-light">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i>Recently Assigned</h5>
                    <span class="badge bg-white text-dark shadow-sm px-3 py-2 rounded-pill border"><?= count($recentPlans) ?> Plans</span>
                </div>
                <div class="p-4 bg-light" style="min-height: 500px;">
                    <?php if (empty($recentPlans)): ?>
                        <div class="text-center py-5">
                            <div class="bg-white rounded-circle shadow-sm d-inline-flex align-items-center justify-content-center mb-3" style="width:80px; height:80px;">
                                <i class="fa-solid fa-file-signature fa-2x text-muted opacity-50"></i>
                            </div>
                            <h5 class="fw-bold text-dark">No Plans Yet</h5>
                            <p class="text-muted small">When you create a plan, it will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="accordion" id="plansAccordion">
                            <?php foreach ($recentPlans as $index => $plan): ?>
                                <div class="accordion-item plan-item-card overflow-hidden">
                                    <h2 class="accordion-header" id="heading<?= $plan['plan_id'] ?>">
                                        <button class="accordion-button collapsed bg-white fw-bold shadow-none p-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $plan['plan_id'] ?>">
                                            <div class="d-flex w-100 justify-content-between align-items-center pe-3">
                                                <div>
                                                    <div class="mb-1 text-dark fs-6"><?= htmlspecialchars($plan['plan_title']) ?></div>
                                                    <div class="text-muted fw-normal small"><i class="fa-solid fa-user text-secondary me-1"></i><?= htmlspecialchars($plan['client_name']) ?></div>
                                                </div>
                                                <div class="text-end">
                                                    <?php
                                                    $badge = 'bg-primary';
                                                    if ($plan['plan_type'] === 'diet') $badge = 'bg-success';
                                                    if ($plan['plan_type'] === 'hybrid') $badge = 'bg-warning text-dark';
                                                    ?>
                                                    <span class="badge <?= $badge ?> rounded-pill mb-1 d-inline-block px-2 py-1 shadow-sm"><?= ucfirst(htmlspecialchars($plan['plan_type'])) ?></span>
                                                    <div class="text-muted fw-normal mt-1" style="font-size: 0.7rem;"><?= date('M j, Y', strtotime($plan['created_at'])) ?></div>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?= $plan['plan_id'] ?>" class="accordion-collapse collapse" data-bs-parent="#plansAccordion">
                                        <div class="accordion-body bg-white border-top p-4">
                                            
                                            <!-- Attachments Area -->
                                            <?php if (!empty($plan['file_path']) || !empty($plan['video_link'])): ?>
                                                <div class="mb-4 p-3 rounded-3" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                                                    <h6 class="fw-bold text-dark small mb-3 text-uppercase letter-spacing-1">Resources Attached</h6>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <?php if (!empty($plan['file_path'])): ?>
                                                            <a href="../<?= htmlspecialchars($plan['file_path']) ?>" target="_blank" class="attachment-badge <?= $plan['file_type'] === 'pdf' ? 'pdf' : 'image' ?>">
                                                                <i class="fa-solid <?= $plan['file_type'] === 'pdf' ? 'fa-file-pdf' : 'fa-image' ?>"></i>
                                                                <?= htmlspecialchars($plan['original_filename']) ?>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($plan['video_link'])): ?>
                                                            <a href="<?= htmlspecialchars($plan['video_link']) ?>" target="_blank" class="attachment-badge video-preview-btn">
                                                                <i class="fa-brands fa-youtube"></i> Watch Video
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <div class="bg-light p-3 rounded-3 mb-4" style="white-space: pre-wrap; font-family: monospace; font-size: 0.9rem; color:#334155; border-left: 3px solid #3b82f6;"><?= htmlspecialchars($plan['plan_content']) ?></div>
                                            
                                            <div class="d-flex justify-content-end pt-2 border-top">
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this plan?');">
                                                    <input type="hidden" name="action" value="delete_plan">
                                                    <input type="hidden" name="plan_id" value="<?= $plan['plan_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-4 fw-bold">
                                                        <i class="fa-solid fa-trash me-2"></i>Remove
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function updateFileName(input) {
    const dropZone      = document.getElementById('dropZone');
    const emptyState    = document.getElementById('uploadEmptyState');
    const selectedState = document.getElementById('uploadSelectedState');
    const fileIcon      = document.getElementById('uploadFileIcon');
    const fileName      = document.getElementById('uploadFileName');
    const fileSize      = document.getElementById('uploadFileSize');

    if (input.files && input.files.length > 0) {
        const file = input.files[0];
        const ext  = file.name.split('.').pop().toLowerCase();
        const isPdf = ext === 'pdf';

        // Set icon
        fileIcon.className = 'upload-file-icon ' + (isPdf ? 'pdf' : 'img');
        fileIcon.innerHTML = isPdf
            ? '<i class="fa-solid fa-file-pdf"></i>'
            : '<i class="fa-solid fa-image"></i>';

        // Set name + size
        fileName.textContent = file.name;
        fileSize.textContent = formatBytes(file.size);

        // Validation: Max 10MB
        if (file.size > 10 * 1024 * 1024) {
            alert('File is too large! Maximum size allowed is 10 MB.');
            clearFile();
            return;
        }

        // Switch state
        emptyState.style.display    = 'none';
        selectedState.classList.add('show');
        dropZone.classList.add('has-file');
    } else {
        clearFile();
    }
}

function clearFile() {
    const dropZone      = document.getElementById('dropZone');
    const emptyState    = document.getElementById('uploadEmptyState');
    const selectedState = document.getElementById('uploadSelectedState');
    const input         = document.getElementById('planFile');

    // Reset input
    input.value = '';

    // Restore empty state
    emptyState.style.display = '';
    selectedState.classList.remove('show');
    dropZone.classList.remove('has-file');
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('uploadRemoveBtn').addEventListener('click', function (e) {
        e.stopPropagation();
        clearFile();
    });
});
</script>

<?php require_once 'includes/trainer_footer.php'; ?>
