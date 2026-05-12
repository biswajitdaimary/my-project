<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

$pageTitle = 'Blog Manager';
$success = ''; $error = '';
$editPost = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $title   = trim($_POST['title'] ?? '');
            $slug    = strtolower(preg_replace('/[^a-z0-9]+/','-', trim($_POST['slug'] ?? $title)));
            $excerpt = trim($_POST['excerpt'] ?? '');
            $content = $_POST['content'] ?? '';
            $catId   = (int)($_POST['category_id'] ?? 0) ?: null;
            $status  = $_POST['status'] === 'published' ? 'published' : 'draft';
            $postId  = (int)($_POST['post_id'] ?? 0);

            if (!$title) { $error = "Title is required."; }
            else {
                // Handle featured image upload
                $featuredImage = $_POST['existing_image'] ?? null;
                if (!empty($_FILES['featured_image']['name'])) {
                    $file = $_FILES['featured_image'];
                    if ($file['size'] < 5*1024*1024 && in_array($file['type'],['image/jpeg','image/png','image/webp'])) {
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $fname = 'blog_' . uniqid() . '.' . $ext;
                        if (!is_dir(UPLOAD_PATH.'blog/')) mkdir(UPLOAD_PATH.'blog/', 0755, true);
                        if (move_uploaded_file($file['tmp_name'], UPLOAD_PATH.'blog/'.$fname)) {
                            $featuredImage = 'uploads/blog/' . $fname;
                        }
                    }
                }

                try {
                    if ($postId) {
                        $pdo->prepare("UPDATE blog_posts SET title=?,slug=?,excerpt=?,content=?,category_id=?,status=?,featured_image=? WHERE post_id=?")
                            ->execute([$title,$slug,$excerpt,$content,$catId,$status,$featuredImage,$postId]);
                        $success = "Post updated successfully.";
                    } else {
                        $pdo->prepare("INSERT INTO blog_posts (title,slug,excerpt,content,category_id,status,featured_image,author_id) VALUES (?,?,?,?,?,?,?,?)")
                            ->execute([$title,$slug,$excerpt,$content,$catId,$status,$featuredImage,$_SESSION['user_id']]);
                        $success = "Post published successfully.";
                    }
                } catch(PDOException $e) { $error = "Could not save post: " . $e->getMessage(); }
            }
        } elseif ($action === 'delete' && isset($_POST['post_id'])) {
            $pdo->prepare("DELETE FROM blog_posts WHERE post_id = ?")->execute([(int)$_POST['post_id']]);
            $success = "Post deleted.";
        }
    }
}

// Load edit post
if (isset($_GET['edit'])) {
    $editPost = $pdo->prepare("SELECT * FROM blog_posts WHERE post_id = ?");
    $editPost->execute([(int)$_GET['edit']]);
    $editPost = $editPost->fetch();
}

try {
    $posts = $pdo->query("
        SELECT p.*, c.name AS cat_name
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.category_id
        ORDER BY p.created_at DESC
    ")->fetchAll();
    $categories = $pdo->query("SELECT * FROM blog_categories ORDER BY name ASC")->fetchAll();
} catch(PDOException $e) { $posts = []; $categories = []; }

require_once 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold m-0">Blog Manager</h3>
    <a href="blog.php" class="btn btn-primary-custom"><i class="fa-solid fa-plus me-2"></i>New Post</a>
</div>

<?php if($error): ?><div class="alert alert-danger rounded-4 border-0"><?= $error ?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success rounded-4 border-0"><?= $success ?></div><?php endif; ?>

<div class="row g-4">
    <!-- Editor -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><?= $editPost ? 'Edit Post' : 'New Post' ?></h5>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="save">
                    <?php if($editPost): ?>
                        <input type="hidden" name="post_id" value="<?= $editPost['post_id'] ?>">
                        <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editPost['featured_image'] ?? '') ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Title *</label>
                        <input type="text" name="title" class="form-control rounded-3" value="<?= htmlspecialchars($editPost['title'] ?? '') ?>" required id="postTitle">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">URL Slug</label>
                        <input type="text" name="slug" class="form-control rounded-3" value="<?= htmlspecialchars($editPost['slug'] ?? '') ?>" id="postSlug" placeholder="auto-generated from title">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Excerpt</label>
                        <textarea name="excerpt" class="form-control rounded-3" rows="2" placeholder="Short description shown in listings..."><?= htmlspecialchars($editPost['excerpt'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Content *</label>
                        <textarea name="content" id="blogContent" class="form-control rounded-3" rows="10" placeholder="Write your post content here..."><?= htmlspecialchars($editPost['content'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Category</label>
                            <select name="category_id" class="form-select rounded-3">
                                <option value="">— None —</option>
                                <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= ($editPost['category_id'] ?? '') == $cat['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Status</label>
                            <select name="status" class="form-select rounded-3">
                                <option value="draft" <?= ($editPost['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= ($editPost['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small">Featured Image</label>
                        <?php if(!empty($editPost['featured_image'])): ?>
                            <div class="mb-2"><img src="<?= SITE_URL ?>/<?= htmlspecialchars($editPost['featured_image']) ?>" class="img-thumbnail rounded-3" style="height:100px; object-fit:cover;"></div>
                        <?php endif; ?>
                        <input type="file" name="featured_image" class="form-control rounded-3" accept="image/*">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-custom px-4 rounded-pill">
                            <i class="fa-solid fa-save me-2"></i><?= $editPost ? 'Update Post' : 'Publish Post' ?>
                        </button>
                        <?php if($editPost): ?>
                            <a href="blog.php" class="btn btn-outline-secondary rounded-pill">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Post List -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">All Posts <span class="badge bg-secondary ms-1"><?= count($posts) ?></span></h5>
                <?php if(empty($posts)): ?>
                    <p class="text-muted text-center py-4">No posts yet. Write your first post!</p>
                <?php else: foreach($posts as $p): ?>
                <div class="d-flex align-items-start gap-3 mb-3 pb-3 border-bottom">
                    <div class="flex-grow-1">
                        <div class="fw-bold small"><?= htmlspecialchars($p['title']) ?></div>
                        <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($p['cat_name'] ?? 'Uncategorized') ?> · <?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                    </div>
                    <span class="badge <?= $p['status']==='published'?'bg-success':'bg-secondary' ?> flex-shrink-0"><?= ucfirst($p['status']) ?></span>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <a href="blog.php?edit=<?= $p['post_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <form method="POST" onsubmit="return confirm('Delete this post?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="post_id" value="<?= $p['post_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-generate slug from title
document.getElementById('postTitle')?.addEventListener('input', function() {
    const slugField = document.getElementById('postSlug');
    if (!slugField.dataset.manual) {
        slugField.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
    }
});
document.getElementById('postSlug')?.addEventListener('input', function() {
    this.dataset.manual = '1';
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
