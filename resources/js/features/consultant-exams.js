// Exams workspace: Persian dates via the browser's ICU, grid/list switch,
// report-card popup, create-exam dialog. The filter popover's outside-click
// close is already handled in app.js via the shared #filter-toggle checkbox.

document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('exams-app');
    if (!app) return;

    const DAY_MS = 86400000;
    const pad = (n) => String(n).padStart(2, '0');

    // ASCII digits for arithmetic, fa-IR digits for display. Both pinned to UTC
    // so the stored wall clock and the rendered date can never drift.
    const partsFmt = new Intl.DateTimeFormat('en-u-ca-persian', {
        timeZone: 'UTC', year: 'numeric', month: 'numeric', day: 'numeric',
    });
    const displayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'UTC', year: 'numeric', month: '2-digit', day: '2-digit',
    });

    const jalaliParts = (date) => {
        const out = {};
        for (const part of partsFmt.formatToParts(date)) {
            if (part.type !== 'literal') out[part.type] = Number(part.value);
        }
        return out;
    };

    const toAscii = (value) => String(value)
        .replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
        .replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));

    // 1 Farvardin of a Jalali year: Nowruz always lands 19-23 March.
    const nowruz = (jy) => {
        for (let offset = -4; offset <= 4; offset += 1) {
            const candidate = new Date(Date.UTC(jy + 621, 2, 21 + offset));
            const p = jalaliParts(candidate);
            if (p.year === jy && p.month === 1 && p.day === 1) return candidate;
        }
        return null;
    };

    const jalaliToUtc = (jy, jm, jd) => {
        const start = nowruz(jy);
        if (!start) return null;

        // Months 1-6 have 31 days, 7-12 have 30 (month 12 absorbs the leap day).
        const dayOfYear = jm <= 6 ? (jm - 1) * 31 + jd : 186 + (jm - 7) * 30 + jd;
        const gregorian = new Date(start.getTime() + (dayOfYear - 1) * DAY_MS);

        // Round-trip guard: reject anything the calendar does not agree with.
        const check = jalaliParts(gregorian);
        return check.year === jy && check.month === jm && check.day === jd ? gregorian : null;
    };

    // --- Render Jalali dates over the server's Gregorian fallback text ---
    app.querySelectorAll('time.fa-date[datetime]').forEach((el) => {
        const date = new Date(el.getAttribute('datetime'));
        if (!Number.isNaN(date.getTime())) el.textContent = displayFmt.format(date);
    });

    // --- Create dialog: turn the typed Jalali date into a hidden ISO value ---
    const dateField = document.getElementById('exam-date');
    const timeField = document.getElementById('exam-time');
    const hiddenDate = document.getElementById('exam-date-g');
    const dateError = document.getElementById('exam-date-error');
    const form = document.getElementById('create-exam-form');

    const showDateError = (message) => {
        if (!dateError) return;
        dateError.textContent = message;
        dateError.hidden = false;
    };

    form?.addEventListener('submit', (event) => {
        if (!dateField || !hiddenDate) return;

        const digits = toAscii(dateField.value).trim();

        // Nothing typed but a valid hidden value survived a failed submit: keep it.
        if (!digits && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(hiddenDate.value)) return;

        const d = digits.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
        const t = toAscii(timeField?.value || '00:00').trim().match(/^(\d{1,2}):(\d{2})$/);

        if (!d) {
            showDateError('تاریخ را به شکل ۱۴۰۵/۰۶/۲۰ وارد کنید.');
            event.preventDefault();
            return;
        }

        if (!t) {
            showDateError('ساعت را به شکل ۰۸:۳۰ وارد کنید.');
            event.preventDefault();
            return;
        }

        const gregorian = jalaliToUtc(Number(d[1]), Number(d[2]), Number(d[3]));

        if (!gregorian) {
            showDateError('این تاریخ معتبر نیست.');
            event.preventDefault();
            return;
        }

        if (dateError) dateError.hidden = true;

        hiddenDate.value =
            `${gregorian.getUTCFullYear()}-${pad(gregorian.getUTCMonth() + 1)}-${pad(gregorian.getUTCDate())}` +
            ` ${pad(Number(t[1]))}:${t[2]}`;
    });

    // --- View mode: one button whose two icons trade places ---
    const grid = document.getElementById('exam-grid');
    const viewToggle = document.getElementById('view-toggle');

    const setView = (mode) => {
        grid?.classList.toggle('is-list', mode === 'list');

        if (viewToggle) {
            viewToggle.dataset.view = mode;
            viewToggle.title = mode === 'list' ? 'نمایش کارتی' : 'نمایش فهرستی';
        }

        localStorage.setItem('exams-view-mode', mode);
    };

    viewToggle?.addEventListener('click', () =>
        setView(viewToggle.dataset.view === 'list' ? 'grid' : 'list')
    );

    if (localStorage.getItem('exams-view-mode') === 'list') {
        setView('list');
    }

    // --- Dialogs ---
    const open = (id) => document.getElementById(id)?.showModal();
    const close = (id) => document.getElementById(id)?.close();

    document.getElementById('open-create-exam')?.addEventListener('click', () => open('create-exam-modal'));
    app.querySelector('.exam-modal-cancel')?.addEventListener('click', () => close('create-exam-modal'));

    const reportButton = document.getElementById('open-report-modal');
    const reportModal = document.getElementById('report-modal');

    if (reportButton && reportModal) {
        const frame = reportModal.querySelector('iframe');

        reportButton.addEventListener('click', () => {
            // Lazy-load so the report page isn't fetched on every visit.
            if (frame && !frame.src) frame.src = frame.dataset.src;
            reportModal.showModal();
        });

        reportModal.querySelector('.report-modal-close')?.addEventListener('click', () => reportModal.close());

        // Click on the blurred backdrop (the dialog element itself) closes it.
        reportModal.addEventListener('click', (event) => {
            if (event.target === reportModal) reportModal.close();
        });
    }

    // Server-side validation failed → reopen the dialog with the old input.
    if (document.getElementById('exam-form-errors')?.dataset.hasErrors === '1') {
        open('create-exam-modal');
  }

    // --- Two-step builder: meta -> question bank picker ---
    const modal = document.getElementById('create-exam-modal');
    const pickerRoot = document.getElementById('picker-root');

    if (modal && pickerRoot) {
        const steps = [...modal.querySelectorAll('.bstep')];
        const panes = [...modal.querySelectorAll('.bstep-pane')];
        const nextBtn = document.getElementById('builder-next');
        const backBtn = document.getElementById('builder-back');
        const submitBtn = document.getElementById('builder-submit');
        const tray = document.getElementById('picker-tray');
        const payload = document.getElementById('exam-questions-inputs');
        const countBadge = document.getElementById('step2-count');
        const sourceManual = modal.querySelector('input[value="manual"]');

        // Survive a failed server-side submit.
        // Survive a failed server-side submit (seeded from Blade via data-selected).
        let selected = JSON.parse(payload.dataset.selected || '[]').map(Number);

        const showStep = (n) => {
            steps.forEach((b) => b.classList.toggle('is-active', +b.dataset.step === n));
            panes.forEach((p) => { p.hidden = +p.dataset.pane !== n; });
            backBtn.hidden = n === 1;
            nextBtn.hidden = n === 2;
            submitBtn.hidden = n !== 2;
            if (n === 2 && !pickerRoot.dataset.loaded) loadPicker(1);
        };

        const sync = () => {
            payload.innerHTML = selected
                .map((id) => `<input type="hidden" name="questions[]" value="${id}">`)
                .join('');
            countBadge.textContent = selected.length ? `(${selected.length})` : '';
            renderTray();
        };

        const renderTray = () => {
            tray.innerHTML = selected.length
                ? selected.map((id, i) => `
                    <li class="tray-item" data-id="${id}">
                        <span class="tray-num">${i + 1}</span>
                        <span class="tray-id">سوال ${id}</span>
                        <span class="tray-actions">
                            <button type="button" data-move="up" ${i === 0 ? 'disabled' : ''} aria-label="بالا">↑</button>
                            <button type="button" data-move="down" ${i === selected.length - 1 ? 'disabled' : ''} aria-label="پایین">↓</button>
                            <button type="button" data-remove aria-label="حذف">×</button>
                        </span>
                    </li>`).join('')
                : '<li class="tray-empty">هنوز سوالی انتخاب نشده است.</li>';
        };

        const loadPicker = (page) => {
            const url = new URL(pickerRoot.dataset.url);
            const search = document.getElementById('picker-search')?.value.trim();
            const diff = document.getElementById('picker-difficulty')?.value;
            if (search) url.searchParams.set('search', search);
            if (diff) url.searchParams.set('difficulty', diff);
            url.searchParams.set('page', page);

            pickerRoot.classList.add('is-loading');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.text())
                .then((html) => {
                    pickerRoot.innerHTML = html;
                    pickerRoot.dataset.loaded = '1';
                    pickerRoot.dataset.page = page;
                    markSelected();
                })
                .finally(() => pickerRoot.classList.remove('is-loading'));
        };

        const markSelected = () => pickerRoot.querySelectorAll('.picker-item').forEach((el) => {
            el.classList.toggle('is-selected', selected.includes(+el.dataset.questionId));
            el.querySelector('.picker-check').checked = el.classList.contains('is-selected');
        });

        pickerRoot.addEventListener('change', (e) => {
            const item = e.target.closest('.picker-item');
            if (!item) return;
            const id = +item.dataset.questionId;
            if (e.target.checked && !selected.includes(id)) selected.push(id);
            if (!e.target.checked) selected = selected.filter((x) => x !== id);
            item.classList.toggle('is-selected', e.target.checked);
            sync();
        });

        pickerRoot.addEventListener('click', (e) => {
            const btn = e.target.closest('.picker-page');
            if (btn && !btn.disabled) loadPicker(+btn.dataset.page);
        });

        let searchTimer;
        document.getElementById('picker-search')?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadPicker(1), 350);
        });
        document.getElementById('picker-difficulty')?.addEventListener('change', () => loadPicker(1));

        tray?.addEventListener('click', (e) => {
            const li = e.target.closest('.tray-item');
            if (!li) return;
            const id = +li.dataset.id;
            const i = selected.indexOf(id);
            if (e.target.closest('[data-remove]')) selected.splice(i, 1);
            if (e.target.closest('[data-move="up"]') && i > 0) [selected[i - 1], selected[i]] = [selected[i], selected[i - 1]];
            if (e.target.closest('[data-move="down"]') && i < selected.length - 1) [selected[i + 1], selected[i]] = [selected[i], selected[i + 1]];
            sync();
            markSelected();
        });

        steps.forEach((b) => b.addEventListener('click', () => showStep(+b.dataset.step)));
        nextBtn?.addEventListener('click', () => showStep(2));
        backBtn?.addEventListener('click', () => showStep(1));
        sourceManual?.addEventListener('change', () => {
            if (sourceManual.checked) showStep(1);
        });

      sync();
      if (selected.length) showStep(2);
    }

});
