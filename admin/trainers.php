<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

$pageTitle = 'Manage Trainers';
$success   = trim((string) ($_GET['success'] ?? ''));
$error     = trim((string) ($_GET['error'] ?? ''));

function trainers_redirect_with_status(string $type, string $message): void
{
    header('Location: trainers.php?' . http_build_query([$type => $message]));
    exit;
}

// ── Handle Delete & Activate (soft) ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['delete', 'activate'], true)) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        trainers_redirect_with_status('error', 'Invalid security token.');
    }

    $trainerId = (int) ($_POST['trainer_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($trainerId <= 0) {
        trainers_redirect_with_status('error', 'Invalid trainer selected.');
    }

    try {
        if ($action === 'delete') {
            $stmt = $pdo->prepare("UPDATE trainers SET is_active = 0 WHERE trainer_id = ? AND is_active = 1");
            $stmt->execute([$trainerId]);
            if ($stmt->rowCount() > 0) {
                trainers_redirect_with_status('success', 'Trainer deactivated successfully.');
            }
            trainers_redirect_with_status('error', 'Trainer not found or already inactive.');
        } else if ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE trainers SET is_active = 1 WHERE trainer_id = ? AND is_active = 0");
            $stmt->execute([$trainerId]);
            if ($stmt->rowCount() > 0) {
                trainers_redirect_with_status('success', 'Trainer activated successfully.');
            }
            trainers_redirect_with_status('error', 'Trainer not found or already active.');
        }
    } catch (PDOException $e) {
        trainers_redirect_with_status('error', 'Could not ' . $action . ' trainer.');
    }
}

