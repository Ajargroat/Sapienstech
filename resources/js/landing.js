/*
 * Landing-page behaviors: navbar scroll state, scroll-reveal, and animated
 * stat counters. Everything is gated on <body class="landing"> and on the
 * data-* attributes the landing template derives from config/consultant.php.
 */

const faDigits = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

document.addEventListener('DOMContentLoaded', () => {
    if (!document.body.classList.contains('landing')) return;

    const root = document.documentElement;
    const useFa = root.dataset.numerals === 'fa';
    const motion = root.dataset.animations !== 'off';
    const render = (n) => (useFa ? faDigits(n) : String(n));

    // Navbar scroll state
    const nav = document.getElementById('site-nav');
    if (nav) {
        const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 40);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // Reveal on scroll
    const reveals = document.querySelectorAll('.reveal');
    if (!motion || !('IntersectionObserver' in window)) {
        reveals.forEach((el) => el.classList.add('is-visible'));
    } else {
        const io = new IntersectionObserver(
            (entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) {
                        e.target.classList.add('is-visible');
                        io.unobserve(e.target);
                    }
                });
            },
            { threshold: 0.15 }
        );
        reveals.forEach((el) => io.observe(el));
    }

    // Animated counters
    const counters = document.querySelectorAll('[data-counter]');
    if (!counters.length) return;

    const duration = Number(root.dataset.counterDuration || 2000);

    const run = (el) => {
        const target = Number(el.dataset.counter);
        let start = null;
        const step = (ts) => {
            start ??= ts;
            const p = Math.min((ts - start) / duration, 1);
            el.textContent = render(Math.floor(p * target));
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    };

    if (!motion || !('IntersectionObserver' in window)) {
        counters.forEach((el) => (el.textContent = render(el.dataset.counter)));
        return;
    }

    const co = new IntersectionObserver(
        (entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    run(e.target);
                    co.unobserve(e.target);
                }
            });
        },
        { threshold: 0.4 }
    );
    counters.forEach((el) => co.observe(el));
});
