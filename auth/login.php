<?php
$pageTitle = 'Log In';
require_once '../config/config.php';
require_once '../helpers/auth_check.php';

// Prevent logged-in users from seeing login page
guest_only();

$error = '';

// Rate limiting setup
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_last_attempt'] = time();
}

// Reset attempts if 1 minute has passed
if (time() - $_SESSION['login_last_attempt'] > 60) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid security token. Please refresh the page.";
    } elseif ($_SESSION['login_attempts'] >= 5) {
        $error = "Too many failed attempts. Please try again in 1 minute.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if ($user['is_active'] == 0) {
                        $error = "Your account has been suspended.";
                    } else {
                        if ((int) $user['is_verified'] === 0) {
                            $verifyStmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE user_id = ?");
                            $verifyStmt->execute([$user['user_id']]);
                            $user['is_verified'] = 1;
                        }

                        // Login Success
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email'] = $user['email'];
                        
                        // Reset rate limits
                        $_SESSION['login_attempts'] = 0;

                        if ($remember) {
                            persist_remember_login('user', (int) $user['user_id']);
                        } else {
                            clear_persisted_remember_login();
                        }

                        // Redirect logic
                        if (isset($_SESSION['redirect_url'])) {
                            $redirect = $_SESSION['redirect_url'];
                            unset($_SESSION['redirect_url']);
                            if (is_safe_internal_redirect_path($redirect)) {
                                header("Location: " . $redirect);
                            } else {
                                header("Location: " . SITE_URL . "/user/dashboard.php");
                            }
                        } else {
                            if ($user['role'] === 'admin') {
                                header("Location: " . SITE_URL . "/admin/dashboard.php");
                            } else {
                                header("Location: " . SITE_URL . "/user/dashboard.php");
                            }
                        }
                        exit;
                    }
                } else {
                    // Check if the user is a trainer instead
                    $stmtTrainer = $pdo->prepare("SELECT * FROM trainers WHERE email = ? LIMIT 1");
                    $stmtTrainer->execute([$email]);
                    $trainer = $stmtTrainer->fetch();

                    if ($trainer && password_verify($password, $trainer['password_hash'])) {
                        if (!$trainer['is_active']) {
                            $error = "Your account is currently inactive. Please contact administration.";
                        } else {
                            // Login Success for Trainer
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $trainer['trainer_id'];
                            $_SESSION['role'] = 'trainer';
                            $_SESSION['full_name'] = $trainer['full_name'];
                            $_SESSION['email'] = $trainer['email'];
                            
                            $_SESSION['login_attempts'] = 0;

                            if ($remember) {
                                persist_remember_login('trainer', (int) $trainer['trainer_id']);
                            } else {
                                clear_persisted_remember_login();
                            }

                            // Always redirect trainers to their dashboard to prevent accidental user/admin URL redirects
                            if (isset($_SESSION['redirect_url'])) {
                                unset($_SESSION['redirect_url']);
                            }
                            header("Location: " . SITE_URL . "/trainer/dashboard.php");
                            exit;
                        }
                    } else {
                        // Neither user nor trainer matched
                        error_log("LOGIN FAILED. User exists? " . ($user ? 'Yes' : 'No') . " Trainer exists? " . ($trainer ? 'Yes' : 'No'));
                        $error = "Incorrect email or password.";
                        $_SESSION['login_attempts']++;
                        $_SESSION['login_last_attempt'] = time();
                    }
                }
            } catch(PDOException $e) {
                $error = "System error. Please try again later.";
            }
        }
    }
}

