<?php
$currentUser = basename($_SERVER['PHP_SELF'] ?? '');
$userSidebarLinks = [
    ['href' => SITE_URL . '/user/dashboard.php',     'icon' => 'fa-gauge-high',    'label' => 'Dashboard',      'file' => 'dashboard.php'],
    ['href' => SITE_URL . '/user/profile.php',       'icon' => 'fa-user-circle',   'label' => 'My Profile',     'file' => 'profile.php'],
    ['href' => SITE_URL . '/user/membership.php',    'icon' => 'fa-id-card',       'label' => 'Membership',     'file' => 'membership.php'],
    ['href' => SITE_URL . '/user/my-plans.php',      'icon' => 'fa-clipboard-list','label' => 'My Plans',       'file' => 'my-plans.php'],
    ['href' => SITE_URL . '/user/bookings.php',      'icon' => 'fa-calendar-check','label' => 'My Bookings',    'file' => 'bookings.php'],
    ['href' => SITE_URL . '/user/calendar.php',      'icon' => 'fa-calendar-days', 'label' => 'My Calendar',    'file' => 'calendar.php'],
    ['href' => SITE_URL . '/user/book-trainer.php',  'icon' => 'fa-person-running','label' => 'Book Trainer',   'file' => 'book-trainer.php'],
    ['href' => SITE_URL . '/user/payments.php',      'icon' => 'fa-receipt',       'label' => 'Payments',       'file' => 'payments.php'],
    ['href' => SITE_URL . '/user/bmi-history.php',   'icon' => 'fa-heart-pulse',   'label' => 'BMI History',    'file' => 'bmi-history.php'],
    ['href' => SITE_URL . '/user/notifications.php', 'icon' => 'fa-bell',          'label' => 'Notifications',  'file' => 'notifications.php'],
];

$notifCount = 0;
$userPhoto  = '';
$userName   = $_SESSION['full_name'] ?? 'Member';
try {
    $ns = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $ns->execute([$_SESSION['user_id'] ?? 0]);
    $notifCount = (int)$ns->fetchColumn();

    $us = $pdo->prepare("SELECT profile_photo, full_name FROM users WHERE user_id = ?");
    $us->execute([$_SESSION['user_id'] ?? 0]);
    $uRow = $us->fetch();
    if ($uRow) {
        $userPhoto = (string)($uRow['profile_photo'] ?? '');
        $userName  = $uRow['full_name'] ?: $userName;
    }
} catch(Exception $e) {}

$firstLetter = strtoupper(substr($userName, 0, 1));
?>

