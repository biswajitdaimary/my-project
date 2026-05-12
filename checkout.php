<?php
$pageTitle = 'Checkout';
require_once 'config/config.php';
require_once 'helpers/auth_check.php';

require_login();
$user_id = $_SESSION['user_id'];
$plan_id = $_GET['plan_id'] ?? null;

if (!$plan_id) {
    header("Location: plans.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE plan_id = ? AND is_active = 1");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();
    if (!$plan) { die("Invalid Plan."); }
} catch (PDOException $e) { die("Database Error"); }

// Fetch user profile
try {
    $uStmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE user_id = ?");
    $uStmt->execute([$user_id]);
    $userProfile = $uStmt->fetch();
} catch (PDOException $e) { $userProfile = null; }

$features = [];
if (!empty($plan['features_json'])) {
    $features = json_decode($plan['features_json'], true) ?? [];
}

$gst   = round($plan['price'] * 0.18, 2);
$total = $plan['price']; // assuming price already includes tax; adjust if needed

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<style>
/* ── Checkout Page Layout ─────────────────────────────── */
.ck-page {
    background: #f4f6fb;
    min-height: 100vh;
    padding: 2.5rem 0 4rem;
}

/* ── Left: Order Summary ──────────────────────────────── */
.ck-summary {
    background: linear-gradient(160deg, #1A1A2E 0%, #0f3460 100%);
    border-radius: 1.75rem;
    padding: 2.25rem 2rem;
    color: #fff;
    position: sticky;
    top: 90px;
    box-shadow: 0 20px 60px rgba(26,26,46,.35);
}
.ck-plan-badge {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #FF6B35;
    background: rgba(255,107,53,.15);
    border: 1px solid rgba(255,107,53,.3);
    border-radius: 100px;
    padding: .3rem .85rem;
    margin-bottom: 1rem;
}
.ck-plan-name {
    font-size: 1.8rem;
    font-weight: 900;
    line-height: 1.15;
    margin-bottom: .25rem;
}
.ck-plan-duration {
    font-size: .88rem;
    color: rgba(255,255,255,.6);
    margin-bottom: 1.75rem;
}
.ck-divider {
    border: none;
    border-top: 1px solid rgba(255,255,255,.1);
    margin: 1.5rem 0;
}
.ck-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: .85rem;
    font-size: .9rem;
}
.ck-line-label { color: rgba(255,255,255,.65); }
.ck-line-value { font-weight: 600; }
.ck-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255,107,53,.12);
    border: 1px solid rgba(255,107,53,.25);
    border-radius: 1rem;
    padding: 1rem 1.25rem;
    margin-top: 1rem;
}
.ck-total-label { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: rgba(255,255,255,.7); }
.ck-total-amount { font-size: 1.9rem; font-weight: 900; color: #fff; }

/* ── Features list ────────────────────────────────────── */
.ck-feature { display: flex; align-items: center; gap: .65rem; font-size: .85rem; color: rgba(255,255,255,.75); margin-bottom: .55rem; }
.ck-feature i { color: #22c55e; font-size: .8rem; flex-shrink: 0; }

/* ── Right: Payment Panel ─────────────────────────────── */
.ck-panel {
    background: #fff;
    border-radius: 1.75rem;
    box-shadow: 0 8px 40px rgba(0,0,0,.07);
    overflow: hidden;
}
.ck-panel-header {
    background: linear-gradient(135deg, #FF6B35, #ff8c5a);
    padding: 1.5rem 2rem;
    color: #fff;
}
.ck-panel-header h5 { font-size: 1.1rem; font-weight: 800; margin: 0; }
.ck-panel-header p  { font-size: .82rem; opacity: .85; margin: .25rem 0 0; }
.ck-panel-body { padding: 2rem; }

/* ── Account chip ─────────────────────────────────────── */
.ck-account-chip {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: #f8f9fc;
    border: 1.5px solid #eef0f7;
    border-radius: 1rem;
    padding: .9rem 1.1rem;
    margin-bottom: 1.75rem;
}
.ck-account-avatar {
    width: 46px; height: 46px;
    border-radius: 50%;
    background: linear-gradient(135deg, #FF6B35, #ff8c5a);
    color: #fff;
    font-size: 1.2rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ck-account-name  { font-weight: 700; font-size: .92rem; color: #1a1a2e; }
.ck-account-email { font-size: .78rem; color: #9ca3af; }

/* ── Section label ────────────────────────────────────── */
.ck-section-label {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #9ca3af;
    margin-bottom: .9rem;
}

/* ── Payment method cards ─────────────────────────────── */
.pay-method-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: .6rem;
    margin-bottom: 1.5rem;
}
.pay-method-chip {
    display: flex;
    align-items: center;
    gap: .6rem;
    border: 2px solid #eef0f7;
    border-radius: .85rem;
    padding: .65rem .9rem;
    font-size: .82rem;
    font-weight: 600;
    color: #374151;
    background: #fafafa;
}
.pay-method-chip i { font-size: 1rem; }
.pay-method-chip.upi   i { color: #5f0a87; }
.pay-method-chip.card  i { color: #1d4ed8; }
.pay-method-chip.net   i { color: #0891b2; }
.pay-method-chip.wallet i { color: #d97706; }

/* ── Pay button ───────────────────────────────────────── */
.ck-pay-btn {
    background: linear-gradient(135deg, #FF6B35, #ff8c5a);
    color: #fff;
    border: none;
    border-radius: 100px;
    width: 100%;
    padding: 1rem;
    font-size: 1.05rem;
    font-weight: 800;
    cursor: pointer;
    transition: all .25s;
    box-shadow: 0 8px 24px rgba(255,107,53,.4);
    letter-spacing: .02em;
}
.ck-pay-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(255,107,53,.5);
}
.ck-pay-btn:disabled { background: #e5e7eb; color: #9ca3af; box-shadow: none; cursor: not-allowed; }

/* ── Trust strip ──────────────────────────────────────── */
.ck-trust-strip {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-top: 1.25rem;
    font-size: .76rem;
    color: #9ca3af;
    font-weight: 600;
}
.ck-trust-item { display: flex; align-items: center; gap: .4rem; }
.ck-trust-item i { font-size: .9rem; }

/* ── Progress steps (top) ─────────────────────────────── */
.ck-breadcrumb {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    font-size: .8rem;
    color: #9ca3af;
    font-weight: 600;
    margin-bottom: 2rem;
}
.ck-breadcrumb .done { color: #22c55e; }
.ck-breadcrumb .current { color: #FF6B35; }
.ck-breadcrumb-sep { color: #d1d5db; }

/* ── Razorpay badge ───────────────────────────────────── */
.rzp-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    border: 1px solid #eef0f7;
    border-radius: .75rem;
    padding: .6rem 1rem;
    background: #fafafa;
    font-size: .76rem;
    color: #6b7280;
    font-weight: 600;
    margin-bottom: 1.25rem;
}

/* ── Promo field ──────────────────────────────────────── */
.promo-row { display: flex; gap: .5rem; margin-bottom: 1.5rem; }
.promo-input {
    flex: 1;
    border: 2px solid #eef0f7;
    border-radius: .75rem;
    padding: .65rem 1rem;
    font-size: .88rem;
    outline: none;
    transition: border-color .2s;
}
.promo-input:focus { border-color: #FF6B35; }
.promo-apply-btn {
    background: #1a1a2e;
    color: #fff;
    border: none;
    border-radius: .75rem;
    padding: .65rem 1.25rem;
    font-size: .85rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s;
}
.promo-apply-btn:hover { background: #FF6B35; }

@media(max-width: 991.98px) {
    .ck-summary { position: static; margin-bottom: 1.5rem; }
}
@media(max-width: 576px) {
    .pay-method-grid { grid-template-columns: 1fr 1fr; }
    .ck-plan-name { font-size: 1.4rem; }
    .ck-panel-body { padding: 1.25rem; }
}
</style>

<div class="ck-page">
    <div class="container" style="max-width:1000px;">

        <!-- Breadcrumb steps -->
        <div class="ck-breadcrumb">
            <span class="done"><i class="fa-solid fa-check-circle me-1"></i>Choose Plan</span>
            <span class="ck-breadcrumb-sep">›</span>
            <span class="current"><i class="fa-solid fa-circle-dot me-1"></i>Checkout</span>
            <span class="ck-breadcrumb-sep">›</span>
            <span>Confirmation</span>
        </div>

        <div class="row g-4 align-items-start">

            <!-- LEFT: Order Summary -->
            <div class="col-lg-5">
                <div class="ck-summary">
                    <div class="ck-plan-badge">
                        <i class="fa-solid fa-crown"></i> Membership Plan
                    </div>
                    <div class="ck-plan-name"><?= htmlspecialchars($plan['plan_name']) ?></div>
                    <div class="ck-plan-duration">
                        <i class="fa-regular fa-calendar me-1"></i>
                        <?= $plan['duration_days'] ?> Days Access
                    </div>

                    <?php if (!empty($features)): ?>
                    <div class="mb-3">
                        <?php foreach ($features as $feat): ?>
                        <div class="ck-feature">
                            <i class="fa-solid fa-circle-check"></i>
                            <?= htmlspecialchars($feat) ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($plan['trainer_sessions'] > 0): ?>
                        <div class="ck-feature">
                            <i class="fa-solid fa-circle-check"></i>
                            <?= $plan['trainer_sessions'] ?> Personal Trainer Sessions
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <hr class="ck-divider">

                    <div class="ck-line">
                        <span class="ck-line-label">Plan Price</span>
                        <span class="ck-line-value">₹<?= number_format($plan['price'], 2) ?></span>
                    </div>
                    <?php if ($plan['trainer_sessions'] > 0): ?>
                    <div class="ck-line">
                        <span class="ck-line-label">Trainer Sessions</span>
                        <span class="ck-line-value"><?= $plan['trainer_sessions'] ?> included</span>
                    </div>
                    <?php endif; ?>
                    <div class="ck-line">
                        <span class="ck-line-label">Taxes & Fees</span>
                        <span class="ck-line-value" style="color:rgba(255,255,255,.5);">Included</span>
                    </div>

                    <div class="ck-total-row">
                        <div>
                            <div class="ck-total-label">Total Amount</div>
                            <div style="font-size:.72rem;color:rgba(255,255,255,.5);">One-time payment</div>
                        </div>
                        <div class="ck-total-amount">₹<?= number_format($plan['price'], 2) ?></div>
                    </div>

                    <div class="mt-4 d-flex align-items-center gap-2" style="font-size:.75rem;color:rgba(255,255,255,.45);">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>7-day refund policy applies. Contact support for details.</span>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Payment Panel -->
            <div class="col-lg-7">
                <div class="ck-panel">
                    <div class="ck-panel-header">
                        <h5><i class="fa-solid fa-lock me-2"></i>Secure Checkout</h5>
                        <p>Complete your payment to activate your membership instantly</p>
                    </div>
                    <div class="ck-panel-body">

                        <!-- Account info -->
                        <div class="ck-section-label">Billing Account</div>
                        <div class="ck-account-chip">
                            <div class="ck-account-avatar">
                                <?= strtoupper(substr($_SESSION['full_name'] ?? 'M', 0, 1)) ?>
                            </div>
                            <div>
                                <div class="ck-account-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Member') ?></div>
                                <div class="ck-account-email"><?= htmlspecialchars($userProfile['email'] ?? $_SESSION['email'] ?? 'member@example.com') ?></div>
                            </div>
                            <div class="ms-auto">
                                <span class="badge rounded-pill" style="background:rgba(34,197,94,.12);color:#16a34a;font-size:.68rem;font-weight:700;padding:.35rem .75rem;">
                                    <i class="fa-solid fa-circle me-1" style="font-size:.45rem;vertical-align:middle;"></i>Verified
                                </span>
                            </div>
                        </div>

                        <!-- Accepted methods -->
                        <div class="ck-section-label">Accepted Payment Methods</div>
                        <div class="pay-method-grid">
                            <div class="pay-method-chip upi"><i class="fa-solid fa-mobile-screen-button"></i> UPI / QR</div>
                            <div class="pay-method-chip card"><i class="fa-solid fa-credit-card"></i> Cards</div>
                            <div class="pay-method-chip net"><i class="fa-solid fa-building-columns"></i> Net Banking</div>
                            <div class="pay-method-chip wallet"><i class="fa-solid fa-wallet"></i> Wallets</div>
                        </div>

                        <!-- Promo code -->
                        <div class="ck-section-label">Promo Code</div>
                        <div class="promo-row">
                            <input type="text" class="promo-input" id="promoCode" placeholder="Enter promo or referral code">
                            <button class="promo-apply-btn" type="button">Apply</button>
                        </div>

                        <!-- Razorpay badge -->
                        <div class="rzp-badge">
                            <i class="fa-solid fa-shield-halved" style="color:#22c55e;"></i>
                            Payments powered by <strong style="color:#1a1a2e;margin-left:.25rem;">Razorpay</strong>
                            <span style="margin:0 .5rem;color:#e5e7eb;">·</span>
                            <span style="color:#9ca3af;">256-bit SSL Encrypted</span>
                        </div>

                        <!-- Pay button -->
                        <button id="payBtn" class="ck-pay-btn">
                            <i class="fa-solid fa-lock me-2"></i>
                            Pay ₹<?= number_format($plan['price'], 2) ?> Securely
                        </button>

                        <!-- Trust strip -->
                        <div class="ck-trust-strip">
                            <div class="ck-trust-item">
                                <i class="fa-solid fa-rotate-left" style="color:#22c55e;"></i> 7-day refund
                            </div>
                            <div class="ck-trust-item">
                                <i class="fa-solid fa-shield-halved" style="color:#3b82f6;"></i> Secure payment
                            </div>
                            <div class="ck-trust-item">
                                <i class="fa-solid fa-bolt" style="color:#f59e0b;"></i> Instant activation
                            </div>
                        </div>

                        <!-- Hidden verify form -->
                        <form id="verifyForm" action="payment/verify.php" method="POST" style="display:none;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
                            <input type="hidden" name="razorpay_order_id"   id="razorpay_order_id">
                            <input type="hidden" name="razorpay_signature"  id="razorpay_signature">
                        </form>
                    </div>
                </div>

                <!-- Back link -->
                <div class="text-center mt-3">
                    <a href="plans.php" class="text-muted" style="font-size:.82rem;text-decoration:none;">
                        <i class="fa-solid fa-arrow-left me-1"></i>Back to Plans
                    </a>
                </div>
            </div>

        </div><!-- /.row -->
    </div><!-- /.container -->
</div><!-- /.ck-page -->

<!-- Razorpay SDK -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('payBtn').onclick = function(e) {
    e.preventDefault();
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Initiating secure payment…';

    fetch('payment/create-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'plan_id=<?= $plan['plan_id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            if (data.mock_mode) {
                document.getElementById('razorpay_payment_id').value = 'pay_mock_' + Date.now();
                document.getElementById('razorpay_order_id').value   = data.order_id;
                document.getElementById('razorpay_signature').value  = 'mock_signature';
                document.getElementById('verifyForm').submit();
                return;
            }
            const options = {
                key:         data.key,
                amount:      data.amount,
                currency:    'INR',
                name:        'FITNESS DESTINATION',
                description: '<?= htmlspecialchars($plan['plan_name']) ?> Membership',
                image:       'https://placehold.co/150x150/FF6B35/FFF?text=Gym',
                order_id:    data.order_id,
                handler: function(response) {
                    document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                    document.getElementById('razorpay_order_id').value   = response.razorpay_order_id;
                    document.getElementById('razorpay_signature').value  = response.razorpay_signature;
                    document.getElementById('verifyForm').submit();
                },
                prefill: {
                    name:  '<?= htmlspecialchars($_SESSION['full_name']) ?>',
                    email: '<?= htmlspecialchars($userProfile['email'] ?? '') ?>',
                    contact: '<?= htmlspecialchars($userProfile['phone'] ?? '') ?>'
                },
                theme:  { color: '#FF6B35' },
                modal: {
                    ondismiss: function() {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-lock me-2"></i>Pay ₹<?= number_format($plan['price'], 2) ?> Securely';
                    }
                }
            };
            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function(response) {
                showAlert('Payment failed: ' + response.error.description, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-lock me-2"></i>Pay ₹<?= number_format($plan['price'], 2) ?> Securely';
            });
            rzp.open();
        } else {
            showAlert('Could not initialise payment. ' + (data.message || 'Please try again.'), 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-lock me-2"></i>Pay ₹<?= number_format($plan['price'], 2) ?> Securely';
        }
    })
    .catch(() => {
        showAlert('Connection error. Please check your network and retry.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-lock me-2"></i>Pay ₹<?= number_format($plan['price'], 2) ?> Securely';
    });
};

function showAlert(msg, type) {
    const div = document.createElement('div');
    div.style.cssText = `position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;background:${type==='error'?'#ef4444':'#22c55e'};color:#fff;padding:.9rem 1.4rem;border-radius:1rem;font-weight:600;font-size:.9rem;box-shadow:0 8px 24px rgba(0,0,0,.2);animation:slideUp .3s ease;`;
    div.innerHTML = `<i class="fa-solid fa-${type==='error'?'circle-xmark':'circle-check'} me-2"></i>${msg}`;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 4000);
}
</script>

<?php require_once 'includes/footer.php'; ?>
