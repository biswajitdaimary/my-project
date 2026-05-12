<?php
require_once '../config/config.php';
require_once 'includes/admin_header.php';

$success = '';
$error = '';

// Handle Plan Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security validation failed.";
    } else {
        $action = $_POST['action'];
        
        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['plan_name']);
            $price = trim($_POST['price']);
            $duration = trim($_POST['duration_days']);
            $sessions = trim($_POST['trainer_sessions']);
            $desc = trim($_POST['description']);
            $is_popular = isset($_POST['is_popular']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Handle Features Array
            $features = [];
            if (isset($_POST['features']) && is_array($_POST['features'])) {
                foreach ($_POST['features'] as $feature) {
                    $trimmedFeature = trim($feature);
                    if (!empty($trimmedFeature)) {
                        $features[] = $trimmedFeature;
                    }
                }
            }
            $features_json = !empty($features) ? json_encode($features) : null;
            
            if (empty($name) || !is_numeric($price) || !is_numeric($duration)) {
                $error = "Please fill all required fields correctly.";
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO membership_plans (plan_name, description, duration_days, price, trainer_sessions, features_json, is_popular, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $desc, $duration, $price, $sessions, $features_json, $is_popular, $is_active]);
                        $success = "Plan added successfully.";
                    } else if ($action === 'edit') {
                        $plan_id = $_POST['plan_id'];
                        $stmt = $pdo->prepare("UPDATE membership_plans SET plan_name=?, description=?, duration_days=?, price=?, trainer_sessions=?, features_json=?, is_popular=?, is_active=? WHERE plan_id=?");
                        $stmt->execute([$name, $desc, $duration, $price, $sessions, $features_json, $is_popular, $is_active, $plan_id]);
                        $success = "Plan updated successfully.";
                    }
                } catch(PDOException $e) {
                    $error = "Database Error.";
                }
            }
        } elseif ($action === 'delete') {
            try {
                $stmt = $pdo->prepare("DELETE FROM membership_plans WHERE plan_id = ?");
                $stmt->execute([$_POST['plan_id']]);
                $success = "Plan deleted successfully.";
            } catch(PDOException $e) { $error = "Cannot delete this plan as it is currently assigned to members."; }
        }
    }
}

