<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

$pageTitle = 'Gallery Manager';
$success   = '';
$error     = '';

// ── Resolve a stored file_path to a public <img> src ─────────────────────
function gallery_img_src(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return SITE_URL . '/' . ltrim($path, '/');
}

// ── Handle POST actions (Upload, Edit, Delete, Toggle) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Upload ────────────────────────────────────────────────────────
        if ($action === 'upload' && isset($_FILES['gallery_image'])) {
            $file     = $_FILES['gallery_image'];
            $title    = trim($_POST['title']    ?? 'Gallery Image');
            $category = trim($_POST['category'] ?? '');
            $altText  = trim($_POST['alt_text'] ?? $title);

            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Upload error. Please try again.';
            } elseif (!in_array($file['type'], $allowedTypes)) {
                $error = 'Only JPG, PNG, WebP, or GIF images are allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Image must be under 5 MB.';
            } else {
                $filename  = 'gallery_' . uniqid() . '.' . $ext;
                $uploadDir = UPLOAD_PATH . 'gallery/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $destPath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $relPath   = 'uploads/gallery/' . $filename;
                    $sortOrder = (int) $pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM gallery")->fetchColumn();

                    $pdo->prepare("
                        INSERT INTO gallery (title, file_path, category, alt_text, sort_order, is_active)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ")->execute([$title, $relPath, $category ?: null, $altText, $sortOrder]);

                    $success = 'Image uploaded successfully.';
                } else {
                    $error = 'Upload failed. Check that uploads/gallery/ folder is writable.';
                }
            }

        // ── Edit ──────────────────────────────────────────────────────────
        } elseif ($action === 'edit' && isset($_POST['image_id'])) {
            $imgId     = (int)$_POST['image_id'];
            $title     = trim($_POST['title'] ?? '');
            $category  = trim($_POST['category'] ?? '');
            $altText   = trim($_POST['alt_text'] ?? $title);
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            
            // Check if a new file was uploaded
            $newRelPath = null;
            if (isset($_FILES['gallery_image']) && $_FILES['gallery_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['gallery_image'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($file['type'], $allowedTypes)) {
                    $error = 'Only JPG, PNG, WebP, or GIF images are allowed.';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $error = 'Image must be under 5 MB.';
                } else {
                    $filename  = 'gallery_' . uniqid() . '.' . $ext;
                    $uploadDir = UPLOAD_PATH . 'gallery/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $destPath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $newRelPath = 'uploads/gallery/' . $filename;
                        
                        // Delete old file if it was a local file
                        $rowStmt = $pdo->prepare("SELECT file_path FROM gallery WHERE image_id = ?");
                        $rowStmt->execute([$imgId]);
                        $oldRow = $rowStmt->fetch();
                        if ($oldRow) {
                            $oldFp = trim($oldRow['file_path']);
                            if ($oldFp !== '' && !preg_match('#^https?://#i', $oldFp)) {
                                $localFile = rtrim(dirname(UPLOAD_PATH), '/') . '/' . ltrim($oldFp, '/');
                                if (file_exists($localFile)) @unlink($localFile);
                            }
                        }
                    } else {
                        $error = 'Failed to upload the new image file.';
                    }
                }
            }

            if (!$error) {
                if ($newRelPath !== null) {
                    $pdo->prepare("
                        UPDATE gallery 
                        SET title = ?, category = ?, alt_text = ?, sort_order = ?, file_path = ?
                        WHERE image_id = ?
                    ")->execute([$title, $category ?: null, $altText, $sortOrder, $newRelPath, $imgId]);
                } else {
                    $pdo->prepare("
                        UPDATE gallery 
                        SET title = ?, category = ?, alt_text = ?, sort_order = ?
                        WHERE image_id = ?
                    ")->execute([$title, $category ?: null, $altText, $sortOrder, $imgId]);
                }
                $success = 'Image updated successfully.';
            }

        // ── Delete ────────────────────────────────────────────────────────
        } elseif ($action === 'delete' && isset($_POST['image_id'])) {
            $imgId  = (int) $_POST['image_id'];
            $rowStmt = $pdo->prepare("SELECT file_path FROM gallery WHERE image_id = ?");
            $rowStmt->execute([$imgId]);
            $row = $rowStmt->fetch();
            if ($row) {
                $fp = trim($row['file_path']);
                if ($fp !== '' && !preg_match('#^https?://#i', $fp)) {
                    $localFile = rtrim(dirname(UPLOAD_PATH), '/') . '/' . ltrim($fp, '/');
                    if (file_exists($localFile)) @unlink($localFile);
                }
                $pdo->prepare("DELETE FROM gallery WHERE image_id = ?")->execute([$imgId]);
                $success = 'Image deleted.';
            }

        // ── Toggle visibility ─────────────────────────────────────────────
        } elseif ($action === 'toggle' && isset($_POST['image_id'])) {
            $pdo->prepare("UPDATE gallery SET is_active = NOT is_active WHERE image_id = ?")
                ->execute([(int) $_POST['image_id']]);
            $success = 'Visibility updated.';
        }
    }
}

