<?php
// ── Security: ensure admin_check runs before ANY output ──────────────────
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
require_once __DIR__ . '/../../helpers/site_settings_helper.php';
require_once __DIR__ . '/admin_check.php'; // enforces require_admin()
// ─────────────────────────────────────────────────────────────────────────

$adminCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
$adminBrandName = site_settings_get('gym_name', SITE_NAME);

// Unread messages count
$unreadMsgCount = 0;
// Unread system alerts count
$unreadAlertCount = 0;
try {
    $uq = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0");
    $uq->execute();
    $unreadMsgCount = (int)$uq->fetchColumn();

    $aq = $pdo->prepare("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0");
    $aq->execute();
    $unreadAlertCount = (int)$aq->fetchColumn();
} catch(Exception $e) {}

$adminLinks = [
    ['href'=>'dashboard.php',         'icon'=>'fa-gauge',              'label'=>'Dashboard'],
    ['href'=>'members.php',           'icon'=>'fa-users',              'label'=>'Members'],
    ['href'=>'plans.php',             'icon'=>'fa-tags',               'label'=>'Plans'],
    ['href'=>'trainers.php',          'icon'=>'fa-person-running',     'label'=>'Trainers'],
    ['href'=>'bookings.php',          'icon'=>'fa-calendar-check',     'label'=>'Bookings'],
    ['href'=>'calendar.php',          'icon'=>'fa-calendar-days',      'label'=>'Calendar'],
    ['href'=>'payments.php',          'icon'=>'fa-file-invoice-dollar','label'=>'Payments'],
    ['href'=>'gallery.php',           'icon'=>'fa-images',             'label'=>'Gallery'],
    ['href'=>'blog.php',              'icon'=>'fa-pen-to-square',      'label'=>'Blog'],
    ['href'=>'reports.php',           'icon'=>'fa-chart-line',         'label'=>'Reports'],
    ['href'=>'contacts.php',          'icon'=>'fa-envelope',           'label'=>'Messages', 'badge'=>$unreadMsgCount],
    ['href'=>'about.php',             'icon'=>'fa-file-pen',           'label'=>'About Us'],
    ['href'=>'notifications.php',     'icon'=>'fa-bullhorn',           'label'=>'Alerts'],
    ['href'=>'settings.php',          'icon'=>'fa-gear',               'label'=>'Settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars($pageTitle ?? 'Panel') ?> | <?= htmlspecialchars($adminBrandName) ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
    body { background-color:#f4f6fb; }

    /* Admin shell layout */
    .admin-shell { display:flex; min-height:100vh; }

    /* Sidebar */
    .admin-sidebar {
        width: 240px; flex-shrink:0;
        background: #1A1A2E;
        display:flex; flex-direction:column;
        position:fixed; top:0; left:0; bottom:0;
        z-index:1040; overflow-y:auto;
        transition: transform 0.3s ease;
    }
    .admin-sidebar-brand {
        padding: 1.5rem 1.25rem 1rem;
        color: #FF6B35;
        font-size:1.15rem; font-weight:800; letter-spacing:0.02em;
        border-bottom: 1px solid rgba(255,255,255,0.07);
        display:flex; align-items:center; gap:0.6rem;
    }
    .admin-nav-link {
        display:flex; align-items:center; gap:0.7rem;
        padding: 0.65rem 1.25rem;
        color: rgba(255,255,255,0.65);
        text-decoration:none; font-size:0.88rem; font-weight:500;
        border-left: 3px solid transparent;
        transition: all 0.18s ease;
        position:relative;
    }
    .admin-nav-link i { width:18px; text-align:center; flex-shrink:0; }
    .admin-nav-link:hover { color:#fff; background:rgba(255,255,255,0.05); }
    .admin-nav-link.active {
        color:#FF6B35; background:rgba(255,107,53,0.12);
        border-left-color:#FF6B35; font-weight:600;
    }
    .admin-nav-section { padding: 0.75rem 1.25rem 0.35rem; font-size:0.7rem; font-weight:700; letter-spacing:0.1em; color:rgba(255,255,255,0.3); text-transform:uppercase; }

    /* Main content */
    .admin-main { margin-left:240px; flex:1; display:flex; flex-direction:column; min-height:100vh; }

    /* Topbar */
    .admin-topbar {
        background:#fff; border-bottom:1px solid rgba(0,0,0,0.07);
        padding: 0.75rem 2rem;
        display:flex; justify-content:space-between; align-items:center;
        position:sticky; top:0; z-index:1030;
        box-shadow:0 2px 10px rgba(0,0,0,0.04);
    }
    .admin-topbar-title { font-weight:700; font-size:1rem; color:#1a1a2e; }
    .admin-content { flex:1; padding: 2rem; }

    /* Responsiveness */
    @media(max-width:991.98px) {
        .admin-sidebar { transform:translateX(-100%); }
        .admin-sidebar.open { transform:translateX(0); }
        .admin-main { margin-left:0; }
    }

    /* Table tweaks */
    .table-custom th { border-top:none; font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#6c757d; }
    .rounded-10 { border-radius:16px !important; }
    </style>
</head>
<body>
<div class="admin-shell">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-brand">
            <i class="fa-solid fa-dumbbell"></i> <?= htmlspecialchars($adminBrandName) ?>
        </div>
        <div class="admin-nav-section">Main Menu</div>
        <?php foreach($adminLinks as $link):
            $activeMatch = $link['match'] ?? [$link['href']];
            $active = in_array($adminCurrentPage, $activeMatch, true) ? 'active' : '';
        ?>
        <a href="<?= SITE_URL ?>/admin/<?= $link['href'] ?>" class="admin-nav-link <?= $active ?>">
            <i class="fa-solid <?= $link['icon'] ?>"></i>
            <span><?= $link['label'] ?></span>
            <?php if(!empty($link['badge']) && $link['badge'] > 0): ?>
                <span class="badge bg-danger rounded-pill ms-auto"><?= $link['badge'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <div class="mt-auto p-3 border-top" style="border-color:rgba(255,255,255,0.07) !important;">
            <a href="<?= SITE_URL ?>/index.php" target="_blank" class="admin-nav-link mb-1"><i class="fa-solid fa-globe"></i> <span>View Site</span></a>
            <a href="<?= SITE_URL ?>/auth/logout.php" class="admin-nav-link" style="color:rgba(255,100,100,0.75);"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="admin-main">
        <!-- Topbar -->
        <div class="admin-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
                <span class="admin-topbar-title"><?= htmlspecialchars($pageTitle ?? 'Admin Panel') ?></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if($unreadMsgCount > 0): ?>
                <a href="<?= SITE_URL ?>/admin/contacts.php" class="btn btn-sm btn-danger rounded-pill">
                    <i class="fa-solid fa-envelope me-1"></i><?= $unreadMsgCount ?> new
                </a>
                <?php endif; ?>

                <a href="<?= SITE_URL ?>/admin/system_alerts.php" class="btn btn-sm btn-outline-secondary position-relative rounded-circle" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center; border:none; background:#f4f6fb;">
                    <i class="fa-solid fa-bell text-secondary"></i>
                    <?php if($unreadAlertCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;">
                        <?= $unreadAlertCount ?>
                    </span>
                    <?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-user-tie me-1"></i><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                        <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/settings.php"><i class="fa-solid fa-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= SITE_URL ?>/auth/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="admin-content">
