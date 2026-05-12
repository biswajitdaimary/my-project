<?php
$pageTitle = 'Membership Plans';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<style>
/* Premium Pricing Cards Custom CSS */
.pricing-card {
    border-radius: 1.5rem;
    border: 1px solid #f0f0f5;
    background: #fff;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    z-index: 1;
}
.pricing-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.08) !important;
}

/* Popular Card Styling */
.pricing-card.popular {
    border: 2px solid #FF6B35;
    box-shadow: 0 15px 35px rgba(255, 107, 53, 0.15) !important;
    transform: scale(1.05);
    z-index: 2;
}
.pricing-card.popular:hover {
    transform: scale(1.05) translateY(-8px);
}
@media (max-width: 991.98px) {
    .pricing-card.popular {
        transform: scale(1);
    }
    .pricing-card.popular:hover {
        transform: translateY(-8px);
    }
}

/* Popular Ribbon */
.popular-ribbon {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #FF6B35;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 0.6rem 1.5rem;
    border-bottom-left-radius: 1.5rem;
    box-shadow: -2px 2px 10px rgba(255, 107, 53, 0.3);
    z-index: 10;
}

/* Price Typography */
.price-container {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}
.pricing-price {
    font-size: 3.5rem;
    font-weight: 800;
    color: #1a1a2e;
    line-height: 1;
    letter-spacing: -0.03em;
}
.pricing-currency {
    font-size: 1.5rem;
    color: #6c757d;
    font-weight: 600;
    margin-top: 0.4rem;
    margin-right: 0.2rem;
}
.pricing-duration {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
}

/* Features List */
.pricing-features li {
    padding: 0.75rem 0;
    border-bottom: 1px dashed #eaeaea;
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    color: #4a4a5a;
}
.pricing-features li:last-child {
    border-bottom: none;
}
.pricing-features li i {
    color: #FF6B35;
    background: rgba(255, 107, 53, 0.1);
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin-right: 0.75rem;
    font-size: 0.7rem;
    flex-shrink: 0;
}
.pricing-features li.trainer-feature i {
    color: #fff;
    background: #FF6B35;
}

/* Header gradient for popular card */
.popular .pricing-header {
    background: linear-gradient(135deg, rgba(255,107,53,0.06), rgba(255,107,53,0.0));
    margin: -1.5rem -1.5rem 1.5rem -1.5rem;
    padding: 2.5rem 1.5rem 0 1.5rem; /* Added top padding to clear ribbon */
    border-bottom: 1px solid rgba(255,107,53,0.1);
}
</style>

<section class="section-padding bg-light min-vh-100">
    <div class="container">
        <div class="section-title text-center" data-aos="fade-up">
            <span class="text-primary-custom fw-bold text-uppercase">Pricing</span>
            <h2 class="mb-3">Choose Your Membership Plan</h2>
            <p class="text-muted">Flexible options designed to help you reach your goals</p>
        </div>

        <div class="row mt-5 gy-4 justify-content-center">
            <?php
            try {
                $stmt = $pdo->query("SELECT * FROM membership_plans WHERE is_active = 1 ORDER BY price ASC");
                $plans = $stmt->fetchAll();
                $delay = 100;
                
                foreach($plans as $plan):
                    $features = json_decode($plan['features_json'], true);
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                <div class="pricing-card <?= $plan['is_popular'] ? 'popular' : 'shadow-sm' ?> p-4 h-100 d-flex flex-column">
                    <?php if($plan['is_popular']): ?>
                        <div class="popular-ribbon"><i class="fa-solid fa-star me-1"></i> Most Popular</div>
                    <?php endif; ?>
                    
                    <div class="pricing-header text-center mb-4 pb-2">
                        <h4 class="mb-2 fw-bold" style="color: <?= $plan['is_popular'] ? '#FF6B35' : '#1a1a2e' ?>; font-size: 1.5rem;"><?= htmlspecialchars($plan['plan_name']) ?></h4>
                        <p class="text-muted small mb-4 px-2" style="min-height: 40px;"><?= htmlspecialchars($plan['description']) ?></p>
                        
                        <div class="price-container">
                            <span class="pricing-currency">₹</span>
                            <span class="pricing-price"><?= number_format($plan['price'], 0) ?></span>
                        </div>
                        <div class="pricing-duration">for <?= $plan['duration_days'] ?> days</div>
                    </div>
                    
                    <div class="mb-4 flex-grow-1">
                        <ul class="list-unstyled pricing-features mb-0">
                            <?php 
                            $trainerFeatureShown = false;
                            if($features): 
                                foreach($features as $feature): 
                                    if (stripos($feature, 'trainer') !== false || stripos($feature, 'session') !== false) {
                                        $trainerFeatureShown = true;
                                        $isTrainerClass = 'trainer-feature fw-bold" style="color:#1a1a2e;';
                                        $icon = 'fa-bolt';
                                    } else {
                                        $isTrainerClass = '';
                                        $icon = 'fa-check';
                                    }
                            ?>
                                <li class="<?= $isTrainerClass ?>">
                                    <i class="fa-solid <?= $icon ?>"></i> 
                                    <span><?= htmlspecialchars($feature) ?></span>
                                </li>
                            <?php endforeach; endif; ?>
                            
                            <?php if(!$trainerFeatureShown && $plan['trainer_sessions'] > 0): ?>
                                <li class="trainer-feature fw-bold" style="color:#1a1a2e;">
                                    <i class="fa-solid fa-bolt"></i> 
                                    <span><?= $plan['trainer_sessions'] ?> Personal Trainer Sessions</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="mt-auto text-center pt-3 border-top" style="border-color: #eaeaea !important;">
                        <a href="checkout.php?plan_id=<?= $plan['plan_id'] ?>" class="btn <?= $plan['is_popular'] ? 'btn-primary-custom' : 'btn-outline-dark' ?> w-100 py-3 rounded-pill fw-bold text-uppercase" style="letter-spacing:0.05em; transition: all 0.2s;">
                            <?= $plan['is_popular'] ? 'Get Started Now' : 'Choose Plan' ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php 
                $delay += 100;
                endforeach; 
            } catch(Exception $e) {
                echo "<div class='alert alert-danger'>Could not load plans. Error: " . $e->getMessage() . "</div>";
            }
            ?>
        </div>
        
        <!-- FAQs Section placed here for cohesion -->
        <div class="row mt-5 pt-5 justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <h3 class="text-center mb-4">Frequently Asked Questions</h3>
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                        <h2 class="accordion-header" id="headingOne">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                            Can I upgrade my plan later?
                        </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            Yes, absolutely! You can upgrade your membership at any time from your user dashboard. The remaining value of your current plan will automatically be credited toward the upgraded plan.
                        </div>
                        </div>
                    </div>
                    <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                        <h2 class="accordion-header" id="headingTwo">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                            What payment methods do you accept?
                        </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            We accept all major credit/debit cards, UPI, and NetBanking through our secure Razorpay payment gateway. Walk-ins can also pay with cash at the front desk.
                        </div>
                        </div>
                    </div>
                    <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                        <h2 class="accordion-header" id="headingThree">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                            Are there any hidden fees?
                        </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            No, we believe in 100% transparency. The price you see on the plan is the final price. Taxes are already included unless expressly mentioned otherwise on the checkout page.
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