// ── Load trainers ────────────────────────────────────────────────────────
try {
    $trainers = $pdo->query("
        SELECT t.*, 
        (SELECT COUNT(DISTINCT user_id) FROM trainer_bookings WHERE trainer_id = t.trainer_id) as active_clients
        FROM trainers t 
        ORDER BY t.is_active DESC, t.full_name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $trainers = [];
}

require_once 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold m-0">Manage Trainers
            <span class="badge bg-secondary ms-2 fs-6"><?= count($trainers) ?></span>
        </h3>
        <p class="text-muted small mb-0 mt-1">Add, edit or deactivate your gym's training team.</p>
    </div>
    <a href="trainers/add.php" class="btn btn-primary-custom rounded-pill px-4">
        <i class="fa-solid fa-plus me-2"></i>Add Trainer
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success rounded-4"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (empty($trainers)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fa-solid fa-person-running fa-3x mb-3 d-block opacity-25"></i>
        No trainers found. <a href="trainers/add.php">Add your first trainer.</a>
    </div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($trainers as $t): ?>
    <?php
        $trainerId = (int) $t['trainer_id'];
        $isActive = !empty($t['is_active']);
        $photo = $t['photo'] ?? '';
        $isUrl = str_starts_with($photo, 'http');
        $photoSrc = $photo
            ? ($isUrl ? $photo : SITE_URL . '/' . ltrim($photo, '/'))
            : null;
    ?>
    <div class="col-sm-6 col-lg-4 col-xl-3">
        <div class="trainer-card <?= $isActive ? '' : 'trainer-card--inactive' ?>">
            <div class="trainer-card__photo-wrap">
                <?php if ($photoSrc): ?>
                    <img src="<?= htmlspecialchars($photoSrc) ?>" alt="<?= htmlspecialchars($t['full_name']) ?>" class="trainer-card__photo">
                <?php else: ?>
                    <?php 
                        // Generate a consistent gradient based on the name
                        $colors = [
                            ['#FF6B35', '#ff8c61'],
                            ['#1A1A2E', '#2d2d55'],
                            ['#4a00e0', '#8e2de2'],
                            ['#11998e', '#38ef7d'],
                            ['#b21f1f', '#fdbb2d']
                        ];
                        $cIdx = crc32($t['full_name']) % count($colors);
                        $grad = $colors[$cIdx];
                    ?>
                    <div class="trainer-card__initials" style="background: linear-gradient(135deg, <?= $grad[0] ?>, <?= $grad[1] ?>);">
                        <?= strtoupper(substr($t['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?> trainer-card__status-badge">
                    <?= $isActive ? 'Active' : 'Inactive' ?>
                </span>
            </div>
            <div class="trainer-card__body">
                <h6 class="trainer-card__name"><?= htmlspecialchars($t['full_name']) ?></h6>
                <p class="trainer-card__spec"><?= htmlspecialchars($t['specialization']) ?></p>
                <?php if (!empty($t['custom_id'])): ?>
                <div class="mb-2">
                    <span class="badge rounded-pill px-3 py-1"
                          style="background:#ede9fe;color:#7c3aed;letter-spacing:1.5px;font-family:monospace;font-size:.72rem;">
                        <i class="fa-solid fa-fingerprint me-1"></i><?= htmlspecialchars($t['custom_id']) ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="trainer-card__meta">
                    <span><i class="fa-solid fa-users text-primary me-1"></i><?= (int)$t['active_clients'] ?> Clients</span>
                    <span><i class="fa-solid fa-star text-warning me-1"></i><?= number_format((float)$t['rating'], 1) ?></span>
                    <?php if ($t['experience_years']): ?>
                    <span><i class="fa-solid fa-clock text-muted me-1"></i><?= $t['experience_years'] ?>yr</span>
                    <?php endif; ?>
                    <?php if ($t['hourly_rate']): ?>
                    <span><i class="fa-solid fa-indian-rupee-sign text-muted me-1"></i><?= number_format($t['hourly_rate'], 0) ?>/hr</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="trainer-card__footer">
                <div class="d-flex gap-2 w-100 flex-wrap">
                    <?php if ($isActive): ?>
                    <a href="trainer_schedule.php?trainer_id=<?= $trainerId ?>" class="btn btn-sm btn-outline-secondary flex-grow-1 d-flex justify-content-center align-items-center" title="Manage Schedule">
                        <i class="fa-solid fa-calendar-week me-1"></i> <span style="font-size: 0.8rem;">Schedule</span>
                    </a>
                    <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 d-flex justify-content-center align-items-center" title="Activate trainer to manage schedule" disabled>
                        <i class="fa-solid fa-calendar-week me-1"></i> <span style="font-size: 0.8rem;">Schedule</span>
                    </button>
                    <?php endif; ?>
                    <a href="trainers/edit.php?id=<?= $trainerId ?>" class="btn btn-sm btn-outline-primary flex-grow-1 d-flex justify-content-center align-items-center" title="Edit">
                        <i class="fa-solid fa-pen-to-square me-1"></i> <span style="font-size: 0.8rem;">Edit</span>
                    </a>
                    <?php if ($isActive): ?>
                    <form method="POST" class="m-0" onsubmit="return confirm('Deactivate this trainer?')" style="width: 40px; flex-shrink: 0;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="trainer_id" value="<?= $trainerId ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100 h-100" title="Deactivate">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST" class="m-0" onsubmit="return confirm('Activate this trainer?')" style="width: 40px; flex-shrink: 0;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="trainer_id" value="<?= $trainerId ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success w-100 h-100" title="Activate">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.trainer-card {
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 2px 16px rgba(0,0,0,0.07);
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.trainer-card:hover { transform: translateY(-4px); box-shadow: 0 8px 32px rgba(0,0,0,0.13); }
.trainer-card--inactive { opacity: 0.55; }
.trainer-card__photo-wrap { position: relative; height: 180px; background: #eee; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.trainer-card__photo { width: 100%; height: 180px; object-fit: cover; display: block; }
.trainer-card__initials { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 4.5rem; font-weight: 800; color: rgba(255,255,255,0.9); }
.trainer-card__status-badge { position: absolute; top: 12px; left: 12px; font-size: 0.7rem; letter-spacing: 0.05em; padding: 5px 10px; border-radius: 100px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
.trainer-card__body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
.trainer-card__name { font-weight: 800; font-size: 1.15rem; margin-bottom: 2px; color: #1a1a2e; }
.trainer-card__spec { font-size: 0.82rem; color: #FF6B35; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.75rem; }
.trainer-card__meta { display: flex; gap: 0.85rem; font-size: 0.8rem; color: #555; flex-wrap: wrap; margin-top: auto; }
.trainer-card__footer { padding: 0.85rem 1.25rem; border-top: 1px solid #f0f0f5; background: #fafafa; }
</style>

<?php require_once 'includes/admin_footer.php'; ?>