// Fetch Plans
try {
    $stmt = $pdo->query("SELECT * FROM membership_plans ORDER BY price ASC");
    $plans = $stmt->fetchAll();
} catch(PDOException $e) { die("Database error."); }
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold m-0">Membership Plans</h3>
    <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#planModal" onclick="resetForm()"><i class="fa-solid fa-plus me-2"></i> Add New Plan</button>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row g-4">
    <?php foreach($plans as $p): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-10 h-100 <?= $p['is_active'] ? '' : 'opacity-50' ?>">
                <div class="card-body p-4 position-relative">
                    <?php if($p['is_popular']): ?>
                        <span class="position-absolute top-0 end-0 badge bg-warning text-dark m-3">Popular</span>
                    <?php endif; ?>
                    
                    <h5 class="fw-bold mb-1 text-primary-custom"><?= htmlspecialchars($p['plan_name']) ?></h5>
                    <h3 class="fw-bold mb-3">₹<?= number_format($p['price']) ?> <span class="fs-6 text-muted fw-normal">/ <?= $p['duration_days'] ?> Days</span></h3>
                    
                    <p class="text-muted small mb-3" style="min-height: 40px;"><?= htmlspecialchars($p['description']) ?></p>
                    
                    <?php 
                        $featuresList = [];
                        if (!empty($p['features_json'])) {
                            $decoded = json_decode($p['features_json'], true);
                            if (is_array($decoded)) {
                                $featuresList = $decoded;
                            }
                        }
                    ?>
                    <?php if(!empty($featuresList)): ?>
                    <ul class="list-unstyled small mb-4">
                        <?php foreach($featuresList as $feat): ?>
                            <li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i><?= htmlspecialchars($feat) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mb-4 pb-3 border-bottom">
                        <span class="small fw-bold"><i class="fa-solid fa-user-tie text-secondary me-2"></i> <?= $p['trainer_sessions'] ?> Sessions</span>
                        <span class="badge <?= $p['is_active'] ? 'bg-success' : 'bg-danger' ?>"><?= $p['is_active'] ? 'Active' : 'Hidden' ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button class="btn btn-sm btn-outline-dark" onclick='editPlan(<?= json_encode($p) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="POST" action="plans.php" class="m-0" onsubmit="return confirm('Are you sure you want to permanently delete this plan?');">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="plan_id" value="<?= $p['plan_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-10 border-0 shadow">
            <div class="modal-header bg-primary-custom text-white border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Add New Plan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="plans.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="plan_id" id="planId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Plan Name *</label>
                        <input type="text" class="form-control" name="plan_name" id="planName" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">Price (₹) *</label>
                            <input type="number" step="0.01" class="form-control" name="price" id="planPrice" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">Duration (Days) *</label>
                            <input type="number" class="form-control" name="duration_days" id="planDuration" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Trainer Sessions Included</label>
                        <input type="number" class="form-control" name="trainer_sessions" id="planSessions" value="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Description</label>
                        <textarea class="form-control" name="description" id="planDesc" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold mb-0">Plan Features</label>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0" onclick="addFeatureRow()"><i class="fa-solid fa-plus me-1"></i> Add Feature</button>
                        </div>
                        <div id="featuresListContainer">
                            <!-- Feature inputs will be added here -->
                        </div>
                    </div>
                    
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_popular" id="planPopular" value="1">
                        <label class="form-check-label small">Mark as Popular</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="planActive" value="1" checked>
                        <label class="form-check-label small">Is Active (Visible on site)</label>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom shadow-sm"><i class="fa-solid fa-save me-2"></i> Save Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function createFeatureInput(val = '') {
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 mb-2 feature-row';
    row.innerHTML = `
        <input type="text" class="form-control form-control-sm" name="features[]" value="${val.replace(/"/g, '&quot;')}" placeholder="e.g. 24/7 Access">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.feature-row').remove()"><i class="fa-solid fa-trash"></i></button>
    `;
    return row;
}

function addFeatureRow() {
    document.getElementById('featuresListContainer').appendChild(createFeatureInput());
}

function resetForm() {
    document.getElementById('modalTitle').innerText = 'Add New Plan';
    document.getElementById('formAction').value = 'add';
    document.getElementById('planId').value = '';
    document.getElementById('planName').value = '';
    document.getElementById('planPrice').value = '';
    document.getElementById('planDuration').value = '';
    document.getElementById('planSessions').value = '0';
    document.getElementById('planDesc').value = '';
    document.getElementById('planPopular').checked = false;
    document.getElementById('planActive').checked = true;
    
    document.getElementById('featuresListContainer').innerHTML = '';
    addFeatureRow(); // Add one empty row by default
}

function editPlan(plan) {
    document.getElementById('modalTitle').innerText = 'Edit Plan';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('planId').value = plan.plan_id;
    document.getElementById('planName').value = plan.plan_name;
    document.getElementById('planPrice').value = plan.price;
    document.getElementById('planDuration').value = plan.duration_days;
    document.getElementById('planSessions').value = plan.trainer_sessions;
    document.getElementById('planDesc').value = plan.description;
    document.getElementById('planPopular').checked = plan.is_popular == 1;
    document.getElementById('planActive').checked = plan.is_active == 1;
    
    const container = document.getElementById('featuresListContainer');
    container.innerHTML = '';
    if (plan.features_json) {
        try {
            const features = JSON.parse(plan.features_json);
            if (Array.isArray(features) && features.length > 0) {
                features.forEach(f => {
                    container.appendChild(createFeatureInput(f));
                });
            } else {
                addFeatureRow();
            }
        } catch(e) {
            addFeatureRow();
        }
    } else {
        addFeatureRow();
    }
    
    new bootstrap.Modal(document.getElementById('planModal')).show();
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
