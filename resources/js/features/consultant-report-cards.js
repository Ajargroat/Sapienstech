// Report-card workspace: Persian dates via the browser's ICU, grid/list switch
// and the send-to-parents dialog. The filter popover's outside-click close is
// already handled in app.js via the shared #filter-toggle checkbox.

export default function init() {
    const app = document.getElementById('report-card-app');
    if (!app) return;

    // Pinned to UTC so the stored wall clock and the rendered date can never
    // drift — same contract as consultant-exams.js.
    const displayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'UTC', year: 'numeric', month: '2-digit', day: '2-digit',
    });

    // --- Render Jalali dates over the server's Gregorian fallback text ---
    app.querySelectorAll('time.fa-date[datetime]').forEach((el) => {
        const date = new Date(el.getAttribute('datetime'));
        if (!Number.isNaN(date.getTime())) el.textContent = displayFmt.format(date);
    });

    // --- View mode: one button whose two icons trade places ---
    const grid = document.getElementById('rc-grid');
    const viewToggle = document.getElementById('rc-view-toggle');

    const setView = (mode) => {
        grid?.classList.toggle('is-list', mode === 'list');

        if (viewToggle) {
            viewToggle.dataset.view = mode;
            viewToggle.title = mode === 'list' ? 'نمایش کارتی' : 'نمایش فهرستی';
        }

        localStorage.setItem('report-cards-view-mode', mode);
    };

    viewToggle?.addEventListener('click', () =>
        setView(viewToggle.dataset.view === 'list' ? 'grid' : 'list')
    );

    if (localStorage.getItem('report-cards-view-mode') === 'list') {
        setView('list');
    }

    // --- Send-to-parents dialog ---
    const modal = document.getElementById('send-report-card-modal');

    document.getElementById('open-send-modal')?.addEventListener('click', () => modal?.showModal());
    app.querySelector('.rc-send-close')?.addEventListener('click', () => modal?.close());

    // Click on the blurred backdrop (the dialog element itself) closes it.
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) modal.close();
    });
}

// The router calls init() on every render of this page; this fallback only
// boots the page when app.js never made it (so a JS error can't leave a
// dead report-card list).
if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}
