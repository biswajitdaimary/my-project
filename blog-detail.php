<?php
require_once 'config/config.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: blog.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name 
        FROM blog_posts p 
        LEFT JOIN blog_categories c ON p.category_id = c.category_id 
        LEFT JOIN users u ON p.author_id = u.user_id 
        WHERE p.slug = ? AND p.status = 'published'
    ");
    $stmt->execute([$slug]);
    $post = $stmt->fetch();

    if (!$post) {
        die("Post not found.");
    }
    
    // Increment view count
    $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE post_id = ?")->execute([$post['post_id']]);

} catch(Exception $e) {
    die("Database Error");
}

$pageTitle = $post['title'];
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<section class="section-padding bg-light min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                
                <a href="blog.php" class="text-decoration-none text-muted mb-4 d-inline-block"><i class="fa-solid fa-arrow-left me-1"></i> Back to Blog</a>
                
                <!-- Post Header -->
                <div class="mb-5 text-center">
                    <a href="blog.php?category=<?= $post['category_slug'] ?>" class="badge bg-primary-custom text-decoration-none mb-3 px-3 py-2 fs-6"><?= htmlspecialchars($post['category_name']) ?></a>
                    <h1 class="fw-bold mb-4 display-4"><?= htmlspecialchars($post['title']) ?></h1>
                    <div class="d-flex justify-content-center align-items-center text-muted">
                        <span class="me-4"><i class="fa-solid fa-user-circle me-1 text-primary-custom"></i> <?= htmlspecialchars($post['author_name'] ?? 'Admin') ?></span>
                        <span class="me-4"><i class="fa-regular fa-calendar me-1 text-primary-custom"></i> <?= date('F d, Y', strtotime($post['created_at'])) ?></span>
                        <span><i class="fa-solid fa-eye me-1 text-primary-custom"></i> <?= $post['views'] ?> Views</span>
                    </div>
                </div>

                <!-- Featured Image -->
                <div class="mb-5 shadow-lg rounded overflow-hidden">
                    <?php $imgSrc = $post['featured_image'] ? htmlspecialchars($post['featured_image']) : 'https://placehold.co/1200x600/1A1A2E/FFF?text=Blog+Header'; ?>
                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="img-fluid w-100" style="max-height: 500px; object-fit: cover;">
                </div>

                <!-- Content -->
                <div class="bg-white p-4 p-md-5 rounded shadow-sm fs-5 text-dark" style="line-height: 1.8;">
                    <?= $post['content'] ?> <!-- Render HTML from TinyMCE safely -->
                </div>

                <!-- Share Buttons -->
                <div class="bg-white p-4 mt-4 rounded shadow-sm d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Share this article:</h5>
                    <div>
                        <a href="#" class="btn btn-primary btn-sm rounded-circle text-white me-2" style="width: 35px; height: 35px; line-height: 22px;"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" class="btn btn-info btn-sm rounded-circle text-white me-2" style="width: 35px; height: 35px; line-height: 22px;"><i class="fa-brands fa-twitter"></i></a>
                        <a href="#" class="btn btn-success btn-sm rounded-circle text-white" style="width: 35px; height: 35px; line-height: 22px;"><i class="fa-brands fa-whatsapp"></i></a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
