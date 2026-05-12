<?php
$pageTitle = 'About Us';
require_once 'includes/header.php';
require_once 'includes/nav.php';
require_once 'helpers/site_settings_helper.php';

$aboutTitle = site_settings_get('about_title', 'Our Story');
$aboutSubtitle = site_settings_get('about_subtitle', 'More Than Just A Gym');
$aboutDesc = site_settings_get('about_description', 'Founded in 2010, FITNESS DESTINATION started with a simple mission: to provide a welcoming, high-energy environment for people of all fitness levels. We believe that fitness is not just about heavy lifting or intense cardio; it\'s about building a sustainable lifestyle.');
$aboutDesc2 = site_settings_get('about_description2', "Over the years, we've grown from a small neighborhood facility to a state-of-the-art fitness center spanning 10,000 square feet, equipped with the latest premium workout machines and a team of certified, passionate trainers.");
$aboutMission = site_settings_get('about_mission', 'Empower individuals to achieve fitness goals.');
$aboutVision = site_settings_get('about_vision', 'Create a healthier, stronger community.');
$aboutImagePath = site_settings_get('about_image');

// Build main image src
if ($aboutImagePath !== '' && file_exists(__DIR__ . '/' . $aboutImagePath)) {
    $aboutImgSrc = SITE_URL . '/' . ltrim($aboutImagePath, '/');
} else {
    $aboutImgSrc = 'https://placehold.co/800x600/FF6B35/FFF?text=Gym+Interior';
}

// Fetch team details
$teamSectionEnabled = site_settings_get('team_section_enabled', '1');
$ceoName = site_settings_get('team_ceo_name', 'John Doe');
$ceoTitle = site_settings_get('team_ceo_title', 'Founder & CEO');
$ceoImgPath = site_settings_get('team_ceo_image');
$ceoImgSrc = ($ceoImgPath !== '' && file_exists(__DIR__ . '/' . $ceoImgPath)) ? SITE_URL . '/' . ltrim($ceoImgPath, '/') : 'https://placehold.co/300x300/1A1A2E/FFF?text=CEO';

$managerName = site_settings_get('team_manager_name', 'Jane Smith');
$managerTitle = site_settings_get('team_manager_title', 'General Manager');
$managerImgPath = site_settings_get('team_manager_image');
$managerImgSrc = ($managerImgPath !== '' && file_exists(__DIR__ . '/' . $managerImgPath)) ? SITE_URL . '/' . ltrim($managerImgPath, '/') : 'https://placehold.co/300x300/FF6B35/FFF?text=Manager';

$headTrainerName = site_settings_get('team_head_trainer_name', 'Mike Johnson');
$headTrainerTitle = site_settings_get('team_head_trainer_title', 'Head Trainer');
$headTrainerImgPath = site_settings_get('team_head_trainer_image');
$headTrainerImgSrc = ($headTrainerImgPath !== '' && file_exists(__DIR__ . '/' . $headTrainerImgPath)) ? SITE_URL . '/' . ltrim($headTrainerImgPath, '/') : 'https://placehold.co/300x300/333/FFF?text=Head+Trainer';
?>

<section class="section-padding bg-light">
    <div class="container">
        <div class="row align-items-center gy-5">
            <!-- Image -->
            <div class="col-lg-6" data-aos="fade-right">
                <img src="<?= htmlspecialchars($aboutImgSrc) ?>" alt="<?= htmlspecialchars($aboutSubtitle) ?>"
                     class="img-fluid rounded shadow-lg"
                     style="width:100%; height:420px; object-fit:cover;">
            </div>

            <!-- Text Content -->
            <div class="col-lg-6" data-aos="fade-left">
                <span class="text-primary-custom fw-bold text-uppercase" style="letter-spacing:0.06em;">
                    <?= htmlspecialchars($aboutTitle) ?>
                </span>
                <h2 class="mb-4 mt-1"><?= htmlspecialchars($aboutSubtitle) ?></h2>
                <p><?= nl2br(htmlspecialchars($aboutDesc)) ?></p>
                <?php if (trim($aboutDesc2) !== ''): ?>
                <p><?= nl2br(htmlspecialchars($aboutDesc2)) ?></p>
                <?php endif; ?>

                <!-- Mission & Vision Cards -->
                <div class="row mt-4">
                    <div class="col-sm-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 bg-primary-custom rounded-circle p-3 text-white">
                                <i class="fa-solid fa-bullseye fs-4"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="mb-0 fw-bold">Our Mission</h5>
                                <small class="text-muted"><?= htmlspecialchars($aboutMission) ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 bg-dark rounded-circle p-3 text-white">
                                <i class="fa-solid fa-eye fs-4"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="mb-0 fw-bold">Our Vision</h5>
                                <small class="text-muted"><?= htmlspecialchars($aboutVision) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($teamSectionEnabled === '1'): ?>
<!-- Team Section -->
<section class="section-padding">
    <div class="container text-center">
        <div class="section-title" data-aos="fade-up">
            <h2>Meet The Core Team</h2>
        </div>
        <p class="text-muted" data-aos="fade-up" data-aos-delay="100">Our founders and managers who make it all possible.</p>
        <div class="row mt-5 gy-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <img src="<?= htmlspecialchars($ceoImgSrc) ?>" class="rounded-circle mb-3 img-thumbnail" alt="<?= htmlspecialchars($ceoName) ?>" style="width:300px; height:300px; object-fit:cover;">
                <h4><?= htmlspecialchars($ceoName) ?></h4>
                <p class="text-primary-custom"><?= htmlspecialchars($ceoTitle) ?></p>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <img src="<?= htmlspecialchars($managerImgSrc) ?>" class="rounded-circle mb-3 img-thumbnail" alt="<?= htmlspecialchars($managerName) ?>" style="width:300px; height:300px; object-fit:cover;">
                <h4><?= htmlspecialchars($managerName) ?></h4>
                <p class="text-primary-custom"><?= htmlspecialchars($managerTitle) ?></p>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                <img src="<?= htmlspecialchars($headTrainerImgSrc) ?>" class="rounded-circle mb-3 img-thumbnail" alt="<?= htmlspecialchars($headTrainerName) ?>" style="width:300px; height:300px; object-fit:cover;">
                <h4><?= htmlspecialchars($headTrainerName) ?></h4>
                <p class="text-primary-custom"><?= htmlspecialchars($headTrainerTitle) ?></p>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
