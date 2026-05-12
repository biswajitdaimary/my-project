<?php
$pageTitle = 'BMI Calculator';
require_once 'config/config.php';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<section class="section-padding bg-light min-vh-100">
    <div class="container">
        <div class="section-title text-center" data-aos="fade-up">
            <span class="text-primary-custom fw-bold text-uppercase">Check Your Fitness</span>
            <h2 class="mb-3">BMI Calculator</h2>
            <p class="text-muted">Calculate your Body Mass Index, understand your current range, and save readings just like the other tools across the site.</p>
        </div>

        <div class="row gy-4 align-items-stretch">
            <div class="col-lg-5" data-aos="fade-up">
                <div class="card card-custom bmi-feature-card h-100">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div>
                                <h4 class="fw-bold mb-1">Enter Your Details</h4>
                                <p class="text-muted small mb-0">Use your current height and weight for the most accurate reading.</p>
                            </div>
                            <span class="bmi-panel-icon">
                                <i class="fa-solid fa-ruler-combined"></i>
                            </span>
                        </div>

                        <form
                            id="bmiForm"
                            method="post"
                            data-save-url="<?= SITE_URL ?>/api/save-bmi.php"
                            data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                            data-dashboard-url="<?= SITE_URL ?>/user/dashboard.php?bmi_saved=1"
                            data-is-logged-in="<?= isset($_SESSION['user_id']) ? '1' : '0' ?>"
                        >
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="age">Age</label>
                                    <div class="input-group input-group-lg">
                                        <input type="number" class="form-control bg-light" id="age" name="age" placeholder="e.g. 25" min="5" max="120" required>
                                        <span class="input-group-text">yrs</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="gender">Gender</label>
                                    <select class="form-select form-select-lg bg-light" id="gender" name="gender" required>
                                        <option value="" selected disabled>Select gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Height</label>
                                <div class="input-group input-group-lg">
                                    <input type="number" class="form-control bg-light" id="height" name="height" placeholder="e.g. 175" step="0.1" min="50" max="300" required>
                                    <span class="input-group-text">cm</span>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Weight</label>
                                <div class="input-group input-group-lg">
                                    <input type="number" class="form-control bg-light" id="weight" name="weight" placeholder="e.g. 70" step="0.1" min="10" max="500" required>
                                    <span class="input-group-text">kg</span>
                                </div>
                            </div>
                            <div id="bmiFormFlash" class="alert d-none text-start small"></div>
                            <button type="submit" class="btn btn-primary-custom w-100 py-3 rounded-pill fw-bold text-uppercase">Calculate BMI</button>
                        </form>

                        <div class="bmi-reference-card mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="fw-bold mb-0">BMI Reference</h6>
                                <span class="small text-muted">Standard ranges</span>
                            </div>
                            <div class="bmi-reference-list">
                                <div class="bmi-reference-item">
                                    <span>Underweight</span>
                                    <span class="text-muted">Below 18.5</span>
                                </div>
                                <div class="bmi-reference-item">
                                    <span>Normal</span>
                                    <span class="text-muted">18.5 - 24.9</span>
                                </div>
                                <div class="bmi-reference-item">
                                    <span>Overweight</span>
                                    <span class="text-muted">25.0 - 29.9</span>
                                </div>
                                <div class="bmi-reference-item">
                                    <span>Obese</span>
                                    <span class="text-muted">30.0+</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7" data-aos="fade-up" data-aos-delay="100">
                <div class="card card-custom bmi-feature-card h-100">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div>
                                <h4 class="fw-bold mb-1">Your Result</h4>
                                <p class="text-muted small mb-0">Your BMI summary will appear here with a quick recommendation.</p>
                            </div>
                            <span class="bmi-panel-icon">
                                <i class="fa-solid fa-heart-pulse"></i>
                            </span>
                        </div>

                        <div class="bmi-result-shell text-center">
                            <div id="bmiResultContainer" class="w-100" style="display: none;">
                                <h5 class="text-muted mb-2">Your BMI Is</h5>
                                <div id="bmiValue" class="display-1 fw-bold mb-3">22.5</div>
                                <h3 id="bmiCategory" class="mb-4 text-uppercase fw-bold">Normal</h3>

                                <div class="bmi-tip-card text-start mb-3" role="alert">
                                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-lightbulb text-warning me-2"></i>Health Tip</h6>
                                    <p id="bmiTip" class="mb-0 small">Maintain your current healthy lifestyle with regular exercise and balanced diet.</p>
                                </div>

                                <div class="bmi-range-chip small text-muted mb-3">
                                    Healthy Range:
                                    <span id="bmiRange" class="fw-bold">18.5 - 24.9</span>
                                </div>
                                <div id="bmiFlash" class="alert d-none text-start small"></div>
                                <div class="d-grid gap-2 mt-4">
                                    <button
                                        type="button"
                                        id="saveBmiBtn"
                                        class="btn btn-primary-custom py-3 rounded-pill fw-bold text-uppercase"
                                        disabled
                                    >
                                        <i class="fa-solid fa-floppy-disk me-2"></i>Save to Dashboard
                                    </button>
                                    <div id="saveStatus" class="small d-none text-start text-muted"></div>
                                </div>

                                <?php if (!isset($_SESSION['user_id'])): ?>
                                    <small class="text-muted mt-3 d-block">
                                        <a href="auth/login.php" class="text-primary-custom">Log in</a> to save results and track history.
                                    </small>
                                <?php endif; ?>
                            </div>

                            <div id="bmiPlaceholder" class="bmi-placeholder text-muted">
                                <span class="bmi-placeholder-icon">
                                    <i class="fa-solid fa-calculator"></i>
                                </span>
                                <h5 class="fw-bold text-dark mt-3">Ready to calculate</h5>
                                <p class="mb-0">Enter your age, gender, height, and weight to see your result here.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Scripts -->
<script src="<?= SITE_URL ?>/assets/js/bmi-calculator.js?v=<?= time() ?>"></script>
<?php require_once 'includes/footer.php'; ?>
