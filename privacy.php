<?php
$pageTitle = 'Privacy Policy';
require_once 'includes/header.php';
require_once 'includes/nav.php';

$privacyGymName = site_settings_get('gym_name', SITE_NAME);
$privacyEmail = site_settings_get('email', 'info@powerhousegym.com');
$privacyPhone = site_settings_get('phone', '+1 234 567 890');
?>

<style>
    /* Legal Pages Custom Styling */
    .legal-section-title {
        position: relative;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    .legal-section-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 3px;
        background-color: var(--primary-color);
        border-radius: 2px;
    }
</style>

<section class="section-padding bg-light" style="min-height: 80vh;">
    <div class="container">
        <!-- Header -->
        <div class="row justify-content-center mb-5 text-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="d-inline-flex align-items-center justify-content-center text-white rounded-circle mb-3 shadow-sm" style="width: 70px; height: 70px; font-size: 1.8rem; background: linear-gradient(135deg, var(--primary-color), #e55520);">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h1 class="fw-bold display-5 mb-3">Privacy Policy</h1>
                <p class="text-muted lead">How <?= htmlspecialchars($privacyGymName) ?> collects, uses, and protects your information.</p>
                <div class="badge rounded-pill px-4 py-2 mt-2 shadow-sm" style="background-color: #e9ecef; color: #495057; font-size: 0.9rem; font-weight: 500;">
                    <i class="fa-regular fa-calendar me-1"></i> Last Updated: <?= date('F j, Y') ?>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <!-- Content -->
            <div class="col-xl-10" data-aos="fade-up" data-aos-delay="100">
                <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
                    <div class="card-body p-4 p-md-5">
                        
                        <div id="information" class="mb-5 pt-3">
                            <h3 class="fw-bold legal-section-title">Information We Collect</h3>
                            <p class="text-muted" style="line-height: 1.8;">We may collect details you provide directly to us, including your name, email address, phone number, fitness preferences, booking activity, payment-related records, and messages submitted through our forms.</p>
                        </div>

                        <div id="usage" class="mb-5 pt-3">
                            <h3 class="fw-bold legal-section-title">How We Use Your Information</h3>
                            <p class="text-muted" style="line-height: 1.8;">We use your information to manage memberships, process bookings, respond to enquiries, improve gym services, maintain account security, and send important service updates related to your membership or activity.</p>
                        </div>

                        <div id="sharing" class="mb-5 pt-3">
                            <h3 class="fw-bold legal-section-title">Sharing Of Information</h3>
                            <p class="text-muted" style="line-height: 1.8;">We do not sell your personal information. We may share limited information with trusted service providers only when needed to support payments, notifications, site operations, or legal compliance.</p>
                        </div>

                        <div id="security" class="mb-5 pt-3">
                            <h3 class="fw-bold legal-section-title">Data Security</h3>
                            <p class="text-muted" style="line-height: 1.8;">We take reasonable technical and organizational steps to protect your information from unauthorized access, disclosure, or misuse. No internet-based system can be guaranteed fully secure, but we work to keep your data protected.</p>
                        </div>

                        <div id="choices" class="mb-5 pt-3">
                            <h3 class="fw-bold legal-section-title">Your Choices</h3>
                            <p class="text-muted" style="line-height: 1.8;">You may request updates or corrections to your personal information by contacting us. You can also reach out if you want clarification about how your information is stored or used.</p>
                        </div>

                        <div id="contact" class="pt-3">
                            <h3 class="fw-bold legal-section-title">Contact Us About Privacy</h3>
                            <div class="bg-light rounded p-4 border mt-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-white rounded-circle d-flex justify-content-center align-items-center shadow-sm me-3" style="width: 40px; height: 40px;">
                                        <i class="fa-solid fa-envelope text-primary-custom fs-5"></i>
                                    </div>
                                    <p class="mb-0 text-muted fw-semibold"><a href="mailto:<?= htmlspecialchars($privacyEmail) ?>" class="text-decoration-none"><?= htmlspecialchars($privacyEmail) ?></a></p>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="bg-white rounded-circle d-flex justify-content-center align-items-center shadow-sm me-3" style="width: 40px; height: 40px;">
                                        <i class="fa-solid fa-phone text-primary-custom fs-5"></i>
                                    </div>
                                    <p class="mb-0 text-muted fw-semibold"><a href="<?= htmlspecialchars(site_settings_phone_href($privacyPhone)) ?>" class="text-decoration-none"><?= htmlspecialchars($privacyPhone) ?></a></p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
