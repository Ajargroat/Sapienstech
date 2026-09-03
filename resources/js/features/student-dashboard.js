// Student dashboard: render Jalali dates and Persian weekday names over the
// server's Gregorian fallback text. Same pinned-UTC Intl approach as
// consultant-exams.js — stored datetimes are wall-clock, so formatting them
// as UTC can never shift the day.

export default function init(region = document) {
    const displayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'UTC', year: 'numeric', month: '2-digit', day: '2-digit',
    });
    const weekdayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'UTC', weekday: 'long',
    });

    (region || document).querySelectorAll('time.fa-date[datetime]').forEach((el) => {
        const date = new Date(el.getAttribute('datetime'));
        if (!Number.isNaN(date.getTime())) el.textContent = displayFmt.format(date);
    });

    (region || document).querySelectorAll('[data-fa-weekday]').forEach((el) => {
        const date = new Date(el.getAttribute('data-fa-weekday'));
        if (!Number.isNaN(date.getTime())) el.textContent = weekdayFmt.format(date);
    });
}

// The router calls init() on every render of this page; this fallback only
// boots the page when app.js never made it (so a JS error can't leave
// Gregorian dates behind).
if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
    else init();
}