// Don't include header.php as we want a full custom split screen
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6fb;
            color: #1a1a2e;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        .split-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .split-left {
            flex: 1;
            background: linear-gradient(135deg, #1A1A2E 0%, #0f3460 100%);
            color: white;
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        
        .split-left::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: rgba(255, 107, 53, 0.1);
            border-radius: 50%;
            z-index: 0;
        }
        
        .split-left::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 300px;
            height: 300px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 50%;
            z-index: 0;
        }
        
        .split-left-content {
            position: relative;
            z-index: 1;
        }
        
        .brand-logo {
            font-size: 1.75rem;
            font-weight: 900;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 4rem;
        }
        
        .brand-logo i {
            color: #FF6B35;
        }
        
        .hero-text {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
        }
        
        .hero-text span {
            color: #FF6B35;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .feature-list li {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
            font-size: 1.1rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .feature-icon {
            width: 32px;
            height: 32px;
            background: rgba(255, 107, 53, 0.2);
            color: #FF6B35;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .split-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: white;
            position: relative;
        }
        
        .auth-form-container {
            width: 100%;
            max-width: 420px;
        }
        
        .auth-header {
            margin-bottom: 2rem;
        }
        
        .auth-title {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #1a1a2e;
        }
        
        .auth-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .form-floating-custom {
            position: relative;
            margin-bottom: 1.25rem;
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
        }
        
        .form-control-custom:focus {
            outline: none;
            border-color: #FF6B35;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }
        
        .form-control-custom::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }
        
        .password-toggle {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 0;
            transition: color 0.2s;
        }
        
        .password-toggle:hover {
            color: #FF6B35;
        }
        
        .form-check-custom {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .form-check-input-custom {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 0.35rem;
            border: 2px solid #d1d5db;
            appearance: none;
            cursor: pointer;
            position: relative;
            transition: all 0.2s;
            margin: 0;
        }
        
        .form-check-input-custom:checked {
            background-color: #FF6B35;
            border-color: #FF6B35;
        }
        
        .form-check-input-custom:checked::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: white;
            font-size: 0.75rem;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .form-check-label-custom {
            font-size: 0.9rem;
            font-weight: 500;
            color: #4b5563;
            cursor: pointer;
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
            margin-top: 1rem;
        }
        
        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.4);
            color: white;
        }
        
        .forgot-link {
            font-size: 0.9rem;
            font-weight: 600;
            color: #FF6B35;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .forgot-link:hover {
            color: #e55a2b;
            text-decoration: underline;
        }
        
        .auth-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.95rem;
            color: #6b7280;
        }
        
        .auth-footer a {
            color: #FF6B35;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .auth-footer a:hover {
            text-decoration: underline;
            color: #e55a2b;
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
        
        .alert-success {
            background-color: #f0fdf4;
            color: #15803d;
        }

        .back-home {
            position: absolute;
            top: 2rem;
            right: 2rem;
            color: #6b7280;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-home:hover {
            color: #1a1a2e;
        }

        /* Mobile specific styles */
        @media (max-width: 991px) {
            .split-left {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="split-layout">
    <!-- Left Branding Panel -->
    <div class="split-left">
        <div class="split-left-content">
            <a href="<?= SITE_URL ?>" class="brand-logo">
                <i class="fa-solid fa-dumbbell"></i>
                <?= htmlspecialchars($authBrandName) ?>
            </a>
            
            <h1 class="hero-text">
                Push your limits.<br>
                Achieve your <span>goals.</span>
            </h1>
            
            <ul class="feature-list mt-5">
                <li>
                    <div class="feature-icon"><i class="fa-solid fa-calendar-check"></i></div>
                    Manage your bookings & schedule
                </li>
                <li>
                    <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
                    Track your fitness progress
                </li>
                <li>
                    <div class="feature-icon"><i class="fa-solid fa-bolt"></i></div>
                    Access premium workout plans
                </li>
            </ul>
        </div>
        
        <div class="split-left-content">
            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5);">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($authBrandName) ?>. All rights reserved.
            </div>
        </div>
    </div>
    
    <!-- Right Login Panel -->
    <div class="split-right">
        <a href="<?= SITE_URL ?>" class="back-home">
            <i class="fa-solid fa-arrow-left"></i> Home
        </a>
        
        <div class="auth-form-container">
            <div class="auth-header text-center text-md-start">
                <h2 class="auth-title">Welcome Back</h2>
                <p class="auth-subtitle">Log in to your member dashboard</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert-custom alert-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert-custom alert-success">
                    <i class="fa-solid fa-check-circle"></i>
                    <div>Registration successful! You can now log in.</div>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-floating-custom">
                    <input type="email" class="form-control-custom" name="email" placeholder="Email Address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email">
                </div>
                
                <div class="form-floating-custom">
                    <input type="password" class="form-control-custom" name="password" id="password" placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="password-toggle" id="togglePassword" tabindex="-1">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <label class="form-check-custom">
                        <input type="checkbox" class="form-check-input-custom" name="remember" id="remember">
                        <span class="form-check-label-custom">Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-auth">Log In</button>
            </form>
            
            <div class="auth-footer">
                Don't have an account? <a href="register.php">Sign up</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            if (type === 'text') {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
</script>

</body>
</html>
