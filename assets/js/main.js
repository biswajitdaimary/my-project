document.addEventListener('DOMContentLoaded', function() {
    // Initialize AOS Animation
    AOS.init({
        duration: 800,
        easing: 'ease-in-out',
        once: true,
        mirror: false
    });

    // Navbar: click pulse animation + mobile close
    const navLinks = document.querySelectorAll('.site-navbar .navbar-nav .nav-link');
    const menuToggle = document.getElementById('navbarNav');
    const bsCollapse = menuToggle ? new bootstrap.Collapse(menuToggle, {toggle: false}) : null;

    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            // Play click pulse on the underline
            link.classList.add('nav-clicking');
            setTimeout(() => link.classList.remove('nav-clicking'), 400);

            // Close mobile menu
            if (bsCollapse && menuToggle && menuToggle.classList.contains('show')) {
                bsCollapse.toggle();
            }
        });
    });

    // Animated Counter for Stats Strip
    function animateCounter(el) {
        const target = parseInt(el.getAttribute('data-target'), 10);
        if (isNaN(target)) return;
        
        // Dynamic duration: faster for small numbers, capped at 2s for large ones
        const duration = Math.min(2000, 800 + (target > 100 ? 1000 : target * 10));
        const start = performance.now();

        function step(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            
            // Ease-out cubic for a smooth deceleration
            const ease = 1 - Math.pow(1 - progress, 3);
            
            const current = Math.floor(ease * target);
            el.textContent = current.toLocaleString() + '+';
            
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString() + '+';
            }
        }
        requestAnimationFrame(step);
    }

    // Only trigger when stats strip enters the viewport
    const statNumbers = document.querySelectorAll('.stat-number[data-target]');
    if (statNumbers.length > 0 && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        statNumbers.forEach(el => observer.observe(el));
    }

    // Smooth active nav link highlight based on scroll position
    const sections = document.querySelectorAll('section[id], div[id]');
    const navAnchors = document.querySelectorAll('.hero-anchor-nav a');
    if (sections.length > 0 && navAnchors.length > 0) {
        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 120;
                if (window.scrollY >= sectionTop) current = section.getAttribute('id');
            });
            navAnchors.forEach(a => {
                a.style.color = a.getAttribute('href') === '#' + current
                    ? 'var(--primary-color)'
                    : 'rgba(255,255,255,0.78)';
            });
        }, { passive: true });
    }
});
