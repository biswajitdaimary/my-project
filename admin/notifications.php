<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

$pageTitle = 'Broadcast Alerts';
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMsg = "Invalid request token. Please try again.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $audience = $_POST['audience'] ?? '';

        if (empty($title) || empty($audience)) {
            $errorMsg = "Title and Audience are required.";
        } elseif (!in_array($type, ['info', 'success', 'warning', 'danger'])) {
            $errorMsg = "Invalid alert type.";
        } else {
            try {
                $pdo->beginTransaction();
                $count = 0;

                // Send to Members
                if ($audience === 'members' || $audience === 'everyone') {
                    $uStmt = $pdo->query("SELECT user_id FROM users WHERE role = 'user'");
                    $users = $uStmt->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($users)) {
                        $insertU = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                        foreach ($users as $uid) {
                            $insertU->execute([$uid, $title, $message, $type]);
                            $count++;
                        }
                    }
                }

                // Send to Trainers
                if ($audience === 'trainers' || $audience === 'everyone') {
                    $tStmt = $pdo->query("SELECT trainer_id FROM trainers");
                    $trainers = $tStmt->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($trainers)) {
                        $insertT = $pdo->prepare("INSERT INTO trainer_notifications (trainer_id, title, message, type) VALUES (?, ?, ?, ?)");
                        foreach ($trainers as $tid) {
                            $insertT->execute([$tid, $title, $message, $type]);
                            $count++;
                        }
                    }
                }

                $pdo->commit();
                $successMsg = "Broadcast sent successfully to $count recipient(s)!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = "Failed to send broadcast: " . $e->getMessage();
            }
        }
    }
}

require_once 'includes/admin_header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-0 text-dark">Broadcast Alerts</h4>
                <p class="text-muted mb-0" style="font-size:0.9rem;">Send announcements and alerts to your members and trainers.</p>
            </div>
            <div class="bg-white p-2 rounded-circle shadow-sm" style="width:48px;height:48px;display:flex;align-items:center;justify-content:center;color:#FF6B35;font-size:1.2rem;">
                <i class="fa-solid fa-bullhorn"></i>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><i class="fa-solid fa-circle-xmark me-2"></i><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold" style="font-size:0.9rem;color:#1a1a2e;">Target Audience <span class="text-danger">*</span></label>
                        <select name="audience" class="form-select form-select-lg" style="font-size:0.95rem;border-radius:0.75rem;background-color:#f8f9fc;border:1px solid #eef0f7;" required>
                            <option value="" disabled selected>Select who should receive this</option>
                            <option value="everyone">Everyone (All Members & Trainers)</option>
                            <option value="members">All Members Only</option>
                            <option value="trainers">All Trainers Only</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold" style="font-size:0.9rem;color:#1a1a2e;">Notification Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-lg" style="font-size:0.95rem;border-radius:0.75rem;background-color:#f8f9fc;border:1px solid #eef0f7;" placeholder="e.g. System Maintenance, Holiday Schedule..." required maxlength="200">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold" style="font-size:0.9rem;color:#1a1a2e;">Message Details</label>
                        <textarea name="message" class="form-control form-control-lg" rows="4" style="font-size:0.95rem;border-radius:0.75rem;background-color:#f8f9fc;border:1px solid #eef0f7;" placeholder="Optional details for the notification..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold" style="font-size:0.9rem;color:#1a1a2e;">Alert Type (Color)</label>
                        <div class="d-flex gap-3 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="tInfo" value="info" checked>
                                <label class="form-check-label text-info fw-bold" for="tInfo"><i class="fa-solid fa-circle-info me-1"></i>Info</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="tSuccess" value="success">
                                <label class="form-check-label text-success fw-bold" for="tSuccess"><i class="fa-solid fa-circle-check me-1"></i>Success</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="tWarning" value="warning">
                                <label class="form-check-label" for="tWarning" style="color:#f59e0b;font-weight:bold;"><i class="fa-solid fa-triangle-exclamation me-1"></i>Warning</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="tDanger" value="danger">
                                <label class="form-check-label text-danger fw-bold" for="tDanger"><i class="fa-solid fa-circle-xmark me-1"></i>Urgent</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 pt-3 border-top" style="border-color:#f0f2f7!important;">
                        <button type="submit" class="btn btn-lg w-100 fw-bold rounded-pill" style="background:#FF6B35;color:#fff;">
                            <i class="fa-solid fa-paper-plane me-2"></i>Send Broadcast Now
                        </button>
                        <p class="text-center text-muted mt-3 mb-0" style="font-size:0.8rem;">
                            <i class="fa-solid fa-shield-halved me-1"></i> Notifications are sent instantly and cannot be undone.
                        </p>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
