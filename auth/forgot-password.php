<?php
$pageTitle = 'Forgot Password';
require_once '../config/config.php';
require_once '../helpers/auth_check.php';

guest_only();

$error = '';
$success = '';
$mockResetLink = ''; // For testing without email setup

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid security token.";
    } else {
        $email = trim($_POST['email']);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    // Generate token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Store token
                    $insertStmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $insertStmt->execute([$email, $token, $expires]);

                    // Send email logic would go here
                    $mockResetLink = SITE_URL . "/auth/reset-password.php?token=" . $token;
                    
                    $success = "Instructions sent! Check your inbox.";
                } else {
                    // Prevent enumeration by giving the same success message
                    $success = "Instructions sent! Check your inbox.";
                }
            } catch(PDOException $e) {
                $error = "System error. Please try again later.";
            }
        }
    }
}
require_once '../helpers/site_settings_helper.php';
$authBrandName = site_settings_get('gym_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($authBrandName) ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6fb;
            color: #1a1a2e;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        .auth-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 420px;
            padding: 3rem 2.5rem;
            position: relative;
            z-index: 1;
        }
        
        /* Decorative Background */
        body::before {
            content: '';
            position: fixed;
            top: -20vh;
            left: -10vw;
            width: 50vw;
            height: 50vw;
            background: radial-gradient(circle, rgba(255,107,53,0.08) 0%, rgba(255,107,53,0) 70%);
            border-radius: 50%;
            z-index: 0;
        }
        
        body::after {
            content: '';
            position: fixed;
            bottom: -20vh;
            right: -10vw;
            width: 40vw;
            height: 40vw;
            background: radial-gradient(circle, rgba(102,126,234,0.08) 0%, rgba(102,126,234,0) 70%);
            border-radius: 50%;
            z-index: 0;
        }
        
        .icon-circle {
            width: 80px;
            height: 80px;
            background: rgba(255, 107, 53, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: #FF6B35;
            font-size: 2rem;
            position: relative;
        }
        
        .icon-circle.success-anim {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        
        @keyframes popIn {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #1a1a2e;
        }
        
        .auth-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .form-floating-custom {
            margin-bottom: 1.5rem;
        }
        
        .form-control-custom {
            width: 100%;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            font-weight: 500;
            color: #1a1a2e;
            background-color: #f8f9fc;
            border: 2px solid #eef0f7;
            border-radius: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
            text-align: center;
        }
        
        .form-control-custom:focus {
            outline: none;
            border-color: #FF6B35;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }
        
        .btn-auth {
            background: linear-gradient(135deg, #FF6B35 0%, #ff8c5a 100%);
            color: white;
            font-weight: 700;
            font-size: 1rem;
            padding: 1rem;
            border-radius: 1rem;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
            cursor: pointer;
        }
        
        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.4);
        }
        
        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: color 0.2s;
            text-align: center;
            width: 100%;
        }
        
        .back-link:hover {
            color: #1a1a2e;
        }
        
        .alert-custom {
            border-radius: 0.75rem;
            border: none;
            padding: 1rem 1.25rem;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background-color: #fef2f2;
            color: #b91c1c;
        }
        
        .mock-email-box {
            margin-top: 1.5rem;
            padding: 1.25rem;
            background: #f8f9fc;
            border: 1px dashed #cbd5e1;
            border-radius: 0.75rem;
            text-align: left;
            font-size: 0.85rem;
            color: #475569;
        }
        
        .mock-email-box a {
            color: #FF6B35;
            word-break: break-all;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="auth-card">
    
    <?php if ($success): ?>
        <!-- Success State -->
        <div class="icon-circle success-anim">
            <i class="fa-solid fa-paper-plane"></i>
        </div>
        <div class="auth-header">
            <h2 class="auth-title">Check your email</h2>
            <p class="auth-subtitle">We've sent a password reset link to your email address.</p>
        </div>
        
        <?php if ($mockResetLink): ?>
        <div class="mock-email-box">
            <strong><i class="fa-solid fa-vial-circle-check me-1"></i> Testing Mode Active</strong><br>
            Because email sending is disabled, click this mock link to test the reset flow:<br>
            <a href="<?= $mockResetLink ?>"><?= $mockResetLink ?></a>
        </div>
        <?php endif; ?>
        
        <a href="login.php" class="btn-auth text-center text-decoration-none d-inline-block mt-3" style="box-sizing:border-box;">Return to Log In</a>
        
    <?php else: ?>
        <!-- Default State -->
        <div class="icon-circle">
            <i class="fa-solid fa-lock-keyhole"></i>
        </div>
        <div class="auth-header">
            <h2 class="auth-title">Forgot Password?</h2>
            <p class="auth-subtitle">Enter your email and we'll send you instructions to reset your password.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert-custom alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot-password.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-floating-custom">
                <input type="email" class="form-control-custom" name="email" placeholder="Enter your email address" required autocomplete="email">
            </div>
            
            <button type="submit" class="btn-auth">Send Reset Link</button>
        </form>
        
        <a href="login.php" class="back-link">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Log In
        </a>
    <?php endif; ?>
    
</div>

</body>
</html>
