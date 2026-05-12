<?php
$pageTitle = 'Home';
require_once 'config/config.php';

$featuredPlans = [];
$featuredTrainers = [];
$featuredPosts = [];
$featuredSlots = [];
$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin = $isLoggedIn && (($_SESSION['role'] ?? '') === 'admin');
$dashboardHref = $isAdmin ? 'admin/dashboard.php' : 'user/dashboard.php';
$metrics = [
    'members' => '5000+',
    'trainers' => '20+',
    'plans' => '3+',
    'slots' => 'Daily'
];

try {
    $planStmt = $pdo->query("SELECT * FROM membership_plans WHERE is_active = 1 ORDER BY is_popular DESC, price ASC LIMIT 3");
    $featuredPlans = $planStmt->fetchAll();

    $trainerStmt = $pdo->query("SELECT * FROM trainers WHERE is_active = 1 ORDER BY rating DESC, experience_years DESC LIMIT 4");
    $featuredTrainers = $trainerStmt->fetchAll();

    $postStmt = $pdo->query("
        SELECT p.title, p.slug, p.excerpt, p.created_at, p.featured_image, c.name AS category_name
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.category_id
        WHERE p.status = 'published'
        ORDER BY p.created_at DESC
        LIMIT 3
    ");
    $featuredPosts = $postStmt->fetchAll();

    $slotStmt = $pdo->query("
        SELECT t.full_name, t.specialization, DATE_FORMAT(a.date, '%a') as day_of_week, a.start_time, a.end_time
        FROM availability_slots a
        JOIN trainers t ON a.trainer_id = t.trainer_id
        WHERE t.is_active = 1 AND a.status = 'available' AND a.date >= CURRENT_DATE
        ORDER BY a.date ASC, a.start_time ASC
        LIMIT 6
    ");
    $featuredSlots = $slotStmt->fetchAll();

    $countStmt = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'user') AS member_count,
            (SELECT COUNT(*) FROM trainers WHERE is_active = 1) AS trainer_count,
            (SELECT COUNT(*) FROM membership_plans WHERE is_active = 1) AS plan_count,
            (SELECT COUNT(*) FROM availability_slots WHERE status = 'available' AND date >= CURRENT_DATE) AS open_slots
    ");
    $counts = $countStmt->fetch();

    if ($counts) {
        // If the DB has very few members (e.g. testing phase), you can set a baseline like + 100 or just use raw count.
        // For now, using raw count as requested to fix fake numbers.
        $metrics['members'] = (int)$counts['member_count'] . '+';
        $metrics['trainers'] = (int)$counts['trainer_count'] . '+';
        $metrics['plans'] = (int)$counts['plan_count'] . '+';
        $metrics['slots'] = (int)$counts['open_slots'] . '+';
    }
} catch (PDOException $e) {
    // Keep homepage usable even if database content is unavailable.
}

$dashboardLinks = [
    [
        'title' => 'Membership Plans',
        'text' => 'Compare plans, benefits, and pricing before you join.',
        'icon' => 'fa-id-card',
        'href' => 'plans.php',
        'label' => 'Explore Plans'
    ],
    [
        'title' => 'Trainer Booking',
        'text' => 'Browse trainers, view specialties, and book your next session.',
        'icon' => 'fa-user-tie',
        'href' => 'trainers.php',
        'label' => 'Meet Trainers'
    ],
    [
        'title' => 'Schedule Board',
        'text' => 'Check trainer availability before you reserve a time slot.',
        'icon' => 'fa-calendar-check',
        'href' => 'schedule.php',
        'label' => 'View Schedule'
    ],
    [
        'title' => 'BMI Calculator',
        'text' => 'Measure your fitness status and save progress to your dashboard.',
        'icon' => 'fa-scale-balanced',
        'href' => 'bmi-calculator.php',
        'label' => 'Open Calculator'
    ],
    [
        'title' => 'Fitness Blog',
        'text' => 'Read workout advice, nutrition tips, and gym updates.',
        'icon' => 'fa-newspaper',
        'href' => 'blog.php',
        'label' => 'Read Articles'
    ],
    [
        'title' => 'Contact & Visits',
        'text' => 'Get in touch, ask questions, or plan your first visit.',
        'icon' => 'fa-envelope-open-text',
        'href' => 'contact.php',
        'label' => 'Contact Us'
    ]
];

$memberActions = $isLoggedIn
    ? [
        ['title' => $isAdmin ? 'Open Admin Panel' : 'Open Dashboard', 'href' => $dashboardHref, 'icon' => 'fa-gauge-high'],
        ['title' => 'Track BMI', 'href' => 'bmi-calculator.php', 'icon' => 'fa-heart-pulse'],
        ['title' => $isAdmin ? 'Manage Members' : 'Manage Bookings', 'href' => $isAdmin ? 'admin/members.php' : 'user/bookings.php', 'icon' => $isAdmin ? 'fa-users-gear' : 'fa-calendar-days'],
        ['title' => $isAdmin ? 'View Payments' : 'Update Profile', 'href' => $isAdmin ? 'admin/payments.php' : 'user/profile.php', 'icon' => $isAdmin ? 'fa-wallet' : 'fa-user-gear']
    ]
    : [
        ['title' => 'Create Account', 'href' => 'auth/register.php', 'icon' => 'fa-user-plus'],
        ['title' => 'View Plans', 'href' => 'plans.php', 'icon' => 'fa-layer-group'],
        ['title' => 'Try BMI Tool', 'href' => 'bmi-calculator.php', 'icon' => 'fa-calculator'],
        ['title' => 'Login', 'href' => 'auth/login.php', 'icon' => 'fa-right-to-bracket']
    ];

require_once 'includes/header.php';
require_once 'includes/nav.php';

$siteGymName = site_settings_get('gym_name', SITE_NAME);
$sitePhone = site_settings_get('phone', '+1 234 567 890');
$siteEmail = site_settings_get('email', 'info@powerhousegym.com');
$siteAddress = site_settings_get('address', "123 Fitness Street\nGym City");
$sitePhoneHref = site_settings_phone_href($sitePhone);
$siteWhatsappHref = site_settings_whatsapp_href($sitePhone);
?>

<section class="hero-section dashboard-hero d-flex align-items-center">
    <div class="container position-relative" data-aos="fade-up">
        <div class="row align-items-center g-5">
            <div class="col-xl-7">
                <span class="dashboard-kicker">Your fitness command center</span>
                <h1 class="hero-title mt-3">Everything at <?= htmlspecialchars($siteGymName) ?> starts from one home page.</h1>
                <p class="lead mb-4">Explore memberships, trainers, schedules, BMI tracking, articles, and support from a single landing page built like a dashboard.</p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="#feature-hub" class="btn btn-primary-custom btn-lg px-4">Explore Features</a>
                    <a href="<?= $isLoggedIn ? $dashboardHref : 'auth/register.php' ?>" class="btn btn-outline-light btn-lg px-4">
                        <?= $isLoggedIn ? ($isAdmin ? 'Open Admin Panel' : 'Open My Dashboard') : 'Join Now' ?>
                    </a>
                </div>
                <div class="hero-anchor-nav">
                    <a href="#member-hub">Member Hub</a>
                    <a href="#membership">Plans</a>
                    <a href="#training">Trainers</a>
                    <a href="#schedule-preview">Schedule</a>
                    <a href="#insights">Resources</a>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="hero-dashboard-shell">
                    <div class="hero-dashboard-top">
                        <span>Home Dashboard</span>
                        <span class="hero-status-pill">Live Access</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="hero-mini-card">
                                <span class="hero-mini-label">Active Trainers</span>
                                <strong><?= htmlspecialchars($metrics['trainers']) ?></strong>
                                <small>Available for guided sessions</small>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="hero-mini-card">
                                <span class="hero-mini-label">Open Slots</span>
                                <strong><?= htmlspecialchars($metrics['slots']) ?></strong>
                                <small>Trainer booking windows</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="hero-action-card">
                                <div>
                                    <span class="hero-mini-label">Quick Start</span>
                <h3><?= $isLoggedIn ? 'Continue your routine' : 'Start with a plan or BMI check' ?></h3>
                                </div>
                                <div class="hero-action-links">
                                    <a href="plans.php">Plans</a>
                                    <a href="schedule.php">Schedule</a>
                                    <a href="bmi-calculator.php">BMI Tool</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="hero-metrics-grid">
                                <div>
                                    <strong><?= htmlspecialchars($metrics['members']) ?></strong>
                                    <span>Members</span>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($metrics['plans']) ?></strong>
                                    <span>Plans</span>
                                </div>
                                <div>
                                    <strong>24/7</strong>
                                    <span>Access View</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!--  Stats Strip  -->
<div class="stats-strip">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <span class="stat-number" data-target="<?= (int)($counts['member_count'] ?? 0) ?>">0</span>
                    <span class="stat-label">Happy Members</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <span class="stat-number" data-target="<?= (int)($counts['trainer_count'] ?? 20) ?>">0</span>
                    <span class="stat-label">Expert Trainers</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <span class="stat-number" data-target="<?= (int)($counts['plan_count'] ?? 3) ?>">0</span>
                    <span class="stat-label">Membership Plans</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <span class="stat-number" data-target="<?= (int)($counts['open_slots'] ?? 50) ?>">0</span>
                    <span class="stat-label">Open Trainer Slots</span>
                </div>
            </div>
        </div>
    </div>
</div>

<section id="feature-hub" class="section-padding dashboard-section-shell">
    <div class="container">
        <div class="section-title" data-aos="fade-up">
            <span class="text-primary-custom fw-bold text-uppercase">Feature Hub</span>
            <h2>Every core feature is visible from home</h2>
            <p class="text-muted">Use these cards as your main navigation across the platform.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($dashboardLinks as $index => $item): ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= 100 + ($index * 50) ?>">
                    <a href="<?= htmlspecialchars($item['href']) ?>" class="feature-tile text-decoration-none">
                        <div class="feature-tile-icon">
                            <i class="fa-solid <?= htmlspecialchars($item['icon']) ?>"></i>
                        </div>
                        <h3><?= htmlspecialchars($item['title']) ?></h3>
                        <p><?= htmlspecialchars($item['text']) ?></p>
                        <span class="feature-link"><?= htmlspecialchars($item['label']) ?> <i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="member-hub" class="section-padding bg-light">
    <div class="container">
        <div class="member-hub-banner" data-aos="fade-up">
            <div>
                <span class="dashboard-kicker">Member Hub</span>
                <h2 class="mb-2"><?= $isLoggedIn ? 'Pick up where you left off' : 'Start your journey in a few clicks' ?></h2>
                <p class="mb-0 text-muted"><?= $isLoggedIn ? 'Jump to the actions members use most often.' : 'Create an account, compare plans, and explore the tools before joining.' ?></p>
            </div>
            <div class="member-hub-actions">
                <?php foreach ($memberActions as $action): ?>
                    <a href="<?= htmlspecialchars($action['href']) ?>" class="member-action-chip">
                        <i class="fa-solid <?= htmlspecialchars($action['icon']) ?>"></i>
                        <span><?= htmlspecialchars($action['title']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section id="membership" class="section-padding">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-5" data-aos="fade-up">
            <div>
                <span class="text-primary-custom fw-bold text-uppercase">Memberships</span>
                <h2 class="mb-2">Choose the right plan from home</h2>
                <p class="text-muted mb-0">Compare pricing, features, and trainer session benefits before checkout.</p>
            </div>
            <a href="plans.php" class="btn btn-outline-dark rounded-pill px-4">See All Plans</a>
        </div>
        <div class="row gy-4">
            <?php foreach ($featuredPlans as $plan): ?>
                <?php $features = json_decode($plan['features_json'], true) ?: []; ?>
                <div class="col-lg-4" data-aos="fade-up">
                    <div class="card card-custom plan-card <?= $plan['is_popular'] ? 'popular text-center' : 'text-center' ?> p-4 h-100">
                        <h3 class="mb-2"><?= htmlspecialchars($plan['plan_name']) ?></h3>
                        <p class="text-muted"><?= htmlspecialchars($plan['description']) ?></p>
                        <div class="price mb-3">₹<?= number_format((float) $plan['price'], 0) ?><span>/ <?= (int) $plan['duration_days'] ?> days</span></div>
                        <div class="small text-muted mb-3"><?= (int) $plan['trainer_sessions'] ?> trainer sessions included</div>
                        <ul class="list-unstyled text-start mb-4">
                            <?php foreach (array_slice($features, 0, 4) as $feature): ?>
                                <li class="mb-2"><i class="fa-solid fa-check text-primary-custom me-2"></i><?= htmlspecialchars($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="checkout.php?plan_id=<?= (int) $plan['plan_id'] ?>" class="btn <?= $plan['is_popular'] ? 'btn-primary-custom' : 'btn-outline-dark' ?> w-100 mt-auto">Choose Plan</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($featuredPlans)): ?>
                <div class="col-12">
                    <div class="card card-custom p-4 text-center">
                        <h3 class="mb-2">Plans will appear here</h3>
                        <p class="text-muted mb-3">Your home page is ready for plan cards as soon as membership data is available.</p>
                        <a href="plans.php" class="btn btn-outline-dark mx-auto">Open Plans Page</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section id="training" class="section-padding bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-5" data-aos="fade-up">
            <div>
                <span class="text-primary-custom fw-bold text-uppercase">Trainer Access</span>
                <h2 class="mb-2">Meet trainers and move straight into booking</h2>
                <p class="text-muted mb-0">Preview expertise on the homepage, then go deeper into trainer pages and booking flows.</p>
            </div>
            <a href="trainers.php" class="btn btn-outline-dark rounded-pill px-4">Browse All Trainers</a>
        </div>
        <div class="row gy-4">
            <?php foreach ($featuredTrainers as $trainer): ?>
                <div class="col-lg-3 col-md-6" data-aos="fade-up">
                    <div class="card card-custom border-0 shadow-sm text-center h-100">
                        <img src="<?= htmlspecialchars($trainer['photo'] ?: 'https://placehold.co/600x600/1A1A2E/FFFFFF?text=Trainer') ?>" class="card-img-top trainer-img" alt="<?= htmlspecialchars($trainer['full_name']) ?>">
                        <div class="card-body p-4 d-flex flex-column">
                            <h4 class="card-title fw-bold mb-1"><?= htmlspecialchars($trainer['full_name']) ?></h4>
                            <span class="text-primary-custom small fw-bold mb-3 d-block"><?= htmlspecialchars($trainer['specialization']) ?></span>
                            <p class="card-text text-muted small flex-grow-1"><?= htmlspecialchars($trainer['bio']) ?></p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap mb-3">
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-star"></i> <?= htmlspecialchars($trainer['rating']) ?></span>
                                <span class="badge bg-dark-subtle text-dark"><?= (int) $trainer['experience_years'] ?> yrs exp</span>
                            </div>
                            <a href="user/book-trainer.php?trainer_id=<?= (int) $trainer['trainer_id'] ?>" class="btn btn-outline-dark btn-sm w-100 mt-auto">Book Session</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($featuredTrainers)): ?>
                <div class="col-12">
                    <div class="card card-custom p-4 text-center">
                        <h3 class="mb-2">Trainer profiles will show here</h3>
                        <p class="text-muted mb-3">The trainer section is wired and ready once active trainer records are available.</p>
                        <a href="trainers.php" class="btn btn-outline-dark mx-auto">Open Trainers Page</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section id="schedule-preview" class="section-padding">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-5" data-aos="fade-right">
                <div class="schedule-side-panel h-100">
                    <span class="dashboard-kicker">Schedule Preview</span>
                    <h2 class="mt-2">See open trainer slots before you navigate away.</h2>
                    <p class="text-muted">The homepage now exposes schedule access directly, so users can spot availability and jump into booking faster.</p>
                    <div class="schedule-stat-row">
                        <div>
                            <strong><?= htmlspecialchars($metrics['slots']) ?></strong>
                            <span>Open slots</span>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($metrics['trainers']) ?></strong>
                            <span>Coaches</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="schedule.php" class="btn btn-primary-custom">Full Schedule</a>
                        <a href="trainers.php" class="btn btn-outline-dark">Book a Trainer</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-7" data-aos="fade-left">
                <div class="card card-custom border-0 shadow-sm h-100 schedule-preview-card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0">Next Available Windows</h3>
                            <span class="small text-muted">Live from trainer availability</span>
                        </div>
                        <div class="row g-3">
                            <?php foreach ($featuredSlots as $slot): ?>
                                <div class="col-md-6">
                                    <div class="slot-preview-item">
                                        <div class="slot-day"><?= htmlspecialchars($slot['day_of_week']) ?></div>
                                        <h4><?= htmlspecialchars($slot['full_name']) ?></h4>
                                        <p><?= htmlspecialchars($slot['specialization']) ?></p>
                                        <span><?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($featuredSlots)): ?>
                                <div class="col-12">
                                    <div class="slot-preview-item">
                                        <div class="slot-day">Schedule</div>
                                        <h4>No open slots loaded</h4>
                                        <p>Users can still open the full schedule page from here.</p>
                                        <span>Use the button on the left to continue</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="insights" class="section-padding bg-light">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-4" data-aos="fade-up">
                <div class="tool-highlight-card h-100">
                    <span class="dashboard-kicker">Wellness Tool</span>
                    <h2 class="mt-2">BMI tracking is one tap from home.</h2>
                    <p class="text-muted">Give visitors an immediate way to assess progress, then save results to their member dashboard after login.</p>
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2"><i class="fa-solid fa-check text-primary-custom me-2"></i>Instant BMI calculation</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-primary-custom me-2"></i>Health category and guidance</li>
                        <li><i class="fa-solid fa-check text-primary-custom me-2"></i>Saved history for members</li>
                    </ul>
                    <a href="bmi-calculator.php" class="btn btn-primary-custom">Launch BMI Calculator</a>
                </div>
            </div>
            <div class="col-lg-8" data-aos="fade-up" data-aos-delay="100">
                <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
                    <div>
                        <span class="text-primary-custom fw-bold text-uppercase">Articles & Updates</span>
                        <h2 class="mb-2">Keep education and discovery on the homepage</h2>
                        <p class="text-muted mb-0">Latest posts stay visible so the landing page keeps working as an information hub too.</p>
                    </div>
                    <a href="blog.php" class="btn btn-outline-dark rounded-pill px-4">Open Blog</a>
                </div>
                <div class="row g-3">
                    <?php foreach ($featuredPosts as $post): ?>
                        <div class="col-md-4">
                            <article class="blog-spotlight-card h-100">
                                <div class="blog-spotlight-image" style="background-image: linear-gradient(rgba(26, 26, 46, 0.2), rgba(26, 26, 46, 0.55)), url('<?= htmlspecialchars($post['featured_image'] ?: 'https://placehold.co/600x400/1A1A2E/FFFFFF?text=Fitness+Blog') ?>');"></div>
                                <div class="blog-spotlight-body">
                                    <span class="blog-category"><?= htmlspecialchars($post['category_name'] ?: 'Fitness') ?></span>
                                    <h3><?= htmlspecialchars($post['title']) ?></h3>
                                    <p><?= htmlspecialchars($post['excerpt']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <small class="text-muted"><?= date('M d, Y', strtotime($post['created_at'])) ?></small>
                                        <a href="blog-detail.php?slug=<?= urlencode($post['slug']) ?>">Read</a>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($featuredPosts)): ?>
                        <div class="col-12">
                            <div class="blog-spotlight-card">
                                <div class="blog-spotlight-body">
                                    <span class="blog-category">Blog</span>
                                    <h3>Latest articles will appear here</h3>
                                    <p class="mb-3">The homepage is ready to spotlight blog content as soon as published posts are available.</p>
                                    <a href="blog.php">Visit Blog</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!--  Gallery Teaser  -->
