<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_trainer();

$pageTitle = 'My Notifications';
$trainer_id = $_SESSION['user_id'];

// Handle Mark as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $notif_id = (int)$_POST['mark_read'];
        $updateStmt = $pdo->prepare("UPDATE trainer_notifications SET is_read = 1 WHERE notification_id = ? AND trainer_id = ?");
        $updateStmt->execute([$notif_id, $trainer_id]);
        
        header("Location: notifications.php");
        exit;
    }
}

// Fetch all notifications
$notifsStmt = $pdo->prepare("SELECT * FROM trainer_notifications WHERE trainer_id = ? ORDER BY created_at DESC");
$notifsStmt->execute([$trainer_id]);
$notifications = $notifsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/trainer_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Notifications</h4>
        <p class="text-muted mb-0">Stay updated with system alerts and admin broadcasts.</p>
    </div>
    <div class="bg-white p-2 rounded-circle shadow-sm" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-size: 1.2rem;">
        <i class="fa-solid fa-bell"></i>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="p-5 text-center text-muted">
                <i class="fa-regular fa-bell-slash fs-1 mb-3 text-secondary opacity-50"></i>
                <h5>No notifications yet!</h5>
                <p>When you receive an alert, it will show up here.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush rounded-4">
                <?php foreach ($notifications as $notif): 
                    $isRead = $notif['is_read'] == 1;
                    $bgColorClass = $isRead ? 'bg-white' : 'bg-light';
                    $iconClass = 'fa-circle-info text-info';
                    if ($notif['type'] === 'success') $iconClass = 'fa-circle-check text-success';
                    if ($notif['type'] === 'warning') $iconClass = 'fa-triangle-exclamation text-warning';
                    if ($notif['type'] === 'danger') $iconClass = 'fa-circle-xmark text-danger';
                ?>
                    <div class="list-group-item list-group-item-action p-4 <?= $bgColorClass ?> border-bottom">
                        <div class="d-flex gap-3">
                            <div class="mt-1" style="font-size: 1.5rem;">
                                <i class="fa-solid <?= $iconClass ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="mb-1 fw-bold <?= $isRead ? 'text-secondary' : 'text-dark' ?>">
                                        <?= htmlspecialchars($notif['title']) ?>
                                        <?php if (!$isRead): ?>
                                            <span class="badge bg-primary ms-2" style="font-size:0.6rem;">NEW</span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted" style="font-size:0.75rem;">
                                        <?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?>
                                    </small>
                                </div>
                                <?php if (!empty($notif['message'])): ?>
                                    <p class="mb-2 text-muted" style="font-size:0.9rem;">
                                        <?= nl2br(htmlspecialchars($notif['message'])) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if (!$isRead): ?>
                                    <form method="POST" action="" class="mt-2">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="mark_read" value="<?= $notif['notification_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-check me-1"></i> Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/trainer_footer.php'; ?>
