<?php
$pageTitle = 'Gallery';
require_once 'includes/header.php';
require_once 'includes/nav.php';

// Fetch distinct categories for filter buttons
$categories = [];
try {
    $cStmt = $pdo->query("SELECT DISTINCT category FROM gallery WHERE is_active = 1 ORDER BY category ASC");
    $categories = $cStmt->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {}

// Fetch images
$images = [];
try {
    $iStmt = $pdo->query("SELECT * FROM gallery WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC");
    $images = $iStmt->fetchAll();
} catch(Exception $e) {}
?>

<!-- Add Lightbox CSS securely -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css" rel="stylesheet" />

<style>
.gallery-filter-wrapper {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.85rem;
    margin-bottom: 3.5rem;
}
.gallery-filter-btn {
    background: #fff;
    border: 2px solid #f0f0f5;
    color: #555;
    font-weight: 600;
    font-size: 0.95rem;
    padding: 0.6rem 1.8rem;
    border-radius: 50px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.gallery-filter-btn:hover {
    border-color: #FF6B35;
    color: #FF6B35;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(255, 107, 53, 0.15);
}
.gallery-filter-btn.active {
    background: #FF6B35;
    border-color: #FF6B35;
    color: #fff;
    box-shadow: 0 10px 30px rgba(255, 107, 53, 0.35);
}
.gallery-card {
    border-radius: 1.25rem;
    overflow: hidden;
    position: relative;
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    aspect-ratio: 4/3;
    display: block;
    background: #f8f9fa;
}
.gallery-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.gallery-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.12);
}
.gallery-card:hover img {
    transform: scale(1.08);
}
.gallery-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 3rem 1.5rem 1.5rem;
    background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.4) 60%, transparent 100%);
    color: #fff;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}
.gallery-card:hover .gallery-overlay {
    opacity: 1;
    transform: translateY(0);
}
.gallery-overlay h5 {
    margin: 0 0 0.3rem 0;
    font-weight: 700;
    font-size: 1.3rem;
    letter-spacing: 0.5px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
}
.gallery-overlay span {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #FF6B35;
    font-weight: 700;
}
.gallery-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.5);
    background: rgba(255,255,255,0.25);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.75rem;
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255,255,255,0.4);
}
.gallery-card:hover .gallery-icon {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
}
.gallery-item {
    transition: opacity 0.4s ease, transform 0.4s ease;
}
</style>

<section class="section-padding bg-light min-vh-100 pb-5">
    <div class="container pb-5">
        <div class="section-title text-center mb-5" data-aos="fade-up">
            <span class="text-primary-custom fw-bold text-uppercase tracking-wide">Gallery</span>
            <h2 class="mb-3 display-5 fw-bold">Our Facility & Community</h2>
            <p class="text-muted fs-5">Take a look inside FITNESS DESTINATION</p>
        </div>

        <?php if(empty($images)): ?>
            <div class="alert alert-info text-center mt-5 p-4 rounded-4 shadow-sm">
                <i class="fa-solid fa-images fa-3x mb-3 text-muted opacity-50 d-block"></i>
                <h5 class="fw-bold text-muted">No Images Yet</h5>
                <p class="mb-0 text-muted">Check back later for photos of our amazing facility and members!</p>
            </div>
        <?php else: ?>
            <!-- Filter Buttons -->
            <div class="gallery-filter-wrapper" data-aos="fade-up" data-aos-delay="100">
                <button class="gallery-filter-btn active" data-filter="all">All Photos</button>
                <?php foreach($categories as $category): ?>
                    <button class="gallery-filter-btn text-capitalize" data-filter="<?= htmlspecialchars($category) ?>">
                        <?= htmlspecialchars($category) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Gallery Grid -->
            <div class="row g-4" id="gallery-grid" data-aos="fade-up" data-aos-delay="200">
                <?php foreach($images as $img): ?>
                <div class="col-lg-4 col-md-6 gallery-item" data-category="<?= htmlspecialchars($img['category']) ?>">
                    <a href="<?= htmlspecialchars($img['file_path']) ?>" data-lightbox="gym-gallery" data-title="<?= htmlspecialchars($img['title']) ?>" class="gallery-card">
                        <img src="<?= htmlspecialchars($img['file_path']) ?>" alt="<?= htmlspecialchars($img['alt_text'] ?: $img['title']) ?>" loading="lazy">
                        
                        <!-- Hover Overlay -->
                        <div class="gallery-overlay">
                            <h5><?= htmlspecialchars($img['title']) ?></h5>
                            <span><?= htmlspecialchars($img['category']) ?></span>
                        </div>
                        
                        <!-- Center Icon -->
                        <div class="gallery-icon">
                            <i class="fa-solid fa-expand"></i>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Lightbox JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox-plus-jquery.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.gallery-filter-btn');
    const items = document.querySelectorAll('.gallery-item');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Update button styles
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const filterValue = this.getAttribute('data-filter');

            items.forEach(item => {
                if (filterValue === 'all' || item.getAttribute('data-category') === filterValue) {
                    item.style.display = 'block';
                    // Slight delay for animation effect
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'scale(1)';
                    }, 50);
                } else {
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.9)';
                    setTimeout(() => item.style.display = 'none', 400);
                }
            });
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
