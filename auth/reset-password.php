<?php
$pageTitle = 'Reset Password';
require_once '../config/config.php';
require_once '../helpers/auth_check.php';

guest_only();

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token) && empty($_POST['token'])) {
    header("Location: login.php");
    exit;
}

$tokenToVerify = $_POST['token'] ?? $token;

// Verify Token exist and is not expired
try {
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at >= NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$tokenToVerify]);
    $resetData = $stmt->fetch();

    if (!$resetData) {
        $error = "This password reset token is invalid or has expired.";
    }
} catch(PDOException $e) {
    $error = "Database error.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $resetData) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid security token.";
    } else {
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($password) || empty($confirmPassword)) {
            $error = "Please fill all fields.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            try {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $email = $resetData['email'];

                // Update password
                $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                $updateStmt->execute([$hashedPassword, $email]);

                // Delete tokens for this email
                $delStmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $delStmt->execute([$email]);

                $success = "Your password has been successfully reset. You can now log in.";
            } catch(PDOException $e) {
                $error = "An error occurred. Please try again.";
            }
        }
    }
}
require_once '../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-center min-vh-100 bg-light" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card card-custom p-4 p-md-5 border-0 shadow-sm">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold">Set New Password</h4>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger p-2 small"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= $error ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success p-2 small"><i class="fa-solid fa-check-circle me-1"></i> <?= $success ?></div>
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-primary-custom w-100 py-2">Go to Login</a>
                        </div>
                    <?php elseif (!$error && $resetData): ?>
                        <form method="POST" action="reset-password.php">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($tokenToVerify) ?>">
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold">New Password</label>
                                <input type="password" class="form-control" name="password" required minlength="8">
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="8">
                            </div>
                            <button type="submit" class="btn btn-primary-custom w-100 py-2">Update Password</button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
