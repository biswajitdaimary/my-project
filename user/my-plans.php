<?php
$pageTitle = 'My Workout & Diet Plans';
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_user();

$userId = $_SESSION['user_id'];

// Fetch plans assigned to this user
$stmt = $pdo->prepare("
    SELECT p.*, t.full_name as trainer_name 
    FROM client_workout_plans p
    JOIN trainers t ON p.trainer_id = t.trainer_id
    WHERE p.user_id = ? AND p.is_active = 1
    ORDER BY p.created_at DESC
");
$stmt->execute([$userId]);
$plans = $stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<div class="up-wrap">
    <div class="container-fluid px-0">
        <div class="d-flex">
            <?php require_once '../includes/sidebar-user.php'; ?>

            <main class="up-main flex-grow-1">
                <div class="container-fluid" style="max-width: 1000px; margin: 0 auto;">
                    <div class="row mb-4 align-items-center">
                        <div class="col">
                            <h2 class="fw-bold mb-0 text-dark">My Plans</h2>
                            <p class="text-muted mb-0">View workout routines and diet plans assigned by your trainers.</p>
                        </div>
                    </div>

            <div class="row">
                <div class="col-12">
                    <?php if (empty($plans)): ?>
                        <div class="card border-0 shadow-sm rounded-4 text-center p-5">
                            <div class="text-muted mb-3"><i class="fa-solid fa-clipboard-list fa-4x opacity-50"></i></div>
                            <h4 class="fw-bold text-dark">No Plans Yet</h4>
                            <p class="text-muted mb-4">You don't have any workout or diet plans assigned to you at the moment.</p>
                            <a href="book-trainer.php" class="btn btn-primary-custom rounded-pill px-4 py-2 fw-bold">
                                <i class="fa-solid fa-person-running me-2"></i>Book a Trainer
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($plans as $plan): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card border-0 shadow-sm rounded-4 h-100">
                                        <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="fw-bold mb-1"><?= htmlspecialchars($plan['plan_title']) ?></h5>
                                                <p class="text-muted small mb-0"><i class="fa-solid fa-user-tie me-1"></i>Trainer: <?= htmlspecialchars($plan['trainer_name']) ?></p>
                                            </div>
                                            <?php
                                            $badge = 'bg-primary';
                                            if ($plan['plan_type'] === 'diet') $badge = 'bg-success';
                                            if ($plan['plan_type'] === 'hybrid') $badge = 'bg-warning text-dark';
                                            ?>
                                            <span class="badge <?= $badge ?> rounded-pill px-3 py-2"><?= ucfirst(htmlspecialchars($plan['plan_type'])) ?></span>
                                        </div>
                                        <div class="card-body p-4">

                                            <!-- ── Resources Attached ──────────────────────── -->
                                            <?php if (!empty($plan['file_path']) || !empty($plan['video_link'])): ?>
                                                <div class="mb-4 p-3 rounded-4" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                                    <h6 class="fw-bold text-dark small mb-2 text-uppercase" style="letter-spacing:1px;">
                                                        <i class="fa-solid fa-paperclip me-2 text-primary"></i>Resources Attached
                                                    </h6>

                                                    <div class="d-flex flex-column gap-2">

                                                        <?php if (!empty($plan['file_path'])): ?>
                                                            <?php
                                                            $ext = strtolower(pathinfo($plan['file_path'], PATHINFO_EXTENSION));
                                                            $isImage = ($plan['file_type'] === 'image' || in_array($ext, ['jpg','jpeg','png']));
                                                            $isPdf   = ($plan['file_type'] === 'pdf');
                                                            ?>
                                                            <?php if ($isImage): ?>
                                                                <!-- Image attachment -->
                                                                <a href="../<?= htmlspecialchars($plan['file_path']) ?>"
                                                                   download="<?= htmlspecialchars($plan['original_filename']) ?>"
                                                                   class="d-flex align-items-center gap-3 p-2 bg-white border rounded-3 text-decoration-none"
                                                                   style="overflow:hidden;">
                                                                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-2"
                                                                         style="width:40px;height:40px;background:#e0e7ff;">
                                                                        <i class="fa-solid fa-image" style="color:#4f46e5;font-size:1.1rem;"></i>
                                                                    </div>
                                                                    <div class="flex-grow-1" style="min-width:0;">
                                                                        <div class="fw-bold text-dark"
                                                                             style="font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                                            <?= htmlspecialchars($plan['original_filename']) ?>
                                                                        </div>
                                                                        <div class="text-muted" style="font-size:.72rem;">Image</div>
                                                                    </div>
                                                                    <span class="badge rounded-pill flex-shrink-0"
                                                                          style="font-size:.7rem; padding: 0.5rem 1rem; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                                                                        <i class="fa-solid fa-download me-1"></i>Download
                                                                    </span>
                                                                </a>
                                                            <?php elseif ($isPdf): ?>
                                                                <!-- PDF attachment -->
                                                                <a href="../<?= htmlspecialchars($plan['file_path']) ?>"
                                                                   target="_blank"
                                                                   class="d-flex align-items-center gap-3 p-2 bg-white border rounded-3 text-decoration-none"
                                                                   style="overflow:hidden;">
                                                                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-2"
                                                                         style="width:40px;height:40px;background:#eff6ff;">
                                                                        <i class="fa-solid fa-file-pdf" style="color:#3b82f6;font-size:1.1rem;"></i>
                                                                    </div>
                                                                    <div class="flex-grow-1" style="min-width:0;">
                                                                        <div class="fw-bold text-dark"
                                                                             style="font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                                            <?= htmlspecialchars($plan['original_filename']) ?>
                                                                        </div>
                                                                        <div class="text-muted" style="font-size:.72rem;">PDF Document</div>
                                                                    </div>
                                                                    <span class="badge rounded-pill flex-shrink-0"
                                                                          style="font-size:.7rem; padding: 0.5rem 1rem; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                                                                        <i class="fa-solid fa-download me-1"></i>Download
                                                                    </span>
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php endif; ?>

                                                        <?php if (!empty($plan['video_link'])): ?>
                                                            <?php
                                                            // Extract YouTube video ID for thumbnail
                                                            $ytId = null;
                                                            preg_match(
                                                                '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i',
                                                                $plan['video_link'], $ytMatch
                                                            );
                                                            if (!empty($ytMatch[1])) $ytId = $ytMatch[1];
                                                            $thumbUrl = $ytId ? "https://img.youtube.com/vi/{$ytId}/mqdefault.jpg" : null;
                                                            ?>
                                                            <!-- Video link — thumbnail preview card -->
                                                            <a href="<?= htmlspecialchars($plan['video_link']) ?>"
                                                               target="_blank" rel="noopener"
                                                               class="d-flex align-items-center gap-3 p-2 bg-white border rounded-3 text-decoration-none"
                                                               style="overflow:hidden;">
                                                                <?php if ($thumbUrl): ?>
                                                                    <!-- YouTube thumbnail -->
                                                                    <div class="flex-shrink-0 position-relative rounded-2 overflow-hidden"
                                                                         style="width:80px;height:52px;background:#000;">
                                                                        <img src="<?= htmlspecialchars($thumbUrl) ?>"
                                                                             alt="Video thumbnail"
                                                                             style="width:100%;height:100%;object-fit:cover;display:block;">
                                                                        <!-- Play overlay icon -->
                                                                        <div class="position-absolute top-50 start-50 translate-middle d-flex align-items-center justify-content-center"
                                                                             style="width:24px;height:24px;background:rgba(31, 109, 235, 0.7);border-radius:50%;">
                                                                            <i class="fa-solid fa-play" style="color:#fff;font-size:.55rem;margin-left:2px;"></i>
                                                                        </div>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <!-- Fallback icon if not a YouTube link -->
                                                                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-2"
                                                                         style="width:80px;height:52px;background:#eff6ff;">
                                                                        <i class="fa-brands fa-youtube" style="color:#3b82f6;font-size:1.6rem;"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="flex-grow-1" style="min-width:0;">
                                                                    <div class="fw-bold text-dark" style="font-size:.82rem;">Video Resource</div>
                                                                    <div class="text-muted"
                                                                         style="font-size:.72rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                                        <?= htmlspecialchars($plan['video_link']) ?>
                                                                    </div>
                                                                </div>
                                                                <span class="badge rounded-pill flex-shrink-0"
                                                                      style="font-size:.7rem; padding: 0.5rem 1rem; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                                                                    <i class="fa-solid fa-play me-1"></i>Watch
                                                                </span>
                                                            </a>
                                                        <?php endif; ?>

                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <!-- ────────────────────────────────────────────── -->

                                            <div class="bg-light p-3 rounded-3" style="white-space: pre-wrap; font-family: monospace; font-size: 0.95rem; min-height: 200px; max-height: 400px; overflow-y: auto; border-left: 4px solid var(--primary-color);"><?= htmlspecialchars($plan['plan_content']) ?></div>
                                        </div>
                                        <div class="card-footer bg-white border-0 pb-4 text-muted small">
                                            <i class="fa-solid fa-clock me-1"></i>Assigned on <?= date('M j, Y', strtotime($plan['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
                </div><!-- /.container-fluid -->
            </main><!-- /.up-main -->
        </div><!-- /.d-flex -->
    </div><!-- /.container-fluid -->
</div><!-- /.up-wrap -->

<?php require_once '../includes/footer.php'; ?>
