<?php
$pageTitle = 'Blog';
require_once 'includes/header.php';
require_once 'includes/nav.php';

$categoryFilter = $_GET['category'] ?? '';

// Fetch active categories
$categories = [];
try {
    $cStmt = $pdo->query("SELECT * FROM blog_categories ORDER BY name ASC");
    $categories = $cStmt->fetchAll();
} catch(Exception $e) {}

// Fetch Posts
$posts = [];
try {
    if ($categoryFilter) {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name 
            FROM blog_posts p 
            LEFT JOIN blog_categories c ON p.category_id = c.category_id 
            LEFT JOIN users u ON p.author_id = u.user_id 
            WHERE p.status = 'published' AND c.slug = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$categoryFilter]);
    } else {
        $stmt = $pdo->query("
            SELECT p.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name 
            FROM blog_posts p 
            LEFT JOIN blog_categories c ON p.category_id = c.category_id 
            LEFT JOIN users u ON p.author_id = u.user_id 
            WHERE p.status = 'published' 
            ORDER BY p.created_at DESC
        ");
    }
    $posts = $stmt->fetchAll();
} catch(Exception $e) {
    die("Database Error");
}
?>

<section class="section-padding bg-light min-vh-100">
    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8 pe-lg-5" data-aos="fade-right">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <h2 class="fw-bold mb-0">Latest Articles <?= $categoryFilter ? '- ' . htmlspecialchars(ucfirst($categoryFilter)) : '' ?></h2>
                </div>

                <?php if(empty($posts)): ?>
                    <div class="alert alert-info">No blog posts found.</div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach($posts as $post): ?>
                            <div class="col-12">
                                <article class="card border-0 shadow-sm overflow-hidden h-100 flex-md-row">
                                    <div class="col-md-5">
                                        <?php $imgSrc = $post['featured_image'] ? htmlspecialchars($post['featured_image']) : 'https://placehold.co/600x400/1A1A2E/FFF?text=Blog+Post'; ?>
                                        <img src="<?= $imgSrc ?>" class="img-fluid h-100 w-100 object-fit-cover" alt="<?= htmlspecialchars($post['title']) ?>">
                                    </div>
                                    <div class="col-md-7 card-body p-4 d-flex flex-column">
                                        <div class="mb-2">
                                            <a href="?category=<?= $post['category_slug'] ?>" class="badge bg-primary-custom text-decoration-none me-2"><?= htmlspecialchars($post['category_name']) ?></a>
                                            <span class="text-muted small"><i class="fa-regular fa-calendar me-1"></i> <?= date('M d, Y', strtotime($post['created_at'])) ?></span>
                                        </div>
                                        <h4 class="card-title fw-bold mb-3">
                                            <a href="blog-detail.php?slug=<?= $post['slug'] ?>" class="text-dark text-decoration-none main-link-hover"><?= htmlspecialchars($post['title']) ?></a>
                                        </h4>
                                        <p class="card-text text-muted flex-grow-1"><?= htmlspecialchars($post['excerpt']) ?></p>
                                        <div class="mt-3 d-flex justify-content-between align-items-center">
                                            <span class="small text-muted fw-bold"><i class="fa-solid fa-user-pen me-1"></i> <?= htmlspecialchars($post['author_name'] ?? 'Admin') ?></span>
                                            <a href="blog-detail.php?slug=<?= $post['slug'] ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">Read More <i class="fa-solid fa-arrow-right ms-1"></i></a>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4 mt-5 mt-lg-0" data-aos="fade-left">
                <div class="card border-0 shadow-sm p-4 mb-4">
                    <h5 class="fw-bold mb-3">Search</h5>
                    <form action="blog.php" method="GET" class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search posts..." required>
                        <button class="btn btn-primary-custom" type="submit"><i class="fa-solid fa-search"></i></button>
                    </form>
                </div>

                <div class="card border-0 shadow-sm p-4">
                    <h5 class="fw-bold mb-3">Categories</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 border-0">
                            <a href="blog.php" class="text-decoration-none text-dark d-flex justify-content-between align-items-center <?= empty($categoryFilter) ? 'text-primary-custom fw-bold' : '' ?>">
                                All Categories
                            </a>
                        </li>
                        <?php foreach($categories as $cat): ?>
                            <li class="list-group-item px-0 border-0">
                                <a href="?category=<?= $cat['slug'] ?>" class="text-decoration-none text-dark d-flex justify-content-between align-items-center <?= ($categoryFilter === $cat['slug']) ? 'text-primary-custom fw-bold' : '' ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .main-link-hover:hover {
        color: var(--primary-color) !important;
    }
</style>

<?php require_once 'includes/footer.php'; ?>