// ── Search, Filter & Sort Logic ───────────────────────────────────────────
$searchQuery = trim($_GET['search'] ?? '');
$filterCat   = trim($_GET['category'] ?? '');
$filterVis   = trim($_GET['visibility'] ?? '');
$sortOption  = trim($_GET['sort'] ?? 'order_asc');

$where = [];
$params = [];

if ($searchQuery !== '') {
    $where[] = "(title LIKE ? OR alt_text LIKE ? OR category LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}
if ($filterCat !== '') {
    $where[] = "category = ?";
    $params[] = $filterCat;
}
if ($filterVis !== '') {
    $where[] = "is_active = ?";
    $params[] = ($filterVis === 'visible' ? 1 : 0);
}

$whereClause = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";

$orderBy = "ORDER BY sort_order ASC, created_at DESC";
switch ($sortOption) {
    case 'order_desc': $orderBy = "ORDER BY sort_order DESC, created_at DESC"; break;
    case 'newest':     $orderBy = "ORDER BY created_at DESC, sort_order ASC"; break;
    case 'oldest':     $orderBy = "ORDER BY created_at ASC, sort_order ASC"; break;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM gallery $whereClause $orderBy");
    $stmt->execute($params);
    $images = $stmt->fetchAll();
} catch (PDOException $e) {
    $images = [];
}

// Calculate total stats
$totalStats = $pdo->query("SELECT COUNT(*) as total, SUM(is_active) as visible FROM gallery")->fetch();
$totalImages = (int)$totalStats['total'];
$visibleCount = (int)$totalStats['visible'];
$hiddenCount = $totalImages - $visibleCount;

require_once 'includes/admin_header.php';
?>

<!-- Premium Gallery CSS -->
<style>
:root {
    --card-radius: 1.25rem;
    --transition-smooth: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    --primary-gradient: linear-gradient(135deg, #FF6B35 0%, #ff8c61 100%);
}

/* Page Header */
.gallery-header-title { font-weight: 800; letter-spacing: -0.5px; color: #1a1a2e; }
.btn-premium {
    background: var(--primary-gradient);
    border: none; color: #fff; font-weight: 600;
    box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
    transition: var(--transition-smooth);
}
.btn-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 107, 53, 0.4);
    color: #fff;
}

/* Premium Filter Bar */
.filter-bar {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(0,0,0,0.05);
    border-radius: 100px;
    padding: 0.5rem;
    box-shadow: 0 8px 32px rgba(0,0,0,0.03);
    transition: var(--transition-smooth);
}
.filter-bar:focus-within {
    border-color: #FF6B35;
    box-shadow: 0 8px 32px rgba(255, 107, 53, 0.1);
    background: #fff;
}
.filter-input, .filter-select {
    border: none !important;
    background: transparent !important;
    font-size: 0.9rem;
    font-weight: 500;
    color: #4a4a68;
    box-shadow: none !important;
}
.filter-input::placeholder { color: #a0a0b5; }
.filter-input:focus, .filter-select:focus { outline: none; }
.filter-divider { width: 1px; height: 24px; background: #e2e2ea; margin: 0 10px; }
.filter-btn-apply {
    background: #1a1a2e; color: #fff;
    border-radius: 100px; font-weight: 600; font-size: 0.85rem;
    padding: 0.5rem 1.25rem; transition: var(--transition-smooth);
}
.filter-btn-apply:hover { background: #2a2a4a; color: #fff; transform: scale(1.05); }

/* Premium Gallery Card */
.gallery-card {
    background: #fff;
    border-radius: var(--card-radius);
    overflow: hidden;
    position: relative;
    box-shadow: 0 10px 30px rgba(0,0,0,0.04);
    transition: var(--transition-smooth);
    border: 1px solid rgba(0,0,0,0.02);
    height: 100%;
}
.gallery-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
}
.gallery-card--hidden { opacity: 0.6; filter: grayscale(50%); }

.gallery-card__img-container {
    position: relative;
    width: 100%;
    padding-top: 75%; /* 4:3 Aspect Ratio */
    overflow: hidden;
    background: #f8f9fa;
}
.gallery-card__img {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%; object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.gallery-card:hover .gallery-card__img { transform: scale(1.08); }

/* Hover Overlay & Action Buttons */
.gallery-card__overlay {
    position: absolute; inset: 0;
    background: rgba(26, 26, 46, 0.7);
    backdrop-filter: blur(4px);
    opacity: 0;
    display: flex; align-items: center; justify-content: center; gap: 1rem;
    transition: opacity 0.3s ease;
    z-index: 10;
}
.gallery-card:hover .gallery-card__overlay { opacity: 1; }

.action-btn {
    width: 45px; height: 45px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    color: #fff; border: 1px solid rgba(255,255,255,0.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    transform: translateY(20px) scale(0.9);
    transition: var(--transition-smooth);
    padding: 0;
}
.gallery-card:hover .action-btn { transform: translateY(0) scale(1); }
.action-btn:hover {
    background: #fff; color: #1a1a2e;
    transform: scale(1.15) !important;
}
.action-btn.btn-delete:hover { background: #dc3545; color: #fff; border-color: #dc3545; }

/* Status Badges */
.gallery-badges {
    position: absolute; top: 12px; left: 12px; right: 12px;
    display: flex; justify-content: space-between; z-index: 5;
}
.badge-glass {
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(5px);
    color: #1a1a2e; font-weight: 700; font-size: 0.7rem;
    padding: 6px 12px; border-radius: 100px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.badge-status {
    width: 10px; height: 10px; border-radius: 50%;
    display: inline-block; box-shadow: 0 0 0 2px #fff;
}
.badge-status.active { background: #28a745; }
.badge-status.hidden { background: #dc3545; }

/* Card Info Area */
.gallery-card__info { padding: 1.25rem; }
.gallery-card__title {
    font-weight: 700; font-size: 1.05rem; color: #1a1a2e;
    margin: 0 0 0.25rem 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.gallery-card__meta { font-size: 0.8rem; color: #888; margin: 0; font-weight: 500; }

/* Drag & Drop Upload Zone */
.upload-zone {
    border: 2px dashed #d0d0df; border-radius: 1rem;
    padding: 3rem 2rem; text-align: center;
    background: #fbfbfc; transition: var(--transition-smooth);
    position: relative; overflow: hidden;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: #FF6B35; background: rgba(255, 107, 53, 0.05);
}
.upload-zone.has-image { border-style: solid; padding: 0; background: #000; height: 250px; display: flex; align-items: center; }
.upload-zone__preview { width: 100%; height: 100%; object-fit: contain; }
.upload-zone__icon { font-size: 3rem; color: #d0d0df; margin-bottom: 1rem; transition: var(--transition-smooth); }
.upload-zone:hover .upload-zone__icon { color: #FF6B35; transform: translateY(-5px); }
</style>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="gallery-header-title m-0">Gallery Manager</h3>
        <p class="text-muted small mb-0 mt-1">
            <span class="fw-bold text-dark"><?= $totalImages ?> total</span> &bull; 
            <span class="text-success fw-semibold"><?= $visibleCount ?> visible</span> &bull; 
            <span class="text-danger fw-semibold"><?= $hiddenCount ?> hidden</span>
        </p>
    </div>
    <button class="btn btn-premium rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="fa-solid fa-plus me-2"></i>New Image
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4 border-0 shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success rounded-4 border-0 shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Floating Filter Bar -->
<div class="filter-bar mb-5 d-none d-lg-block">
    <form method="GET" action="gallery.php" class="d-flex align-items-center m-0 w-100">
        <i class="fa-solid fa-search text-muted ms-3 me-2"></i>
        <input type="text" name="search" class="form-control filter-input flex-grow-1" placeholder="Search images..." value="<?= htmlspecialchars($searchQuery) ?>">
        
        <div class="filter-divider"></div>
        <select name="category" class="form-select filter-select w-auto" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <option value="gym" <?= $filterCat === 'gym' ? 'selected' : '' ?>>Gym Facility</option>
            <option value="equipment" <?= $filterCat === 'equipment' ? 'selected' : '' ?>>Equipment</option>
            <option value="classes" <?= $filterCat === 'classes' ? 'selected' : '' ?>>Classes</option>
            <option value="trainers" <?= $filterCat === 'trainers' ? 'selected' : '' ?>>Trainers</option>
        </select>

        <div class="filter-divider"></div>
        <select name="visibility" class="form-select filter-select w-auto" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="visible" <?= $filterVis === 'visible' ? 'selected' : '' ?>>Visible</option>
            <option value="hidden" <?= $filterVis === 'hidden' ? 'selected' : '' ?>>Hidden</option>
        </select>

        <div class="filter-divider"></div>
        <select name="sort" class="form-select filter-select w-auto" onchange="this.form.submit()">
            <option value="order_asc" <?= $sortOption === 'order_asc' ? 'selected' : '' ?>>Order (1 ➜ 9)</option>
            <option value="newest" <?= $sortOption === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= $sortOption === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
        </select>

        <div class="ms-3 d-flex gap-2">
            <?php if ($searchQuery || $filterCat || $filterVis || $sortOption !== 'order_asc'): ?>
                <a href="gallery.php" class="btn btn-light rounded-circle" style="width:36px;height:36px;padding:0;line-height:36px;text-align:center;" title="Clear Filters"><i class="fa-solid fa-times"></i></a>
            <?php endif; ?>
            <button type="submit" class="btn filter-btn-apply">Apply Filters</button>
        </div>
    </form>
</div>

<!-- Mobile Filter Button (Visible only on small screens) -->
<div class="d-lg-none mb-4">
    <button class="btn btn-outline-secondary w-100 rounded-pill py-2" type="button" data-bs-toggle="collapse" data-bs-target="#mobileFilters">
        <i class="fa-solid fa-sliders me-2"></i>Toggle Filters
    </button>
    <div class="collapse mt-3" id="mobileFilters">
        <div class="card card-body border-0 shadow-sm rounded-4">
            <form method="GET" action="gallery.php">
                <input type="text" name="search" class="form-control mb-2 rounded-pill" placeholder="Search..." value="<?= htmlspecialchars($searchQuery) ?>">
                <select name="category" class="form-select mb-2 rounded-pill" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <option value="gym" <?= $filterCat==='gym'?'selected':'' ?>>Gym Facility</option>
                    <option value="equipment" <?= $filterCat==='equipment'?'selected':'' ?>>Equipment</option>
                    <option value="classes" <?= $filterCat==='classes'?'selected':'' ?>>Classes</option>
                    <option value="trainers" <?= $filterCat==='trainers'?'selected':'' ?>>Trainers</option>
                </select>
                <select name="visibility" class="form-select mb-2 rounded-pill" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="visible" <?= $filterVis==='visible'?'selected':'' ?>>Visible</option>
                    <option value="hidden" <?= $filterVis==='hidden'?'selected':'' ?>>Hidden</option>
                </select>
                <select name="sort" class="form-select mb-3 rounded-pill" onchange="this.form.submit()">
                    <option value="order_asc" <?= $sortOption === 'order_asc' ? 'selected' : '' ?>>Order (1 ➜ 9)</option>
                    <option value="newest" <?= $sortOption === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sortOption === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                </select>
                <button type="submit" class="btn filter-btn-apply w-100 rounded-pill">Apply Filters</button>
            </form>
        </div>
    </div>
</div>

<!-- Gallery Grid -->
<div class="row g-4">
    <?php if (empty($images)): ?>
        <div class="col-12 text-center py-5">
            <img src="<?= SITE_URL ?>/assets/images/empty-gallery.svg" alt="Empty" style="width: 200px; opacity:0.5; margin-bottom: 1.5rem;" onerror="this.style.display='none'">
            <i class="fa-solid fa-images fa-4x text-muted mb-3 d-block" style="opacity:0.2;"></i>
            <h4 class="fw-bold text-dark mb-2">No Images Found</h4>
            <p class="text-muted">Your gallery view is empty. Adjust filters or upload a new image.</p>
        </div>
    <?php else: foreach ($images as $img):
        $src      = gallery_img_src($img['file_path']);
        $isActive = (bool) $img['is_active'];
        $imgJson  = htmlspecialchars(json_encode([
            'image_id'   => $img['image_id'],
            'title'      => $img['title'],
            'category'   => $img['category'],
            'alt_text'   => $img['alt_text'],
            'sort_order' => $img['sort_order'],
            'src'        => $src
        ]));
    ?>
    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
        <div class="gallery-card <?= $isActive ? '' : 'gallery-card--hidden' ?>">
            
            <div class="gallery-card__img-container">
                <img src="<?= htmlspecialchars($src) ?>" alt="Gallery Image" class="gallery-card__img" loading="lazy" onerror="this.onerror=null;this.src='<?= SITE_URL ?>/assets/images/no-image.png';">
                
                <div class="gallery-badges">
                    <span class="badge-glass text-uppercase"><i class="fa-solid fa-tag me-1 text-primary-custom"></i><?= htmlspecialchars($img['category'] ?: 'Uncategorized') ?></span>
                    <span class="badge-status <?= $isActive ? 'active' : 'hidden' ?>" title="<?= $isActive ? 'Visible to public' : 'Hidden from public' ?>"></span>
                </div>

                <!-- Hover Actions Overlay -->
                <div class="gallery-card__overlay">
                    <a href="<?= htmlspecialchars($src) ?>" target="_blank" class="action-btn" title="View Full Image">
                        <i class="fa-solid fa-expand"></i>
                    </a>
                    <button type="button" class="action-btn" title="Edit Image" onclick="openEditModal(<?= $imgJson ?>)">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <form method="POST" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="image_id" value="<?= $img['image_id'] ?>">
                        <button type="submit" class="action-btn" title="<?= $isActive ? 'Hide Image' : 'Show Image' ?>">
                            <i class="fa-solid <?= $isActive ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                        </button>
                    </form>
                    <form method="POST" class="m-0" onsubmit="return confirm('Permanently delete this image?');">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="image_id" value="<?= $img['image_id'] ?>">
                        <button type="submit" class="action-btn btn-delete" title="Delete Image">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="gallery-card__info">
                <h5 class="gallery-card__title" title="<?= htmlspecialchars($img['title']) ?>">
                    <?= htmlspecialchars($img['title']) ?>
                </h5>
                <p class="gallery-card__meta">
                    <i class="fa-solid fa-layer-group me-1"></i>Order #<?= $img['sort_order'] ?> &nbsp;&bull;&nbsp; <?= date('M d, Y', strtotime($img['created_at'])) ?>
                </p>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-light border-0 px-4 py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-cloud-arrow-up me-2 text-primary-custom"></i>Upload New Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="gallery.php?<?= http_build_query($_GET) ?>">
                <div class="modal-body px-4 py-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="upload">

                    <div class="row g-4">
                        <div class="col-md-5">
                            <div class="upload-zone" id="dropZone" onclick="document.getElementById('galleryFileInput').click()">
                                <img id="uploadPreviewImg" src="" alt="" class="upload-zone__preview d-none">
                                <div id="dropZonePlaceholder">
                                    <i class="fa-solid fa-file-image upload-zone__icon"></i>
                                    <h6 class="fw-bold mb-1">Click to browse</h6>
                                    <p class="small text-muted mb-0">JPG, PNG, WebP (Max 5MB)</p>
                                </div>
                            </div>
                            <input type="file" name="gallery_image" id="galleryFileInput" class="d-none" accept="image/*" required>
                        </div>

                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Image Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control rounded-3" placeholder="e.g., Heavy Weights Area" required maxlength="255">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Category</label>
                                <select name="category" class="form-select rounded-3">
                                    <option value="">— Select Category —</option>
                                    <option value="gym">Gym Facility</option>
                                    <option value="equipment">Equipment</option>
                                    <option value="classes">Classes</option>
                                    <option value="trainers">Trainers</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fw-bold small text-muted">Alt Text <span class="fw-normal">(Accessibility)</span></label>
                                <input type="text" name="alt_text" class="form-control rounded-3" placeholder="Describe the image..." maxlength="255">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-premium rounded-pill px-5">Upload Image</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-light border-0 px-4 py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen me-2 text-primary-custom"></i>Edit Image Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="gallery.php?<?= http_build_query($_GET) ?>">
                <div class="modal-body px-4 py-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="image_id" id="editImageId" value="">

                    <div class="row g-4">
                        <div class="col-md-5">
                            <div class="upload-zone has-image position-relative" id="editDropZone" onclick="document.getElementById('editFileInput').click()">
                                <img id="editPreviewImg" src="" alt="Current Image" class="upload-zone__preview">
                                <div class="position-absolute inset-0 d-flex align-items-center justify-content-center" style="background:rgba(0,0,0,0.5); opacity:0; transition:opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0">
                                    <span class="badge bg-light text-dark rounded-pill px-3 py-2"><i class="fa-solid fa-camera me-2"></i>Change Image</span>
                                </div>
                            </div>
                            <input type="file" name="gallery_image" id="editFileInput" class="d-none" accept="image/*">
                        </div>

                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Image Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="editTitle" class="form-control rounded-3" required maxlength="255">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-8">
                                    <label class="form-label fw-bold small text-muted">Category</label>
                                    <select name="category" id="editCategory" class="form-select rounded-3">
                                        <option value="">— Select —</option>
                                        <option value="gym">Gym Facility</option>
                                        <option value="equipment">Equipment</option>
                                        <option value="classes">Classes</option>
                                        <option value="trainers">Trainers</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-bold small text-muted">Order</label>
                                    <input type="number" name="sort_order" id="editSortOrder" class="form-control rounded-3" min="0">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fw-bold small text-muted">Alt Text <span class="fw-normal">(Accessibility)</span></label>
                                <input type="text" name="alt_text" id="editAltText" class="form-control rounded-3" maxlength="255">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom rounded-pill px-5">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// File Upload Preview Handlers
function setupImagePreview(inputId, previewId, placeholderId, dropZoneId) {
    document.getElementById(inputId).addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById(previewId).src = e.target.result;
            document.getElementById(previewId).classList.remove('d-none');
            if (placeholderId) document.getElementById(placeholderId).classList.add('d-none');
            document.getElementById(dropZoneId).classList.add('has-image');
        };
        reader.readAsDataURL(file);
    });
}
setupImagePreview('galleryFileInput', 'uploadPreviewImg', 'dropZonePlaceholder', 'dropZone');
setupImagePreview('editFileInput', 'editPreviewImg', null, 'editDropZone');

// Edit Modal Population
function openEditModal(imgData) {
    document.getElementById('editImageId').value = imgData.image_id;
    document.getElementById('editTitle').value = imgData.title;
    document.getElementById('editCategory').value = imgData.category || '';
    document.getElementById('editAltText').value = imgData.alt_text || '';
    document.getElementById('editSortOrder').value = imgData.sort_order;
    document.getElementById('editPreviewImg').src = imgData.src;
    document.getElementById('editFileInput').value = ''; // clear previous file

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