<style>
/* ── Sidebar Shell ───────────────────────────── */
.user-sidebar {
    width: 260px;
    min-width: 260px;
    background: #ffffff;
    border-right: 1px solid #eef0f5;
    min-height: calc(100vh - 76px);
    position: sticky;
    top: 76px;
    height: calc(100vh - 76px);
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    scrollbar-width: thin;
    scrollbar-color: #f0f0f0 transparent;
}
.user-sidebar::-webkit-scrollbar { width: 4px; }
.user-sidebar::-webkit-scrollbar-thumb { background: #e0e0e0; border-radius: 4px; }

/* ── Profile Section ─────────────────────────── */
.sb-profile {
    padding: 1.5rem 1.25rem 1.25rem;
    border-bottom: 1px solid #f0f2f7;
    background: linear-gradient(135deg, #fff5f1 0%, #fff 100%);
}
.sb-avatar-wrap {
    width: 48px; height: 48px;
    border-radius: 50%;
    flex-shrink: 0;
    position: relative;
}
.sb-avatar-wrap img {
    width: 100%; height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 2.5px solid #FF6B35;
}
.sb-avatar-initials {
    width: 48px; height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #FF6B35, #ff8c5a);
    color: #fff;
    font-weight: 800;
    font-size: 1.15rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(255,107,53,0.35);
}
.sb-name {
    font-weight: 700;
    font-size: 0.92rem;
    color: #1a1a2e;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}
.sb-role {
    font-size: 0.72rem;
    color: #FF6B35;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.sb-online-dot {
    width: 10px; height: 10px;
    background: #22c55e;
    border-radius: 50%;
    border: 2px solid #fff;
    position: absolute;
    bottom: 1px;
    right: 1px;
}

/* ── Nav Links ───────────────────────────────── */
.sb-nav { padding: 0.75rem 0.75rem; flex: 1; }
.sb-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.72rem 1rem;
    border-radius: 12px;
    text-decoration: none;
    color: #6b7280;
    font-weight: 500;
    font-size: 0.88rem;
    margin-bottom: 2px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    white-space: nowrap;
}
.sb-link i {
    width: 20px;
    text-align: center;
    font-size: 0.95rem;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}
.sb-link:hover {
    background: rgba(255,107,53,0.06);
    color: #FF6B35;
}
.sb-link:hover i { transform: translateX(2px); }
.sb-link.active {
    background: linear-gradient(135deg, rgba(255,107,53,0.12), rgba(255,107,53,0.06));
    color: #FF6B35;
    font-weight: 700;
    box-shadow: inset 3px 0 0 #FF6B35;
}
.sb-link.active i { color: #FF6B35; }

/* ── Section Labels ──────────────────────────── */
.sb-section-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #bbb;
    padding: 0.75rem 1rem 0.35rem;
}

/* ── Footer Links ────────────────────────────── */
.sb-footer {
    border-top: 1px solid #f0f2f7;
    padding: 0.75rem;
    margin-top: auto;
}
.sb-footer-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: background 0.18s;
    color: #6b7280;
}
.sb-footer-link:hover { background: #f3f4f6; }
.sb-footer-link.logout { color: #ef4444; }
.sb-footer-link.logout:hover { background: #fef2f2; }

/* ── Badge ───────────────────────────────────── */
.sb-badge {
    margin-left: auto;
    background: #ef4444;
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.18rem 0.5rem;
    border-radius: 100px;
    line-height: 1;
    min-width: 18px;
    text-align: center;
}

@media(max-width: 767px) {
    .user-sidebar { display: none !important; }
}
</style>

<nav class="user-sidebar col-md-auto d-none d-md-flex flex-column">

    <!-- Profile Header -->
    <div class="sb-profile d-flex align-items-center gap-3">
        <div class="sb-avatar-wrap">
            <?php if (!empty($userPhoto)): ?>
                <img src="<?= SITE_URL ?>/<?= htmlspecialchars($userPhoto) ?>" alt="<?= htmlspecialchars($userName) ?>">
            <?php else: ?>
                <div class="sb-avatar-initials"><?= $firstLetter ?></div>
            <?php endif; ?>
            <span class="sb-online-dot" title="Online"></span>
        </div>
        <div style="min-width:0;">
            <div class="sb-name"><?= htmlspecialchars($userName) ?></div>
            <div class="sb-role">Member Portal</div>
        </div>
    </div>

    <!-- Main Navigation -->
    <div class="sb-nav">
        <div class="sb-section-label">Main</div>
        <?php foreach($userSidebarLinks as $link): ?>
        <a href="<?= $link['href'] ?>" class="sb-link <?= $currentUser === $link['file'] ? 'active' : '' ?>">
            <i class="fa-solid <?= $link['icon'] ?>"></i>
            <span><?= $link['label'] ?></span>
            <?php if($link['file'] === 'notifications.php' && $notifCount > 0): ?>
                <span class="sb-badge"><?= $notifCount ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="sb-footer">
        <?php if(isset($_SESSION['admin_return_id'])): ?>
            <form method="POST" action="<?= SITE_URL ?>/user/stop-impersonate.php" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <button type="submit" class="sb-footer-link" style="width:100%; border:1px solid #fde68a; background:#fffbeb; color:#d97706;">
                    <i class="fa-solid fa-user-tie"></i> <span>Return to Admin</span>
                </button>
            </form>
        <?php else: ?>
            <a href="<?= SITE_URL ?>/index.php" class="sb-footer-link">
                <i class="fa-solid fa-house"></i> <span>Back to Site</span>
            </a>
            <a href="<?= SITE_URL ?>/auth/logout.php" class="sb-footer-link logout">
                <i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span>
            </a>
        <?php endif; ?>
    </div>
</nav>
