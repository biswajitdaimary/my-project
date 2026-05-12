<?php
if (!function_exists('site_settings_get')) {
  require_once __DIR__ . '/../helpers/site_settings_helper.php';
}

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$navActiveMap = [
  'index.php' => ['index.php'],
  'about.php' => ['about.php'],
  'plans.php' => ['plans.php', 'checkout.php'],
  'trainers.php' => ['trainers.php'],
  'gallery.php' => ['gallery.php'],
  'schedule.php' => ['schedule.php'],
  'bmi-calculator.php' => ['bmi-calculator.php'],
  'blog.php' => ['blog.php', 'blog-detail.php'],
  'contact.php' => ['contact.php']
];

$isNavActive = static function (string $key) use ($navActiveMap, $currentScript): bool {
  return in_array($currentScript, $navActiveMap[$key] ?? [], true);
};
?>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top site-navbar" style="background-color: var(--dark-bg);">
  <div class="container site-navbar-container">
    <a class="navbar-brand fw-bold" style="color: var(--primary-color);" href="<?= SITE_URL ?>/index.php">
      <i class="fa-solid fa-dumbbell"></i> <?= htmlspecialchars(site_settings_get('gym_name', SITE_NAME)) ?>
    </a>

    <button class="navbar-toggler ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= $isNavActive('index.php') ? 'active nav-link-current' : '' ?>" href="<?= SITE_URL ?>/index.php" <?= $isNavActive('index.php') ? 'aria-current="page"' : '' ?>>Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $isNavActive('about.php') ? 'active nav-link-current' : '' ?>" href="<?= SITE_URL ?>/about.php" <?= $isNavActive('about.php') ? 'aria-current="page"' : '' ?>>About</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $isNavActive('plans.php') ? 'active nav-link-current' : '' ?>" href="<?= SITE_URL ?>/plans.php" <?= $isNavActive('plans.php') ? 'aria-current="page"' : '' ?>>Plans</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $isNavActive('trainers.php') ? 'active nav-link-current' : '' ?>" href="<?= SITE_URL ?>/trainers.php" <?= $isNavActive('trainers.php') ? 'aria-current="page"' : '' ?>>Trainers</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $isNavActive('gallery.php') ? 'active nav-link-current' : '' ?>" href="<?= SITE_URL ?>/gallery.php" <?= $isNavActive('gallery.php') ? 'aria-current="page"' : '' ?>>Gallery</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $isNavActive('schedule.php') ? 'active nav-link-current' : '' ?>" href="<?= SITE_URL ?>/schedule.php" <?= $isNavActive('schedule.php') ? 'aria-current="page"' : '' ?>>Schedule</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $isNavActive('bmi-calculator.php') ? 'active nav-link-current' : '' ?>" href="<?= SITE_URL ?>/bmi-calculator.php" <?= $isNavActive('bmi-calculator.php') ? 'aria-current="page"' : '' ?>>BMI Calculator</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $isNavActive('blog.php') ? 'active nav-link-current' : '' ?>" href="<?= SITE_URL ?>/blog.php" <?= $isNavActive('blog.php') ? 'aria-current="page"' : '' ?>>Blog</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $isNavActive('contact.php') ? 'active nav-link-current' : '' ?>" href="<?= SITE_URL ?>/contact.php" <?= $isNavActive('contact.php') ? 'aria-current="page"' : '' ?>>Contact</a>
        </li>
      </ul>
      <div class="d-flex ms-xxl-3 gap-2 align-items-center navbar-actions">
        <?php if(!empty($_SESSION['user_id'])): ?>
          <div class="dropdown">
            <a class="btn btn-outline-light dropdown-toggle" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-user-circle"></i> Account
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/dashboard.php">Admin Panel</a></li>
              <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] === 'trainer'): ?>
                <li><a class="dropdown-item" href="<?= SITE_URL ?>/trainer/dashboard.php">Trainer Panel</a></li>
              <?php else: ?>
                <li><a class="dropdown-item" href="<?= SITE_URL ?>/user/dashboard.php">Dashboard</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= SITE_URL ?>/auth/logout.php">Logout</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-outline-light">Login</a>
          <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn-primary-custom" style="background-color: var(--primary-color); border: none;">Join Now</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
