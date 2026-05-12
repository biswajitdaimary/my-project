<?php
$pageTitle = 'Terms of Service';
require_once 'includes/header.php';
require_once 'includes/nav.php';

$termsGymName = site_settings_get('gym_name', SITE_NAME);
$termsEmail = site_settings_get('email', 'info@powerhousegym.com');
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
                    <i class="fa-solid fa-file-contract"></i>
                </div>
                <h1 class="fw-bold display-5 mb-3">Terms of Service</h1>
                <p class="text-muted lead">The ground rules for using <?= htmlspecialchars($termsGymName) ?> online services and membership features.</p>
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
                        
                        <div id="use-of-website" class="mb-5 pt-3">
                            <h3 class="fw-bold legal-section-title">Use Of The Website</h3>
                            <p class="text-muted" style="line-height: 1.8;">By using this website, you agree to use it lawfully and respectfully. You must not misuse booking features, account access, forms, or any protected area of the platform. We reserve the right to suspend accounts that violate these guidelines.</p>
                        </div>

                        <div id="membership" class="mb-5 pt-3">
                            <h3 class="fw-bold legal-section-title">Membership And Bookings</h3>
                            <p class="text-muted" style="line-height: 1.8;">Membership plans, trainer sessions, and class bookings are subject to availability, pricing, and gym policies. We may update schedules, trainers, or plan details when operational needs change. Cancellations must adhere to our booking rules.</p>
                        </div>

                        <div id="payments" class="mb-5 pt-3">
                            <h3 class="fw-bold legal-section-title">Payments</h3>
                            <p class="text-muted" style="line-height: 1.8;">Payments made through the site must be accurate and authorized. If a payment issue, verification failure, or suspected misuse occurs, we may pause access until the matter is resolved. All transactions are securely encrypted.</p>
                        </div>

                        <div id="content" class="mb-5 pt-3">
                            <h3 class="fw-bold legal-section-title">Content And Availability</h3>
                            <p class="text-muted" style="line-height: 1.8;">We aim to keep information accurate and the site available, but we cannot guarantee uninterrupted access or that every page will always be error-free. We may update or improve features at any time without prior notice.</p>
                        </div>

                        <div id="liability" class="mb-5 pt-3">
                            <h3 class="fw-bold legal-section-title">Limitation Of Responsibility</h3>
                            <p class="text-muted" style="line-height: 1.8;"><strong><?= htmlspecialchars($termsGymName) ?></strong> is not responsible for losses arising from misuse of the site, inaccurate information supplied by users, or temporary service interruptions beyond our reasonable control.</p>
                        </div>

                        <div id="questions" class="pt-3">
                            <h3 class="fw-bold legal-section-title">Questions About These Terms</h3>
                            <div class="bg-light rounded p-4 border mt-4 d-flex align-items-center">
                                <div class="bg-white rounded-circle d-flex justify-content-center align-items-center shadow-sm me-3" style="width: 50px; height: 50px;">
                                    <i class="fa-solid fa-envelope text-primary-custom fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1">Get in Touch</h5>
                                    <p class="mb-0 text-muted">For questions about these terms, contact us at <a href="mailto:<?= htmlspecialchars($termsEmail) ?>" class="text-decoration-none fw-semibold"><?= htmlspecialchars($termsEmail) ?></a>.</p>
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
