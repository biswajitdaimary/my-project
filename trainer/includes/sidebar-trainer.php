<?php
$currentUser = basename($_SERVER['PHP_SELF'] ?? '');
$trainerSidebarLinks = [
    ['href' => SITE_URL . '/trainer/dashboard.php',  'icon' => 'fa-gauge-high',    'label' => 'Dashboard',      'file' => 'dashboard.php'],
    ['href' => SITE_URL . '/trainer/clients.php',    'icon' => 'fa-users',         'label' => 'My Clients',     'file' => 'clients.php'],
    ['href' => SITE_URL . '/trainer/bookings.php',   'icon' => 'fa-calendar-check','label' => 'My Sessions',    'file' => 'bookings.php'],
    ['href' => SITE_URL . '/trainer/calendar.php',   'icon' => 'fa-calendar-days', 'label' => 'My Calendar',    'file' => 'calendar.php'],
    ['href' => SITE_URL . '/trainer/availability.php','icon' => 'fa-clock',        'label' => 'Availability',   'file' => 'availability.php'],
    ['href' => SITE_URL . '/trainer/assign_plans.php','icon' => 'fa-dumbbell',     'label' => 'Workout Plans',  'file' => 'assign_plans.php'],
    ['href' => SITE_URL . '/trainer/profile.php',    'icon' => 'fa-user-tie',      'label' => 'My Profile',     'file' => 'profile.php'],
];

$userPhoto  = '';
$userName   = $_SESSION['full_name'] ?? 'Trainer';
try {
    $us = $pdo->prepare("SELECT photo, full_name FROM trainers WHERE trainer_id = ?");
    $us->execute([$_SESSION['user_id'] ?? 0]);
    $uRow = $us->fetch();
    if ($uRow) {
        $userPhoto = (string)($uRow['photo'] ?? '');
        $userName  = $uRow['full_name'] ?: $userName;
    }
} catch(Exception $e) {}

$firstLetter = strtoupper(substr($userName, 0, 1));
?>

<style>
/* ── Sidebar Shell ───────────────────────────── */
.trainer-sidebar {
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
}

/* ── Profile Section ─────────────────────────── */
.sb-profile {
    padding: 1.5rem 1.25rem 1.25rem;
    border-bottom: 1px solid #f0f2f7;
    background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);
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
    border: 2.5px solid #0ea5e9;
}
.sb-avatar-initials {
    width: 48px; height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0ea5e9, #38bdf8);
    color: #fff;
    font-weight: 800;
    font-size: 1.15rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(14,165,233,0.35);
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
    color: #0ea5e9;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
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
}
.sb-link i {
    width: 20px;
    text-align: center;
    font-size: 0.95rem;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}
.sb-link:hover {
    background: rgba(14,165,233,0.06);
    color: #0ea5e9;
}
.sb-link:hover i { transform: translateX(2px); }
.sb-link.active {
    background: linear-gradient(135deg, rgba(14,165,233,0.12), rgba(14,165,233,0.06));
    color: #0ea5e9;
    font-weight: 700;
    box-shadow: inset 3px 0 0 #0ea5e9;
}
.sb-link.active i { color: #0ea5e9; }

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
    color: #6b7280;
}
.sb-footer-link:hover { background: #f3f4f6; }
.sb-footer-link.logout { color: #ef4444; }
.sb-footer-link.logout:hover { background: #fef2f2; }

@media(max-width: 767px) {
    .trainer-sidebar { display: none !important; }
}
</style>

<nav class="trainer-sidebar col-md-auto d-none d-md-flex flex-column">
    <div class="sb-profile d-flex align-items-center gap-3">
        <div class="sb-avatar-wrap">
            <?php if (!empty($userPhoto)): ?>
                <img src="<?= SITE_URL ?>/<?= htmlspecialchars($userPhoto) ?>" alt="<?= htmlspecialchars($userName) ?>">
            <?php else: ?>
                <div class="sb-avatar-initials"><?= $firstLetter ?></div>
            <?php endif; ?>
        </div>
        <div style="min-width:0;">
            <div class="sb-name"><?= htmlspecialchars($userName) ?></div>
            <div class="sb-role">Trainer Portal</div>
        </div>
    </div>

    <div class="sb-nav">
        <div class="sb-section-label">Main</div>
        <?php foreach($trainerSidebarLinks as $link): ?>
        <a href="<?= $link['href'] ?>" class="sb-link <?= $currentUser === $link['file'] ? 'active' : '' ?>">
            <i class="fa-solid <?= $link['icon'] ?>"></i>
            <span><?= $link['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="sb-footer">
        <a href="<?= SITE_URL ?>/index.php" class="sb-footer-link">
            <i class="fa-solid fa-house"></i> <span>Back to Site</span>
        </a>
        <a href="<?= SITE_URL ?>/auth/logout.php" class="sb-footer-link logout">
            <i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span>
        </a>
    </div>
</nav>
