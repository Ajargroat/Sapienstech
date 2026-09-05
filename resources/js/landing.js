/*
 * Landing-page behaviors: navbar scroll state, scroll-reveal, animated stat
 * counters, and the opt-in motion levers.
 *
 * Everything is gated on <body class="landing"> and on the data-* attributes the
 * landing template derives from the tenant-resolved config
 * (partials/theme-attrs.blade.php). Reveal *styles* are pure CSS keyed off
 * [data-reveal], so adding a new one never requires touching this file — this
 * script only ever toggles `.is-visible`.
 */

const faDigits = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    if (!body.classList.contains('landing')) return;

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

    // Animated counters. The template omits data-counter entirely when
    // animations.counters is false, so this is a no-op in that case.
    const counters = document.querySelectorAll('[data-counter]');
    if (counters.length) {
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
        } else {
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
        }
    }

    // ---- opt-in motion levers ---------------------------------------------
    // Each is off unless the theme asks for it, and each respects
    // prefers-reduced-motion, so a tenant cannot accidentally ship motion that
    // the visitor's OS has told us to suppress.
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!motion || reduced) return;

    if (root.dataset.tilt === 'on') enableCardTilt();
    if (root.dataset.magnetic === 'on') enableMagneticButtons();
    if (root.dataset.parallax && root.dataset.parallax !== 'none') enableParallax(root.dataset.parallax);
});

/* Subtle 3D tilt on cards. Pointer-driven, so it costs nothing on touch. */
function enableCardTilt() {
    const strength = 4; // degrees

    document.querySelectorAll('.lp-card').forEach((card) => {
        card.addEventListener('pointermove', (e) => {
            const r = card.getBoundingClientRect();
            const x = (e.clientX - r.left) / r.width - 0.5;
            const y = (e.clientY - r.top) / r.height - 0.5;
            card.style.transform = `perspective(700px) rotateX(${-y * strength}deg) rotateY(${x * strength}deg)`;
        });

        card.addEventListener('pointerleave', () => { card.style.transform = ''; });
    });
}

/* Buttons that drift a few pixels toward the cursor. */
function enableMagneticButtons() {
    const pull = 6;

    document.querySelectorAll('.lp-btn').forEach((btn) => {
        btn.addEventListener('pointermove', (e) => {
            const r = btn.getBoundingClientRect();
            const x = (e.clientX - r.left) / r.width - 0.5;
            const y = (e.clientY - r.top) / r.height - 0.5;
            btn.style.transform = `translate(${x * pull}px, ${y * pull}px)`;
        });

        btn.addEventListener('pointerleave', () => { btn.style.transform = ''; });
    });
}

/* Drifts the decorative glows as the page scrolls. */
function enableParallax(amount) {
    const factor = amount === 'strong' ? 0.18 : 0.07;
    const blobs = document.querySelectorAll('.lp-glow');

    if (!blobs.length) return;

    let queued = false;

    window.addEventListener('scroll', () => {
        if (queued) return;
        queued = true;

        requestAnimationFrame(() => {
            queued = false;
            const offset = window.scrollY * factor;
            blobs.forEach((blob, i) => {
                // Alternate direction so paired glows move apart, not together.
                blob.style.translate = `0 ${i % 2 ? offset : -offset}px`;
            });
        });
    }, { passive: true });
}
