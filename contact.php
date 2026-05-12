<?php
$pageTitle = 'Contact Us';
require_once 'includes/header.php';
require_once 'includes/nav.php';
require_once 'helpers/notification_helper.php';

$contactGymName = site_settings_get('gym_name', SITE_NAME);
$contactPhone = site_settings_get('phone', '+1 234 567 890');
$contactEmail = site_settings_get('email', 'info@powerhousegym.com');
$contactAddress = site_settings_get('address', "123 Fitness Street\nGym City");
$contactSocials = [];

foreach (site_settings_social_fields() as $key => $meta) {
    $url = trim(site_settings_get($key));
    if ($url !== '') {
        $contactSocials[] = $meta + ['url' => $url];
    }
}

// Form processing status
$success = false;
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid security token.";
    } 
    // Honeypot check for spam
    elseif (!empty($_POST['website_hp'])) {
        $error = "Spam detected.";
    } 
    else {
        // Sanitize input
        $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars(trim($_POST['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');

        if (empty($name) || empty($email) || empty($message)) {
            $error = "Name, Email, and Message are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $subject, $message]);
                $success = true;

                // Notify Admin
                notify_admin(
                    $pdo,
                    "New Contact Message",
                    "{$name} ({$email}) has sent a new message regarding '{$subject}'.",
                    "info",
                    "contacts.php"
                );

            } catch (PDOException $e) {
                $error = "An error occurred while saving your message. Please try again.";
            }
        }
    }
}
?>

<section class="section-padding bg-light">
    <div class="container">
        <div class="section-title text-center" data-aos="fade-up">
            <h2>Get In Touch</h2>
            <p class="text-muted">Have a question? We'd love to hear from you.</p>
        </div>

        <div class="row mt-5 gy-5">
            <div class="col-lg-4" data-aos="fade-right">
                <div class="card card-custom p-4 bg-dark text-white border-0 h-100">
                    <h4 class="mb-4 text-primary-custom">Contact Information</h4>
                    <p class="text-white-50 small mb-4">These details are managed from the admin Contact Info Editor and update here automatically.</p>
                    <div class="d-flex mb-4">
                        <i class="fa-solid fa-location-dot fs-3 text-primary-custom me-3 mt-1"></i>
                        <div>
                            <h5>Our Location</h5>
                            <p class="text-white-50 mb-0"><?= nl2br(htmlspecialchars($contactAddress)) ?></p>
                        </div>
                    </div>
                    <div class="d-flex mb-4">
                        <i class="fa-solid fa-phone fs-3 text-primary-custom me-3 mt-1"></i>
                        <div>
                            <h5>Phone Number</h5>
                            <p class="text-white-50 mb-0"><a href="<?= htmlspecialchars(site_settings_phone_href($contactPhone)) ?>" class="text-white-50 text-decoration-none"><?= htmlspecialchars($contactPhone) ?></a></p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <i class="fa-solid fa-envelope fs-3 text-primary-custom me-3 mt-1"></i>
                        <div>
                            <h5>Email Address</h5>
                            <p class="text-white-50 mb-0"><a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="text-white-50 text-decoration-none"><?= htmlspecialchars($contactEmail) ?></a></p>
                        </div>
                    </div>

                    <?php if (!empty($contactSocials)): ?>
                        <h5 class="mt-5 mb-3 text-primary-custom">Follow <?= htmlspecialchars($contactGymName) ?></h5>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($contactSocials as $social): ?>
                                <a href="<?= htmlspecialchars($social['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-sm rounded-circle">
                                    <i class="fa-brands <?= $social['icon'] ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <h5 class="mt-5 mb-3 text-primary-custom">Business Hours</h5>
                    <div class="d-flex flex-column gap-2 text-white-50 small">
                        <div class="d-flex justify-content-between border-bottom border-secondary pb-2 border-opacity-25">
                            <span>Monday - Friday:</span>
                            <span class="fw-semibold text-white"><?= htmlspecialchars(site_settings_get('hours_weekday', '6:00 AM - 10:00 PM')) ?></span>
                        </div>
                        <div class="d-flex justify-content-between border-bottom border-secondary pb-2 border-opacity-25">
                            <span>Saturday:</span>
                            <span class="fw-semibold text-white"><?= htmlspecialchars(site_settings_get('hours_saturday', '8:00 AM - 8:00 PM')) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Sunday:</span>
                            <span class="fw-semibold text-white"><?= htmlspecialchars(site_settings_get('hours_sunday', '8:00 AM - 2:00 PM')) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8" data-aos="fade-left">
                <div class="card card-custom p-4 p-md-5 border-0 h-100">
                    <h4 class="mb-4">Send us a message</h4>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="fa-solid fa-check-circle me-2"></i> Your message has been sent successfully. We will get back to you soon.</div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> <?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST" action="contact.php">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <!-- Honeypot -->
                        <input type="text" name="website_hp" style="display:none" tabindex="-1" autocomplete="off">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Your Name *</label>
                                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Your Email *</label>
                                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Message *</label>
                            <textarea name="message" class="form-control" rows="5" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary-custom px-5 py-2">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Google Map -->
<div class="w-100" style="height: 400px;">
    <iframe 
        width="100%" 
        height="100%" 
        style="border:0;" 
        loading="lazy" 
        allowfullscreen 
        referrerpolicy="no-referrer-when-downgrade" 
        src="https://maps.google.com/maps?q=<?= urlencode($contactGymName . ', ' . str_replace(["\r", "\n"], ', ', $contactAddress)) ?>&t=&z=15&ie=UTF8&iwloc=&output=embed">
    </iframe>
</div>

<?php require_once 'includes/footer.php'; ?>
