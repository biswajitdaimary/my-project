<?php
if (!function_exists('site_settings_get')) {
    require_once __DIR__ . '/../helpers/site_settings_helper.php';
}

$footerGymName = site_settings_get('gym_name', SITE_NAME);
$footerPhone = site_settings_get('phone', '+1 234 567 890');
$footerEmail = site_settings_get('email', 'info@powerhousegym.com');
$footerAddress = site_settings_get('address', "123 Fitness Street\nGym City");
$footerSocials = [];

foreach (site_settings_social_fields() as $key => $meta) {
    $url = trim(site_settings_get($key));
    if ($url !== '') {
        $footerSocials[] = $meta + ['url' => $url];
    }
}
?>
<footer class="footer mt-auto py-5" style="background-color: var(--dark-bg); color: #fff;">
  <div class="container">
    <div class="row gy-4">
      <div class="col-lg-4 col-md-6">
        <h4 class="fw-bold mb-3" style="color: var(--primary-color);"><i class="fa-solid fa-dumbbell"></i> <?= htmlspecialchars($footerGymName) ?></h4>
        <p class="text-white-50">Empowering your fitness journey with state-of-the-art equipment, expert trainers, and a supportive community. Your goals are our mission.</p>
        <div class="social-links mt-3">
            <?php foreach ($footerSocials as $social): ?>
                <a href="<?= htmlspecialchars($social['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-sm rounded-circle me-2">
                    <i class="fa-brands <?= $social['icon'] ?>"></i>
                </a>
            <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-2 col-md-6">
        <h5 class="mb-3">Quick Links</h5>
        <ul class="list-unstyled">
          <li class="mb-2"><a href="<?= SITE_URL ?>/about.php" class="text-white-50 text-decoration-none">About Us</a></li>
          <li class="mb-2"><a href="<?= SITE_URL ?>/plans.php" class="text-white-50 text-decoration-none">Plans & Pricing</a></li>
          <li class="mb-2"><a href="<?= SITE_URL ?>/trainers.php" class="text-white-50 text-decoration-none">Our Trainers</a></li>
          <li class="mb-2"><a href="<?= SITE_URL ?>/schedule.php" class="text-white-50 text-decoration-none">Class Schedule</a></li>
        </ul>
      </div>
      <div class="col-lg-2 col-md-6">
        <h5 class="mb-3">Support</h5>
        <ul class="list-unstyled">
          <li class="mb-2"><a href="<?= SITE_URL ?>/contact.php" class="text-white-50 text-decoration-none">Contact Us</a></li>
          <li class="mb-2"><a href="<?= SITE_URL ?>/privacy.php" class="text-white-50 text-decoration-none">Privacy Policy</a></li>
          <li class="mb-2"><a href="<?= SITE_URL ?>/terms.php" class="text-white-50 text-decoration-none">Terms of Service</a></li>
        </ul>
      </div>
      <div class="col-lg-4 col-md-6">
        <h5 class="mb-3">Contact Info</h5>
        <ul class="list-unstyled text-white-50">
          <li class="mb-2"><i class="fa-solid fa-location-dot me-2 text-primary-custom"></i> <?= nl2br(htmlspecialchars($footerAddress)) ?></li>
          <li class="mb-2"><i class="fa-solid fa-phone me-2 text-primary-custom"></i> <a href="<?= htmlspecialchars(site_settings_phone_href($footerPhone)) ?>" class="text-white-50 text-decoration-none"><?= htmlspecialchars($footerPhone) ?></a></li>
          <li class="mb-2"><i class="fa-solid fa-envelope me-2 text-primary-custom"></i> <a href="mailto:<?= htmlspecialchars($footerEmail) ?>" class="text-white-50 text-decoration-none"><?= htmlspecialchars($footerEmail) ?></a></li>
        </ul>
      </div>
    </div>
    <hr class="border-secondary mt-4 mb-3">
    <div class="text-center text-white-50 small">
      &copy; <?= date('Y') ?> <?= htmlspecialchars($footerGymName) ?>. All Rights Reserved. Designed by Antigravity.
    </div>
  </div>
</footer>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AOS Animation -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<!-- Custom Main JS -->
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>

<?php require_once __DIR__ . '/../chatbot/widget.php'; ?>



</body>
</html>