<section id="gallery" class="section-padding" style="background: var(--dark-bg);">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-5" data-aos="fade-up">
            <div>
                <span class="text-primary-custom fw-bold text-uppercase">Gallery</span>
                <h2 class="mb-2 text-white">A Glimpse Inside PowerHouse</h2>
                <p class="text-white-50 mb-0">State-of-the-art equipment, vibrant community, premium facilities.</p>
            </div>
            <a href="gallery.php" class="btn btn-primary-custom rounded-pill px-4">View Full Gallery</a>
        </div>
        <div class="gallery-teaser-grid" data-aos="fade-up" data-aos-delay="100">
            <?php
            $galleryTeaser = [
                ['https://placehold.co/600x450/1A1A2E/FF6B35?text=Training+Floor', 'Training Floor'],
                ['https://placehold.co/600x450/FF6B35/FFF?text=Cardio+Zone', 'Cardio Zone'],
                ['https://placehold.co/600x450/16213E/FF6B35?text=Free+Weights', 'Free Weights'],
                ['https://placehold.co/600x450/0F3460/FFF?text=Yoga+Studio', 'Yoga Studio'],
            ];
            // Prefer real gallery images if DB has them
            try {
                $galStmt = $pdo->query("SELECT file_path, title FROM gallery WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 4");
                $realGallery = $galStmt->fetchAll();
                if (!empty($realGallery)) {
                    $galleryTeaser = array_map(fn($r) => [$r['file_path'], $r['title']], $realGallery);
                }
            } catch(Exception $e) {}
            foreach ($galleryTeaser as [$src, $label]):
            ?>
                <a href="gallery.php" class="gallery-teaser-item">
                    <img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($label) ?>" loading="lazy">
                    <div class="gallery-teaser-overlay"><?= htmlspecialchars($label) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!--  Contact Quick-Access  -->
