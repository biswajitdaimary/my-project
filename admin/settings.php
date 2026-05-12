<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/site_settings_helper.php';
require_admin();

$pageTitle = 'Site Settings';
$success = '';
$error = '';
$fieldErrors = [];

$settings = site_settings_get_all($pdo);
$formValues = $settings;
$socialFields = site_settings_social_fields();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_contact_info') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid security token.';
    } else {
        [$cleanData, $fieldErrors] = site_settings_validate_contact_payload($_POST);
        $formValues = array_merge($formValues, $cleanData);

        if (empty($fieldErrors)) {
            try {
                site_settings_upsert($pdo, $cleanData);
                $settings = site_settings_get_all($pdo, true);
                $formValues = $settings;
                $success = 'Contact information updated successfully.';
            } catch (PDOException $e) {
                $error = 'Failed to save contact information.';
            }
        } else {
            $error = 'Please fix the highlighted fields and try again.';
        }
    }
}

$displayValue = function (string $key, string $fallback = '') use ($formValues): string {
    $defaults = site_settings_defaults();
    $value = $formValues[$key] ?? ($defaults[$key] ?? $fallback);
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$fieldClass = function (string $key, string $base = 'form-control') use ($fieldErrors): string {
    return $base . (isset($fieldErrors[$key]) ? ' is-invalid' : '');
};

$liveSettings = array_merge(site_settings_defaults(), $settings);
$previewSocials = [];
foreach ($socialFields as $key => $meta) {
    $url = trim((string) ($liveSettings[$key] ?? ''));
    if ($url !== '') {
        $previewSocials[] = $meta + ['url' => $url];
    }
}

require_once 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold m-0"><i class="fa-solid fa-gear text-muted me-2"></i>Site Settings</h3>
        <p class="text-muted small mb-0 mt-2">Update the public contact details shown on the footer, contact page, home page, and payment receipts.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4 mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success rounded-4 mb-4"><i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <form method="POST" action="settings.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="save_contact_info">

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4 border-bottom pb-3">
                        <div>
                            <h5 class="fw-bold mb-1">
                                <i class="fa-solid fa-address-card me-2 text-primary-custom"></i>Contact Info Editor
                            </h5>
                            <p class="text-muted small mb-0">Manage the office phone, email, address, and brand name from one place.</p>
                        </div>
                        <span class="badge text-bg-light border px-3 py-2">Live on next refresh</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Gym Name</label>
                            <input
                                type="text"
                                name="gym_name"
                                class="<?= $fieldClass('gym_name') ?>"
                                value="<?= $displayValue('gym_name', SITE_NAME) ?>"
                                maxlength="100"
                                required
                            >
                            <?php if (isset($fieldErrors['gym_name'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['gym_name']) ?></div>
                            <?php else: ?>
                                <div class="form-text">Used for branding on the navigation, footer, and receipt screens.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Office Phone</label>
                            <input
                                type="tel"
                                name="phone"
                                class="<?= $fieldClass('phone') ?>"
                                value="<?= $displayValue('phone', '+1 234 567 890') ?>"
                                placeholder="+91 98765 43210"
                                pattern="[0-9+\-\s().]{7,25}"
                                required
                            >
                            <?php if (isset($fieldErrors['phone'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['phone']) ?></div>
                            <?php else: ?>
                                <div class="form-text">Use an international format if you want the WhatsApp link to work cleanly.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Office Email</label>
                            <input
                                type="email"
                                name="email"
                                class="<?= $fieldClass('email') ?>"
                                value="<?= $displayValue('email', 'info@powerhousegym.com') ?>"
                                placeholder="info@example.com"
                                required
                            >
                            <?php if (isset($fieldErrors['email'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['email']) ?></div>
                            <?php else: ?>
                                <div class="form-text">Shown on the public contact card, footer, and receipt layout.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Office Address</label>
                            <textarea
                                name="address"
                                class="<?= $fieldClass('address') ?>"
                                rows="4"
                                placeholder="123 Fitness Street&#10;Gym City"
                                required
                            ><?= $displayValue('address', "123 Fitness Street\nGym City") ?></textarea>
                            <?php if (isset($fieldErrors['address'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['address']) ?></div>
                            <?php else: ?>
                                <div class="form-text">You can use multiple lines. The website will preserve line breaks.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-12 mt-4">
                            <h6 class="fw-bold mb-3 border-bottom pb-2">Business Hours</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Monday - Friday</label>
                            <input
                                type="text"
                                name="hours_weekday"
                                class="<?= $fieldClass('hours_weekday') ?>"
                                value="<?= $displayValue('hours_weekday', '6:00 AM - 10:00 PM') ?>"
                                placeholder="6:00 AM - 10:00 PM"
                            >
                            <?php if (isset($fieldErrors['hours_weekday'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['hours_weekday']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Saturday</label>
                            <input
                                type="text"
                                name="hours_saturday"
                                class="<?= $fieldClass('hours_saturday') ?>"
                                value="<?= $displayValue('hours_saturday', '8:00 AM - 8:00 PM') ?>"
                                placeholder="8:00 AM - 8:00 PM"
                            >
                            <?php if (isset($fieldErrors['hours_saturday'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['hours_saturday']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Sunday</label>
                            <input
                                type="text"
                                name="hours_sunday"
                                class="<?= $fieldClass('hours_sunday') ?>"
                                value="<?= $displayValue('hours_sunday', '8:00 AM - 2:00 PM') ?>"
                                placeholder="8:00 AM - 2:00 PM"
                            >
                            <?php if (isset($fieldErrors['hours_sunday'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors['hours_sunday']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4 border-bottom pb-2">
                        <i class="fa-solid fa-share-nodes me-2 text-primary-custom"></i>Social Media Links
                    </h5>
                    <div class="row g-3">
                        <?php foreach ($socialFields as $key => $meta): ?>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">
                                    <i class="fa-brands <?= $meta['icon'] ?> me-1 text-primary-custom"></i><?= htmlspecialchars($meta['label']) ?>
                                </label>
                                <input
                                    type="url"
                                    name="<?= htmlspecialchars($key) ?>"
                                    class="<?= $fieldClass($key) ?>"
                                    value="<?= $displayValue($key) ?>"
                                    placeholder="<?= htmlspecialchars($meta['placeholder']) ?>"
                                >
                                <?php if (isset($fieldErrors[$key])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($fieldErrors[$key]) ?></div>
                                <?php else: ?>
                                    <div class="form-text">Optional. Leave blank to hide this platform from the public site.</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                        <button type="submit" class="btn btn-primary-custom px-5 rounded-pill">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Save Contact Info
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">
                <h5 class="fw-bold mb-3 border-bottom pb-2">
                    <i class="fa-solid fa-key me-2 text-primary-custom"></i>Payment Gateway
                </h5>
                <div class="alert alert-warning border-0 rounded-3 small">
                    <i class="fa-solid fa-lock me-2"></i>
                    For security, API keys are managed in <code>config/payment.php</code> directly on the server.
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Razorpay Key ID (Masked)</label>
                        <input type="text" class="form-control bg-light text-muted" value="rzp_test_••••••••••" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Gateway Status</label>
                        <div class="form-control bg-light d-flex align-items-center gap-2">
                            <span class="badge bg-success">Active</span>
                            <span class="small text-muted">Razorpay (Test Mode)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 border-bottom pb-2">Live Preview</h6>
                <div class="rounded-4 p-4 text-white" style="background: linear-gradient(135deg, #1A1A2E 0%, #23233d 100%);">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                        <div>
                            <div class="small text-uppercase text-white-50 mb-1">Public Contact Card</div>
                            <h5 class="fw-bold mb-0"><?= htmlspecialchars($liveSettings['gym_name'] ?? SITE_NAME) ?></h5>
                        </div>
                        <span class="badge bg-light text-dark">Live</span>
                    </div>

                    <div class="small text-white-50 mb-3">
                        <div class="mb-2"><i class="fa-solid fa-phone me-2 text-primary-custom"></i><?= htmlspecialchars($liveSettings['phone'] ?? '') ?></div>
                        <div class="mb-2"><i class="fa-solid fa-envelope me-2 text-primary-custom"></i><?= htmlspecialchars($liveSettings['email'] ?? '') ?></div>
                        <div><i class="fa-solid fa-location-dot me-2 text-primary-custom"></i><?= nl2br(htmlspecialchars($liveSettings['address'] ?? '')) ?></div>
                    </div>

                    <?php if (!empty($previewSocials)): ?>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <?php foreach ($previewSocials as $social): ?>
                                <a href="<?= htmlspecialchars($social['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-sm rounded-circle">
                                    <i class="fa-brands <?= $social['icon'] ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 bg-dark text-white mb-4">
            <div class="card-body p-4 text-center">
                <i class="fa-solid fa-shield-halved fa-3x text-primary-custom mb-3"></i>
                <h5 class="fw-bold">Security Status</h5>
                <p class="small text-white-50 mb-0">
                    This editor uses CSRF protection, server-side validation, and PDO prepared statements before writing anything to the database.
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 border-bottom pb-2">System Info</h6>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">PHP Version</span>
                    <span class="fw-bold"><?= phpversion() ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Server Time</span>
                    <span class="fw-bold"><?= date('H:i:s') ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Timezone</span>
                    <span class="fw-bold"><?= date_default_timezone_get() ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Logged in as</span>
                    <span class="fw-bold"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
