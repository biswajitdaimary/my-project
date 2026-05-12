<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

$pageTitle = 'Manage Members';
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

// Toggle active/inactive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $uid = (int)$_POST['toggle_user'];
        $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?")->execute([$uid]);
        header("Location: members.php?search=".urlencode($search)."&page=$page");
        exit;
    }
}

$filter = $_GET['filter'] ?? 'all';

try {
    $where = "WHERE u.role = 'user'";
    $params = [];
    if ($search !== '') {
        $where .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Latest membership logic
    $memJoin = "
        LEFT JOIN (
            SELECT user_id, MAX(membership_id) as latest_id
            FROM user_memberships
            GROUP BY user_id
        ) latest_um ON u.user_id = latest_um.user_id
        LEFT JOIN user_memberships um ON latest_um.latest_id = um.membership_id
        LEFT JOIN membership_plans mp ON um.plan_id = mp.plan_id
    ";

    if ($filter === 'expiring_soon') {
        $where .= " AND um.status = 'active' AND um.end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($filter === 'expired') {
        $where .= " AND um.status = 'expired'";
    }

    $total = $pdo->prepare("SELECT COUNT(*) FROM users u $memJoin $where");
    $total->execute($params);
    $totalCount = $total->fetchColumn();
    $totalPages  = max(1, ceil($totalCount / $perPage));

    $stmt = $pdo->prepare("
        SELECT u.*,
               mp.plan_name,
               um.start_date, um.end_date, um.status AS mem_status,
               (SELECT COUNT(*) FROM trainer_bookings WHERE user_id = u.user_id) AS booking_count,
               (SELECT COALESCE(SUM(amount),0) FROM payments WHERE user_id = u.user_id AND status = 'success') AS total_spent
        FROM users u
        $memJoin
        $where
        ORDER BY u.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $members = $stmt->fetchAll();
} catch (PDOException $e) {
    $members = []; $totalPages = 1; $totalCount = 0;
}

require_once 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h3 class="fw-bold m-0">Members <span class="badge bg-secondary ms-2 fs-6"><?= number_format($totalCount) ?></span></h3>
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>" style="min-width:260px;">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <button class="btn btn-primary-custom"><i class="fa-solid fa-search"></i></button>
        <?php if($search): ?><a href="members.php?filter=<?= htmlspecialchars($filter) ?>" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
    </form>
</div>

<!-- Filters -->
<ul class="nav nav-pills mb-4" style="gap:0.5rem;">
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?> rounded-pill px-4 fw-bold" href="members.php?filter=all&search=<?= urlencode($search) ?>">All Members</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'expiring_soon' ? 'active bg-warning text-dark' : 'bg-light text-secondary' ?> rounded-pill px-4 fw-bold" href="members.php?filter=expiring_soon&search=<?= urlencode($search) ?>">
            <i class="fa-solid fa-clock text-warning me-1" style="<?= $filter === 'expiring_soon' ? 'color:#000 !important;' : '' ?>"></i> Expiring Soon
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'expired' ? 'active bg-danger text-white' : 'bg-light text-secondary' ?> rounded-pill px-4 fw-bold" href="members.php?filter=expired&search=<?= urlencode($search) ?>">
            <i class="fa-solid fa-ban text-danger me-1" style="<?= $filter === 'expired' ? 'color:#fff !important;' : '' ?>"></i> Expired
        </a>
    </li>
</ul>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Member</th>
                        <th>Member ID</th>
                        <th>Plan</th>
                        <th>Membership</th>
                        <th>Bookings</th>
                        <th>Total Spent</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($members)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No members found.</td></tr>
                    <?php else: foreach($members as $m): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-circle"><?= strtoupper(substr($m['full_name'],0,1)) ?></div>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($m['full_name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($m['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($m['custom_id'])): ?>
                                <span class="badge rounded-pill px-3 py-2 fw-bold"
                                      style="background:#ede9fe;color:#7c3aed;letter-spacing:1.5px;font-family:monospace;font-size:.75rem;">
                                    <i class="fa-solid fa-fingerprint me-1"></i><?= htmlspecialchars($m['custom_id']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $m['plan_name'] ? '<span class="badge bg-primary-subtle text-primary fw-semibold">'.htmlspecialchars($m['plan_name']).'</span>' : '<span class="text-muted small">None</span>' ?></td>
                        <td>
                            <?php if($m['mem_status'] === 'active'): 
                                $daysLeft = max(0, (new DateTime($m['end_date']))->diff(new DateTime())->days);
                                if ($daysLeft <= 7):
                            ?>
                                <span class="badge bg-warning text-dark border border-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Expiring in <?= $daysLeft ?>d</span><br>
                                <small class="text-muted">Expires <?= date('M d, Y', strtotime($m['end_date'])) ?></small>
                            <?php else: ?>
                                <span class="badge bg-success">Active</span><br>
                                <small class="text-muted">Expires <?= date('M d, Y', strtotime($m['end_date'])) ?></small>
                            <?php endif; ?>
                            <?php elseif($m['mem_status'] === 'expired'): ?>
                                <span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i>Expired</span><br>
                                <small class="text-muted">Ended <?= date('M d, Y', strtotime($m['end_date'])) ?></small>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="fw-bold"><?= $m['booking_count'] ?></span></td>
                        <td class="fw-bold text-success">₹<?= number_format($m['total_spent'], 0) ?></td>
                        <td class="text-muted small"><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                        <td>
                            <?php $active = $m['is_active'] ?? 1; ?>
                            <span class="badge <?= $active ? 'bg-success' : 'bg-danger' ?>">
                                <?= $active ? 'Active' : 'Blocked' ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <form method="POST" action="impersonate.php" class="d-inline me-1">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="id" value="<?= (int)$m['user_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Login as Member">
                                    <i class="fa-solid fa-user-secret"></i>
                                </button>
                            </form>

                            <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#memberModal"
                                data-name="<?= htmlspecialchars($m['full_name']) ?>"
                                data-email="<?= htmlspecialchars($m['email']) ?>"
                                data-plan="<?= htmlspecialchars($m['plan_name'] ?? 'No Plan') ?>"
                                data-status="<?= htmlspecialchars($m['mem_status'] ?? 'inactive') ?>"
                                data-spent="₹<?= number_format($m['total_spent'],0) ?>"
                                data-bookings="<?= $m['booking_count'] ?>"
                                data-joined="<?= date('M d, Y', strtotime($m['created_at'])) ?>"
                                title="View Details">
                                <i class="fa-solid fa-eye"></i>
                            </button>

                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="toggle_user" value="<?= $m['user_id'] ?>">
                                <button type="submit" class="btn btn-sm <?= ($m['is_active'] ?? 1) ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                    title="<?= ($m['is_active'] ?? 1) ? 'Block' : 'Unblock' ?>">
                                    <i class="fa-solid <?= ($m['is_active'] ?? 1) ? 'fa-ban' : 'fa-check' ?>"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if($totalPages > 1): ?>
        <div class="d-flex justify-content-center py-4 gap-1">
            <?php for($i=1; $i<=$totalPages; $i++): ?>
                <a href="?search=<?= urlencode($search) ?>&page=<?= $i ?>" class="btn btn-sm <?= $i == $page ? 'btn-primary-custom' : 'btn-outline-secondary' ?> rounded-circle" style="width:36px;height:36px;"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Member Profile Modal -->
<div class="modal fade" id="memberModal" tabindex="-1" aria-labelledby="memberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="memberModalLabel">Member Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="avatar-circle-lg mx-auto mb-2" id="modalAvatar"></div>
                    <h5 class="fw-bold mb-0" id="modalName"></h5>
                    <p class="text-muted small" id="modalEmail"></p>
                </div>
                <div class="row g-3">
                    <div class="col-6"><div class="stat-pill"><span class="label">Plan</span><span class="value" id="modalPlan"></span></div></div>
                    <div class="col-6"><div class="stat-pill"><span class="label">Membership</span><span class="value" id="modalStatus"></span></div></div>
                    <div class="col-6"><div class="stat-pill"><span class="label">Total Spent</span><span class="value" id="modalSpent"></span></div></div>
                    <div class="col-6"><div class="stat-pill"><span class="label">Bookings</span><span class="value" id="modalBookings"></span></div></div>
                    <div class="col-12"><div class="stat-pill"><span class="label">Member Since</span><span class="value" id="modalJoined"></span></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle { width:40px;height:40px;border-radius:50%;background:rgba(255,107,53,0.15);color:#FF6B35;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0; }
.avatar-circle-lg { width:72px;height:72px;border-radius:50%;background:rgba(255,107,53,0.15);color:#FF6B35;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:700; }
.stat-pill { background:#f8f9fa;border-radius:12px;padding:0.75rem 1rem;display:flex;flex-direction:column;gap:2px; }
.stat-pill .label { font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:#6c757d;font-weight:600; }
.stat-pill .value { font-weight:700;color:#1a1a2e; }
</style>
<script>
document.getElementById('memberModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('modalName').textContent     = btn.dataset.name;
    document.getElementById('modalEmail').textContent    = btn.dataset.email;
    document.getElementById('modalPlan').textContent     = btn.dataset.plan;
    document.getElementById('modalStatus').textContent   = btn.dataset.status;
    document.getElementById('modalSpent').textContent    = btn.dataset.spent;
    document.getElementById('modalBookings').textContent = btn.dataset.bookings;
    document.getElementById('modalJoined').textContent   = btn.dataset.joined;
    document.getElementById('modalAvatar').textContent   = btn.dataset.name.charAt(0).toUpperCase();
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
