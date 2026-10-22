// Student report card (کارنامه): Jalali dates over the server's Gregorian
// fallback text, plus a one-shot reveal of the panels in reading order.
//
// Same pinned-UTC Intl approach as student-dashboard.js — stored datetimes are
// wall clock, so formatting them as UTC can never shift the day.
//
// The reveal is purely additive: the class is added by this script, so the
// panels are fully visible with JS off or when the browser is still loading.
// Elements are revealed individually as they enter the viewport, and the
// whole set is flushed on teardown (page-router.js bindPage/stopPage).

export default function init(region = document) {
    const root = region || document;

    const displayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'UTC', year: 'numeric', month: '2-digit', day: '2-digit',
    });

    root.querySelectorAll('time.fa-date[datetime]').forEach((el) => {
        const date = new Date(el.getAttribute('datetime'));
        if (!Number.isNaN(date.getTime())) el.textContent = displayFmt.format(date);
    });

    const targets = [...root.querySelectorAll('[data-reveal]')];
    const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    if (!targets.length || reduced || typeof IntersectionObserver === 'undefined') {
        return () => {};
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -40px 0px', threshold: 0.05 }
    );

    targets.forEach((el) => {
        el.classList.add('rc-reveal');
        observer.observe(el);
    });

    return () => {
        observer.disconnect();
        targets.forEach((el) => el.classList.remove('rc-reveal', 'is-in'));
    };
}
