<?php
$pageTitle = 'Register';
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/notification_helper.php';

// Prevent logged-in users from seeing registration
guest_only();

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid security token.";
    } else {
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {
            $error = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email is already registered.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO users (full_name, email, phone, password_hash, is_verified, verification_token)
                        VALUES (?, ?, ?, ?, 1, NULL)
                    ");
                    $insertStmt->execute([$fullName, $email, $phone, $hashedPassword]);
                    $newUserId = $pdo->lastInsertId();

                    // Assign permanent unique Member ID (CLT-XXXX)
                    require_once '../helpers/id_helper.php';
                    assign_custom_id_if_missing($pdo, 'users', 'user_id', (int)$newUserId, 'CLT');

                    // Notify Admin
                    notify_admin(
                        $pdo,
                        "New Member Registration",
                        "{$fullName} has just registered an account.",
                        "success",
                        "members.php" // Or link to the member's detail if available
                    );

                    header("Location: login.php?registered=success");
                    exit;
                } catch(PDOException $e) {
                    $error = "Database error. Please try again later.";
                }
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
            padding: 3rem 2rem;
            background: white;
            position: relative;
        }
        
        .auth-form-container {
            width: 100%;
            max-width: 480px;
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
            box-sizing: border-box;
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

        .row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .col {
            flex: 1;
        }
        .row .form-floating-custom {
            margin-bottom: 0;
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
            cursor: pointer;
        }
        
        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.4);
            color: white;
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

        /* Password Strength Meter */
        .password-strength {
            margin-top: -0.5rem;
            margin-bottom: 1.25rem;
            padding: 0 0.5rem;
        }
        .strength-bars {
            display: flex;
            gap: 5px;
            margin-bottom: 0.25rem;
        }
        .strength-bar {
            height: 4px;
            flex: 1;
            background-color: #e5e7eb;
            border-radius: 2px;
            transition: background-color 0.3s;
        }
        .strength-text {
            font-size: 0.75rem;
            color: #9ca3af;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
        }

        /* Password Match Indicator */
        .match-indicator {
            position: absolute;
            right: 3rem; /* Next to toggle */
            top: 50%;
            transform: translateY(-50%);
            color: #22c55e;
            display: none;
        }

        /* Mobile specific styles */
        @media (max-width: 991px) {
            .split-left {
                display: none;
            }
            .row {
                flex-direction: column;
                gap: 1.25rem;
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
                Start your fitness<br>
                journey <span>today.</span>
            </h1>
            
            <ul class="feature-list mt-5">
                <li>
                    <div class="feature-icon"><i class="fa-solid fa-user-plus"></i></div>
                    Create your personalized profile
                </li>
                <li>
                    <div class="feature-icon"><i class="fa-solid fa-credit-card"></i></div>
                    Easy membership subscriptions
                </li>
                <li>
                    <div class="feature-icon"><i class="fa-solid fa-dumbbell"></i></div>
                    Connect with expert trainers
                </li>
            </ul>
        </div>
        
        <div class="split-left-content">
            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5);">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($authBrandName) ?>. All rights reserved.
            </div>
        </div>
    </div>
    
    <!-- Right Register Panel -->
    <div class="split-right">
        <a href="<?= SITE_URL ?>" class="back-home">
            <i class="fa-solid fa-arrow-left"></i> Home
        </a>
        
        <div class="auth-form-container">
            <div class="auth-header text-center text-md-start">
                <h2 class="auth-title">Create an Account</h2>
                <p class="auth-subtitle">Join us and start achieving your goals</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert-custom alert-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-floating-custom">
                    <input type="text" class="form-control-custom" name="full_name" placeholder="Full Name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" autocomplete="name">
                </div>
                
                <div class="row">
                    <div class="col">
                        <div class="form-floating-custom">
                            <input type="email" class="form-control-custom" name="email" placeholder="Email Address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email">
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-floating-custom">
                            <input type="tel" class="form-control-custom" name="phone" placeholder="Phone (Optional)" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" autocomplete="tel">
                        </div>
                    </div>
                </div>
                
                <div class="form-floating-custom">
                    <input type="password" class="form-control-custom" name="password" id="password" placeholder="Create Password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="password-toggle" onclick="toggleVisibility('password', this)" tabindex="-1">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
                
                <div class="password-strength">
                    <div class="strength-bars">
                        <div class="strength-bar" id="bar-1"></div>
                        <div class="strength-bar" id="bar-2"></div>
                        <div class="strength-bar" id="bar-3"></div>
                        <div class="strength-bar" id="bar-4"></div>
                    </div>
                    <div class="strength-text">
                        <span id="strength-text-label">Enter password</span>
                        <span>8+ characters</span>
                    </div>
                </div>
                
                <div class="form-floating-custom">
                    <input type="password" class="form-control-custom" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required minlength="8" autocomplete="new-password">
                    <i class="fa-solid fa-circle-check match-indicator" id="matchIndicator"></i>
                    <button type="button" class="password-toggle" onclick="toggleVisibility('confirm_password', this)" tabindex="-1">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
                
                <button type="submit" class="btn-auth" id="submitBtn">Sign Up</button>
            </form>
            
            <div class="auth-footer">
                Already have an account? <a href="login.php">Log in</a>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        
        const icon = btn.querySelector('i');
        if (type === 'text') {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        const matchIndicator = document.getElementById('matchIndicator');
        
        // Strength Meter logic
        password.addEventListener('input', function() {
            const val = password.value;
            let strength = 0;
            
            if (val.length >= 8) strength++;
            if (val.match(/[A-Z]/)) strength++;
            if (val.match(/[0-9]/)) strength++;
            if (val.match(/[^a-zA-Z0-9]/)) strength++;
            
            const bars = [
                document.getElementById('bar-1'),
                document.getElementById('bar-2'),
                document.getElementById('bar-3'),
                document.getElementById('bar-4')
            ];
            
            const textLabel = document.getElementById('strength-text-label');
            
            // Reset all
            bars.forEach(b => b.style.backgroundColor = '#e5e7eb');
            
            if (val.length === 0) {
                textLabel.textContent = 'Enter password';
                textLabel.style.color = '#9ca3af';
            } else if (strength === 0 || strength === 1) {
                bars[0].style.backgroundColor = '#ef4444'; // Red
                textLabel.textContent = 'Weak';
                textLabel.style.color = '#ef4444';
            } else if (strength === 2) {
                bars[0].style.backgroundColor = '#f59e0b'; // Yellow
                bars[1].style.backgroundColor = '#f59e0b';
                textLabel.textContent = 'Fair';
                textLabel.style.color = '#f59e0b';
            } else if (strength === 3) {
                bars[0].style.backgroundColor = '#3b82f6'; // Blue
                bars[1].style.backgroundColor = '#3b82f6';
                bars[2].style.backgroundColor = '#3b82f6';
                textLabel.textContent = 'Good';
                textLabel.style.color = '#3b82f6';
            } else if (strength >= 4) {
                bars[0].style.backgroundColor = '#22c55e'; // Green
                bars[1].style.backgroundColor = '#22c55e';
                bars[2].style.backgroundColor = '#22c55e';
                bars[3].style.backgroundColor = '#22c55e';
                textLabel.textContent = 'Strong';
                textLabel.style.color = '#22c55e';
            }
            
            checkMatch();
        });
        
        // Match logic
        confirm.addEventListener('input', checkMatch);
        
        function checkMatch() {
            if (confirm.value.length > 0 && password.value === confirm.value) {
                matchIndicator.style.display = 'block';
                confirm.style.borderColor = '#22c55e';
            } else {
                matchIndicator.style.display = 'none';
                if (confirm.value.length > 0) {
                    confirm.style.borderColor = '#eef0f7';
                }
            }
        }
    });
</script>

</body>
</html>
