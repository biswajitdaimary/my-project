<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/site_settings_helper.php';
require_once __DIR__ . '/../../helpers/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | Trainer Portal' : 'Trainer Portal' ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0ea5e9;
            --primary-hover: #0284c7;
            --dark-bg: #1a1a2e;
            --text-main: #1e293b;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: var(--text-main);
            overflow-x: hidden;
        }
        
        .navbar-trainer {
            background-color: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            height: 76px;
            z-index: 1030;
        }
        .navbar-brand {
            font-weight: 800;
            color: var(--primary-color) !important;
            font-size: 1.25rem;
            letter-spacing: -0.5px;
        }
        
        .btn-primary-custom {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
        }
        .btn-primary-custom:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            color: white;
        }
        
        .page-content-wrapper {
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

<?php if (isset($_SESSION['admin_return_id'])): ?>
<div class="alert text-center rounded-0 m-0 border-0 fw-bold d-flex flex-wrap justify-content-center align-items-center gap-3 shadow-sm" style="background-color: #1a1a2e; color: #FF6B35; z-index: 1050; position: relative;">
    <span><i class="fa-solid fa-user-secret me-2"></i>You are currently viewing this panel as Trainer: <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'Trainer') ?></strong></span>
    <form method="POST" action="<?= SITE_URL ?>/trainer/stop-impersonate.php" class="m-0">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <button type="submit" class="btn btn-sm rounded-pill fw-bold px-3" style="background-color: #FF6B35; color: white;">Return to Admin Panel</button>
    </form>
</div>
<?php endif; ?>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-light navbar-trainer sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="<?= SITE_URL ?>/trainer/dashboard.php">
            <i class="fa-solid fa-dumbbell me-2"></i><?= htmlspecialchars(site_settings_get('gym_name', SITE_NAME)) ?> <span class="text-muted fw-normal ms-1 fs-6">| Trainer Portal</span>
        </a>
        
        <div class="d-flex align-items-center gap-3">
            <?php
            // Trainer notification bell
            try {
                $tnStmt = $pdo->prepare("SELECT COUNT(*) FROM trainer_notifications WHERE trainer_id = ? AND is_read = 0");
                $tnStmt->execute([$_SESSION['user_id']]);
                $unreadTrainerNotifs = (int)$tnStmt->fetchColumn();
            } catch (Exception $e) { $unreadTrainerNotifs = 0; }
            ?>
            <a href="<?= SITE_URL ?>/trainer/notifications.php" class="position-relative text-decoration-none" style="color:#555;" title="Notifications">
                <i class="fa-solid fa-bell fs-5"></i>
                <?php if ($unreadTrainerNotifs > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill" style="background:#FF6B35;font-size:.6rem;">
                    <?= $unreadTrainerNotifs > 9 ? '9+' : $unreadTrainerNotifs ?>
                </span>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler d-md-none border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#trainerSidebarMobile">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Sidebar Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="trainerSidebarMobile">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title fw-bold text-primary"><i class="fa-solid fa-dumbbell"></i> Trainer Portal</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
      <?php require __DIR__ . '/sidebar-trainer.php'; ?>
  </div>
</div>

<div class="container-fluid p-0 flex-grow-1">
    <div class="row g-0 h-100">
        <!-- Desktop Sidebar -->
        <?php require __DIR__ . '/sidebar-trainer.php'; ?>

        <!-- Main Content Area -->
        <div class="col d-flex flex-column" style="min-width: 0;">
            <main class="page-content-wrapper">
