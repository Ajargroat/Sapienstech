// Student dashboard: render Jalali dates and Persian weekday names over the
// server's Gregorian fallback text, and wire the read-only schedule-details
// dialog. Same pinned-UTC Intl approach as consultant-exams.js — stored
// datetimes are wall-clock, so formatting them as UTC can never shift the day.

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

    initScheduleDialog();
}

// Boxes are <button data-sched-item="#template-id">; details live in hidden
// server-rendered <template>s and are cloned into one shared <dialog>.
function initScheduleDialog() {
    const dialog = document.getElementById('schedule-detail-modal');
    if (!dialog || dialog.dataset.wired) return;
    dialog.dataset.wired = '1';

    const title = dialog.querySelector('#sched-modal-title span');
    const body = dialog.querySelector('.sched-modal-body');

    document.querySelectorAll('[data-sched-item]').forEach((box) => {
        box.addEventListener('click', () => {
            const tpl = document.getElementById(box.dataset.schedItem);
            if (!tpl) return;

            body.replaceChildren(tpl.content.cloneNode(true));
            if (title) title.textContent = box.dataset.schedTitle || '';
            init(dialog); // rewrite the freshly cloned Jalali date placeholders
            dialog.showModal();
        });
    });

    dialog.querySelector('[data-sched-close]')?.addEventListener('click', () => dialog.close());

    // Click on the backdrop (the dialog element itself, outside the box).
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) dialog.close();
    });
}

// The router calls init() on every render of this page; this fallback only
// boots the page when app.js never made it (so a JS error can't leave
// Gregorian dates behind).
if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
    else init();
}
