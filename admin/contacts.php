<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security validation failed.";
    } else {
        if ($_POST['action'] === 'mark_read' && isset($_POST['message_id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE message_id = ?");
                $stmt->execute([$_POST['message_id']]);
                $success = "Message marked as read.";
            } catch(PDOException $e) { $error = "Could not update message."; }
        } elseif ($_POST['action'] === 'delete' && isset($_POST['message_id'])) {
            try {
                $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE message_id = ?");
                $stmt->execute([$_POST['message_id']]);
                $success = "Message deleted permanently.";
            } catch(PDOException $e) { $error = "Could not delete message."; }
        }
        header("Location: contacts.php");
        exit;
    }
}

try {
    $stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
    $messages = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Database error.");
}

$pageTitle = 'Contact Messages';
require_once 'includes/admin_header.php';
?>

<style>
/* ── Premium Modern UI Styles ── */
.inbox-header {
    background: linear-gradient(135deg, #1A1A2E 0%, #0f3460 100%);
    border-radius: 1.25rem;
    padding: 1.25rem 1.75rem;
    color: white;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 10px 25px rgba(15, 52, 96, 0.2);
    position: relative;
    overflow: hidden;
}

.inbox-header::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -20px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
}

.msg-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 1.25rem;
    padding: 1rem 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    display: flex;
    gap: 1rem;
}

.msg-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.06);
}

.msg-card.unread {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.msg-card.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: linear-gradient(to bottom, #3b82f6, #8b5cf6);
    border-radius: 1rem 0 0 1rem;
}

.msg-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #c026d3);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(192, 38, 211, 0.2);
}

.msg-content-wrapper {
    flex-grow: 1;
    min-width: 0;
}

.msg-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.msg-sender {
    font-weight: 800;
    font-size: 1rem;
    color: #1e293b;
    margin: 0 0 0.15rem 0;
}

.msg-email {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

.msg-date {
    font-size: 0.8rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.35rem 0.85rem;
    border-radius: 20px;
    font-weight: 600;
}

.msg-body {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    color: #334155;
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 0.75rem;
}

.msg-card.unread .msg-body {
    background: #ffffff;
}

.msg-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.btn-action {
    border-radius: 25px;
    padding: 0.3rem 1rem;
    font-size: 0.8rem;
    font-weight: 700;
    transition: all 0.2s;
}

.btn-action-reply { background: #eff6ff; color: #2563eb; border: none; }
.btn-action-reply:hover { background: #2563eb; color: #fff; transform: translateY(-2px); }

.btn-action-read { background: #f0fdf4; color: #16a34a; border: none; }
.btn-action-read:hover { background: #16a34a; color: #fff; transform: translateY(-2px); }

.btn-action-delete { background: #fef2f2; color: #dc2626; border: none; }
.btn-action-delete:hover { background: #dc2626; color: #fff; transform: translateY(-2px); }
</style>

<div class="inbox-header">
    <div style="position:relative; z-index:2;">
        <h3 class="fw-bold mb-1"><i class="fa-solid fa-inbox me-2 opacity-75"></i>Contact Inbox</h3>
        <p class="mb-0 opacity-75 small fw-medium">Manage inquiries and feedback from your users.</p>
    </div>
    <?php 
    $unreadCount = count(array_filter($messages, fn($m) => !$m['is_read']));
    if ($unreadCount > 0): 
    ?>
        <div class="bg-white text-primary px-4 py-2 rounded-pill fw-bold shadow-sm" style="position:relative; z-index:2; color: #1A1A2E !important;">
            <i class="fa-solid fa-bell me-2" style="color: #e63946;"></i><?= $unreadCount ?> New Message<?= $unreadCount > 1 ? 's' : '' ?>
        </div>
    <?php else: ?>
        <div class="bg-white bg-opacity-10 px-4 py-2 rounded-pill text-white small fw-medium border border-white border-opacity-25" style="position:relative; z-index:2;">
            <i class="fa-solid fa-check-double me-2 text-success"></i>All caught up!
        </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger px-4 py-3 border-0 shadow-sm rounded-4 text-sm fw-bold"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success px-4 py-3 border-0 shadow-sm rounded-4 text-sm fw-bold" id="successAlert"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="messages-wrapper">
    <?php if(empty($messages)): ?>
        <div class="text-center py-5 mt-4">
            <div style="background: #f8fafc; width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto;">
                <i class="fa-regular fa-envelope-open fa-3x text-muted opacity-50"></i>
            </div>
            <h5 class="fw-bold text-dark">Your inbox is empty</h5>
            <p class="text-muted small">When users send messages, they will appear here.</p>
        </div>
    <?php else: ?>
        <?php foreach($messages as $m): 
            $initials = strtoupper(substr($m['name'], 0, 1));
        ?>
            <div class="msg-card <?= !$m['is_read'] ? 'unread' : '' ?>" data-aos="fade-up">
                <div class="msg-avatar">
                    <?= $initials ?>
                </div>
                
                <div class="msg-content-wrapper">
                    <div class="msg-header-row">
                        <div>
                            <h5 class="msg-sender">
                                <?= htmlspecialchars($m['name']) ?>
                                <?php if(!$m['is_read']): ?>
                                    <span class="badge rounded-pill ms-2" style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); font-size:0.65rem; vertical-align:middle; letter-spacing: 1px;">NEW</span>
                                <?php endif; ?>
                            </h5>
                            <div class="msg-email"><i class="fa-solid fa-envelope me-2 opacity-50"></i><?= htmlspecialchars($m['email']) ?></div>
                        </div>
                        <div class="msg-date">
                            <i class="fa-regular fa-calendar me-1"></i> <?= date('M d, Y', strtotime($m['created_at'])) ?>
                            <span class="mx-1 opacity-25">|</span> 
                            <i class="fa-regular fa-clock me-1"></i> <?= date('h:i A', strtotime($m['created_at'])) ?>
                        </div>
                    </div>
                    
                    <div class="msg-body">
                        <?= nl2br(htmlspecialchars($m['message'])) ?>
                    </div>
                    
                    <div class="msg-actions">
                        <a href="mailto:<?= htmlspecialchars($m['email']) ?>" class="btn btn-action btn-action-reply">
                            <i class="fa-solid fa-reply me-1"></i> Reply
                        </a>
                        
                        <?php if(!$m['is_read']): ?>
                            <form method="POST" action="contacts.php" class="m-0 d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="message_id" value="<?= $m['message_id'] ?>">
                                <button type="submit" class="btn btn-action btn-action-read">
                                    <i class="fa-solid fa-check-double me-1"></i> Mark as Read
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" action="contacts.php" class="m-0 d-inline" onsubmit="return confirm('Are you sure you want to delete this message permanently?');">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="message_id" value="<?= $m['message_id'] ?>">
                            <button type="submit" class="btn btn-action btn-action-delete">
                                <i class="fa-regular fa-trash-can me-1"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    setTimeout(function() {
        let alert = document.getElementById('successAlert');
        if(alert) {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }
    }, 3000);
</script>

<?php require_once 'includes/admin_footer.php'; ?>
