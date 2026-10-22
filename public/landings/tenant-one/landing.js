/**
 * Bespoke landing behaviour — آکادمی جنت (tenant-one).
 *
 * Owns every kind of motion the page has, so the blade carries zero inline
 * script (the CSP allows no inline <script>). Motion identity per the
 * tenant's stored spec: scroll progress, reveal-on-scroll, stat counters and
 * pointer tilt.
 *
 * Two kill switches are honoured, in this order:
 *   1. the tenant's own data-animations switch on <html>;
 *   2. prefers-reduced-motion.
 * Either one leaves the page fully rendered and fully readable: the reveal
 * styles only apply once this file has added the .jn-obs class, so without
 * JS (or with motion off) nothing is ever hidden.
 *
 * Every number this file puts on screen comes from the page markup itself
 * (data-counter / data-suffix) — none are invented here.
 */
(function () {
    'use strict';

    var root = document.documentElement;

    if (root.dataset.animations !== 'on') {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    // Animate nothing before this class exists: the CSS scopes every reveal
    // to html[data-animations="on"].jn-obs.
    root.classList.add('jn-obs');

    // ---- scroll progress bar ----------------------------------------------
    var bar = document.querySelector('.jn-progress');
    if (bar) {
        var updateProgress = function () {
            var doc = document.documentElement;
            var max = doc.scrollHeight - doc.clientHeight;
            var p = max > 0 ? Math.min(1, Math.max(0, (doc.scrollTop || document.body.scrollTop) / max)) : 0;
            bar.style.setProperty('--jn-progress', p.toFixed(4));
        };
        document.addEventListener('scroll', updateProgress, { passive: true });
        updateProgress();
    }

    // ---- reveal on scroll ---------------------------------------------------
    var risers = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
    if (risers.length) {
        if ('IntersectionObserver' in window) {
            var revealIo = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('jn-in');
                        revealIo.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 });
            risers.forEach(function (el) { revealIo.observe(el); });
        } else {
            risers.forEach(function (el) { el.classList.add('jn-in'); });
        }
    }

    // ---- boxed-stat counters ------------------------------------------------
    // Persian numerals when the page declares them: Intl formats 91 as ۹۱.
    var faNumerals = root.dataset.numerals === 'fa';
    var format = faNumerals
        ? new Intl.NumberFormat('fa-IR')
        : new Intl.NumberFormat('en-US');

    var runCounter = function (el) {
        var target = parseInt(el.getAttribute('data-counter'), 10) || 0;
        var suffix = el.getAttribute('data-suffix') || '';
        var out = el.querySelector('.jn-stat__v') || el;
        var startedAt = null;
        var duration = parseInt(root.dataset.counterDuration, 10) || 1600;

        var step = function (ts) {
            if (startedAt === null) {
                startedAt = ts;
            }
            var k = duration > 0 ? Math.min(1, (ts - startedAt) / duration) : 1;
            var eased = 1 - Math.pow(1 - k, 3);
            out.textContent = format.format(Math.round(target * eased)) + suffix;
            if (k < 1) {
                window.requestAnimationFrame(step);
            }
        };

        window.requestAnimationFrame(step);
    };

    var counters = Array.prototype.slice.call(document.querySelectorAll('[data-counter]'));
    if (counters.length) {
        if ('IntersectionObserver' in window) {
            var counterIo = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        runCounter(entry.target);
                        counterIo.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.4 });
            counters.forEach(function (el) { counterIo.observe(el); });
        } else {
            counters.forEach(function (el) { runCounter(el); });
        }
    }

    // ---- pointer tilt on glass cards ---------------------------------------
    if (window.matchMedia('(pointer: fine)').matches) {
        Array.prototype.slice.call(document.querySelectorAll('.jn-card--tilt')).forEach(function (card) {
            card.addEventListener('pointermove', function (ev) {
                var rect = card.getBoundingClientRect();
                var x = (ev.clientX - rect.left) / rect.width - 0.5;
                var y = (ev.clientY - rect.top) / rect.height - 0.5;
                card.style.transform = 'perspective(900px) rotateX(' + (-y * 3).toFixed(2) +
                    'deg) rotateY(' + (x * 3).toFixed(2) + 'deg)';
            });
            card.addEventListener('pointerleave', function () {
                card.style.transform = '';
            });
        });
    }
})();