<section id="contact" class="section-padding bg-light">
    <div class="container">
        <div class="row g-4 align-items-center" data-aos="fade-up">
            <div class="col-lg-6">
                <span class="text-primary-custom fw-bold text-uppercase">Contact & Support</span>
                <h2 class="mb-3">Have a question before joining?</h2>
                <p class="text-muted mb-4">Our team is ready to help you pick the right plan, schedule a tour, or answer any gym-related questions. Reach out directly from here.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="contact.php" class="btn btn-primary-custom px-4"><i class="fa-solid fa-envelope me-2"></i>Send Message</a>
                    <a href="<?= htmlspecialchars($sitePhoneHref) ?>" class="btn btn-outline-dark px-4"><i class="fa-solid fa-phone me-2"></i>Call Us</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100">
                            <i class="fa-solid fa-location-dot fa-2x text-primary-custom mb-3"></i>
                            <h6 class="fw-bold">Find Us</h6>
                            <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($siteAddress)) ?></p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100">
                            <i class="fa-regular fa-clock fa-2x text-primary-custom mb-3"></i>
                            <h6 class="fw-bold">Gym Hours</h6>
                            <p class="text-muted small mb-0">Mon–Sat: 5AM–11PM<br>Sunday: 7AM–9PM</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100">
                            <i class="fa-solid fa-headset fa-2x text-primary-custom mb-3"></i>
                            <h6 class="fw-bold">Support</h6>
                            <p class="text-muted small mb-0"><?= htmlspecialchars($siteEmail) ?></p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100">
                            <i class="fa-brands fa-whatsapp fa-2x text-success mb-3"></i>
                            <h6 class="fw-bold">WhatsApp</h6>
                            <a href="<?= htmlspecialchars($siteWhatsappHref) ?>" class="text-muted small">Chat with Us</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding dashboard-cta-section">
    <div class="container" data-aos="zoom-in">
        <div class="dashboard-cta-panel">
            <div>
                <span class="dashboard-kicker">Ready to train?</span>
                <h2 class="mt-2 mb-2">Use the home page as your launch point for every gym feature.</h2>
                <p class="mb-0 text-white-50">Plans, bookings, BMI, blog, schedule, and support are now surfaced directly instead of being hidden behind separate pages.</p>
            </div>
            <div class="d-flex flex-wrap gap-3">
                <a href="contact.php" class="btn btn-light text-primary-custom fw-bold">Talk to Us</a>
                <a href="<?= $isLoggedIn ? $dashboardHref : 'auth/register.php' ?>" class="btn btn-primary-custom">
                    <?= $isLoggedIn ? ($isAdmin ? 'Go to Admin Panel' : 'Go to Dashboard') : 'Create Account' ?>
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
