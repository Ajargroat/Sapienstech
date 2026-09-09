// Exams workspace: Persian dates via the browser's ICU, grid/list switch and
// the create-exam dialog. The questions picker lives in its own dialog opened
// from the form, filtered by the lesson chips. Date/time popups (Jalali
// calendar + clock face) are plain DOM/SVG so they need no PHP extensions.
// The filter popover's outside-click close is already handled in app.js via
// the shared #filter-toggle checkbox.

// --- Popup manager -------------------------------------------------------
// Module scope so a single open popup survives router re-inits of init().
let activePopup = null;

function closePopup() {
    if (!activePopup) return;
    const popup = activePopup;
    activePopup = null;
    document.removeEventListener('pointerdown', outsidePopup, true);
    popup.el.hidden = true;
}

function outsidePopup(event) {
    if (!activePopup) return;
    const { el, host } = activePopup;
    if (el.contains(event.target) || host.contains(event.target)) return;
    closePopup();
}

function openPopup(el, host) {
    closePopup();
    if (!el._escapeBound) {
        el._escapeBound = true;
        // Escape must close only the popup, never the dialog behind it.
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.stopPropagation();
                closePopup();
            }
        });
    }
    el.hidden = false;
    activePopup = { el, host };
    document.addEventListener('pointerdown', outsidePopup, true);
}

export default function init() {
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

    const toFa = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[+d]);

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

    const parseClock = (raw) => {
        const m = toAscii(raw || '').trim().match(/^(\d{1,2}):(\d{2})$/);
        if (!m || +m[1] > 23 || +m[2] > 59) return null;
        return (+m[1]) * 60 + (+m[2]);
    };

    // --- Render Jalali dates over the server's Gregorian fallback text ---
    app.querySelectorAll('time.fa-date[datetime]').forEach((el) => {
        const date = new Date(el.getAttribute('datetime'));
        if (!Number.isNaN(date.getTime())) el.textContent = displayFmt.format(date);
    });

    // --- Success toast: fixed top-right, gone after 5 seconds -------------
    const toast = app.querySelector('.exam-toast');
    if (toast) {
        const dismiss = () => {
            toast.classList.add('is-leaving');
            setTimeout(() => toast.remove(), 300);
        };
        toast.querySelector('.exam-toast-close')?.addEventListener('click', dismiss);
        setTimeout(dismiss, 5000);
    }

    // --- Jalali calendar popup (pure DOM, Intl-backed) -------------------
    const J_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    const J_WEEK = ['ش', 'ی', 'د', 'س', 'پ', 'ج', 'م'];

    const daysInJalaliMonth = (jy, jm) => {
        if (jm <= 6) return 31;
        if (jm < 12) return 30;
        return jalaliToUtc(jy, 12, 30) ? 30 : 29;
    };

    const attachCalendar = (input) => {
        const host = input?.closest('.popup-field');
        if (!host || host.querySelector('.jpop')) return;
        const btn = host.querySelector('[data-popup="jalali"]');

        const el = document.createElement('div');
        el.className = 'jpop';
        el.hidden = true;
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-label', 'تقویم جلالی');
        host.appendChild(el);

        const view = { jy: 1405, jm: 1 };

        const selected = () => toAscii(input.value)
            .trim().match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);

        const render = () => {
            const sel = selected();
            const today = jalaliParts(new Date());
            const dim = daysInJalaliMonth(view.jy, view.jm);
            const first = jalaliToUtc(view.jy, view.jm, 1);
            const lead = first ? (first.getUTCDay() + 1) % 7 : 0; // Saturday first

            let html = `
                <div class="jpop-head">
                    <div class="jpop-nav">
                        <button type="button" data-nav="prev-year" aria-label="سال قبل">«</button>
                        <button type="button" data-nav="prev" aria-label="ماه قبل">‹</button>
                    </div>
                    <strong>${J_MONTHS[view.jm - 1]} ${toFa(view.jy)}</strong>
                    <div class="jpop-nav">
                        <button type="button" data-nav="next" aria-label="ماه بعد">›</button>
                        <button type="button" data-nav="next-year" aria-label="سال بعد">»</button>
                    </div>
                </div>
                <div class="jpop-grid jpop-dow">${J_WEEK.map((d) => `<span>${d}</span>`).join('')}</div>
                <div class="jpop-grid">`;
            for (let i = 0; i < lead; i += 1) html += '<span></span>';
            for (let d = 1; d <= dim; d += 1) {
                const isSel = sel && +sel[1] === view.jy && +sel[2] === view.jm && +sel[3] === d;
                const isToday = today.year === view.jy && today.month === view.jm && today.day === d;
                html += `<button type="button" data-day="${d}" class="jpop-day${isSel ? ' is-selected' : ''}${isToday ? ' is-today' : ''}">${toFa(d)}</button>`;
            }
            html += `</div>
                <div class="jpop-foot"><button type="button" data-nav="today">امروز</button></div>`;
            el.innerHTML = html;
        };

        const shift = (months) => {
            let m = view.jm + months;
            let y = view.jy;
            while (m < 1) { m += 12; y -= 1; }
            while (m > 12) { m -= 12; y += 1; }
            view.jm = m;
            view.jy = y;
            render();
        };

        el.addEventListener('click', (e) => {
            const nav = e.target.closest('[data-nav]');
            if (nav) {
                const n = nav.dataset.nav;
                if (n === 'prev') shift(-1);
                if (n === 'next') shift(1);
                if (n === 'prev-year') shift(-12);
                if (n === 'next-year') shift(12);
                if (n === 'today') {
                    const t = jalaliParts(new Date());
                    view.jy = t.year;
                    view.jm = t.month;
                    render();
                }
                return;
            }
            const day = e.target.closest('[data-day]');
            if (!day) return;
            input.value = toFa(`${view.jy}/${pad(view.jm)}/${pad(+day.dataset.day)}`);
            input.dispatchEvent(new Event('change', { bubbles: true }));
            closePopup();
        });

        const open = () => {
            if (!el.hidden) return;
            const sel = selected();
            if (sel && jalaliToUtc(+sel[1], +sel[2], +sel[3])) {
                view.jy = +sel[1];
                view.jm = +sel[2];
            } else {
                const t = jalaliParts(new Date());
                view.jy = t.year;
                view.jm = t.month;
            }
            render();
            openPopup(el, host);
        };

        btn?.addEventListener('click', () => (el.hidden ? open() : closePopup()));
        input.addEventListener('click', open);
    };

    // --- Clock-face time popup (Material-style dial) ----------------------
    // The DOM is built once and updates mutate existing nodes. Rebuilding
    // innerHTML on every pointermove detached the SVG mid-drag, which zeroed
    // its bounding rect and made the hand jump wildly. The hand is now a
    // rotating <g>: CSS-eased for discrete picks, transitionless while
    // dragging so it tracks the pointer exactly.
    const attachClock = (input) => {
        const host = input?.closest('.popup-field');
        if (!host || host.querySelector('.cpop')) return;
        const btn = host.querySelector('[data-popup="clock"]');

        const R = 78; // label ring radius inside the 220-unit viewBox
        const polar = (deg, r) => {
            const a = (deg * Math.PI) / 180;
            return [110 + (r * Math.sin(a)), 110 - (r * Math.cos(a))];
        };

        let ticks = '';
        for (let i = 0; i < 60; i += 1) {
            const major = i % 5 === 0;
            const [x1, y1] = polar(i * 6, major ? 88 : 91.5);
            const [x2, y2] = polar(i * 6, 96);
            ticks += `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" class="cpop-tick${major ? ' is-major' : ''}"></line>`;
        }

        let numMarkup = '';
        for (let i = 0; i < 12; i += 1) {
            const [x, y] = polar(i * 30, R);
            numMarkup += `<text x="${x}" y="${y}" text-anchor="middle" dominant-baseline="central" class="cpop-num"></text>`;
        }

        const el = document.createElement('div');
        el.className = 'cpop';
        el.hidden = true;
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-label', 'انتخاب ساعت');
        el.innerHTML = `
            <div class="cpop-head">
                <div class="cpop-display">
                    <button type="button" data-mode="hour" class="cpop-seg is-active"></button>
                    <span class="cpop-colon">:</span>
                    <button type="button" data-mode="minute" class="cpop-seg"></button>
                </div>
                <div class="cpop-ampm">
                    <button type="button" data-ampm="am">ق.ظ</button>
                    <button type="button" data-ampm="pm">ب.ظ</button>
                </div>
            </div>
            <svg viewBox="0 0 220 220" class="cpop-dial" aria-hidden="true">
                <circle cx="110" cy="110" r="99" class="cpop-face"></circle>
                <g class="cpop-ticks">${ticks}</g>
                <g class="cpop-hand-g">
                    <line x1="110" y1="110" x2="110" y2="${110 - R}" class="cpop-hand"></line>
                    <circle cx="110" cy="${110 - R}" r="16" class="cpop-knob"></circle>
                </g>
                <circle cx="110" cy="110" r="4" class="cpop-axis"></circle>
                <g class="cpop-nums">${numMarkup}</g>
            </svg>
            <div class="cpop-foot">
                <button type="button" data-clock="now">اکنون</button>
                <button type="button" data-clock="ok">تأیید</button>
            </div>`;
        host.appendChild(el);

        const svg = el.querySelector('.cpop-dial');
        const handG = el.querySelector('.cpop-hand-g');
        const numEls = [...el.querySelectorAll('.cpop-num')];
        const segH = el.querySelector('[data-mode="hour"]');
        const segM = el.querySelector('[data-mode="minute"]');
        const amBtn = el.querySelector('[data-ampm="am"]');
        const pmBtn = el.querySelector('[data-ampm="pm"]');

        const state = { h: 8, m: 0, mode: 'hour' };
        let dragging = false;

        const render = () => {
            segH.textContent = toFa(pad(state.h));
            segM.textContent = toFa(pad(state.m));
            segH.classList.toggle('is-active', state.mode === 'hour');
            segM.classList.toggle('is-active', state.mode === 'minute');
            amBtn.classList.toggle('is-active', state.h < 12);
            pmBtn.classList.toggle('is-active', state.h >= 12);

            handG.style.transform = `rotate(${state.mode === 'hour' ? (state.h % 12) * 30 : state.m * 6}deg)`;

            const picked = state.mode === 'hour' ? state.h % 12 : Math.round(state.m / 5) % 12;
            numEls.forEach((t, i) => {
                t.textContent = toFa(state.mode === 'hour' ? (i === 0 ? '12' : String(i)) : pad(i * 5));
                t.classList.toggle('is-picked', i === picked);
            });
        };

        const commit = (close) => {
            input.value = toFa(`${pad(state.h)}:${pad(state.m)}`);
            input.dispatchEvent(new Event('change', { bubbles: true }));
            if (close) closePopup();
        };

        const applyAngle = (evt) => {
            const box = svg.getBoundingClientRect();
            const dx = evt.clientX - (box.left + box.width / 2);
            const dy = (box.top + box.height / 2) - evt.clientY;
            let deg = (Math.atan2(dx, dy) * 180) / Math.PI;
            deg = (deg + 360) % 360;
            if (state.mode === 'hour') {
                const h = Math.round(deg / 30) % 12;
                state.h = state.h >= 12 ? (h === 0 ? 12 : h + 12) : h;
            } else {
                state.m = Math.round(deg / 6) % 60;
            }
            render();
        };

        el.addEventListener('click', (e) => {
            const seg = e.target.closest('[data-mode]');
            if (seg) {
                state.mode = seg.dataset.mode;
                render();
                return;
            }
            const ap = e.target.closest('[data-ampm]');
            if (ap) {
                const h12 = state.h % 12;
                state.h = ap.dataset.ampm === 'pm' ? (h12 === 0 ? 12 : h12 + 12) : h12;
                render();
                return;
            }
            if (e.target.closest('[data-clock="now"]')) {
                const now = new Date();
                state.h = now.getHours();
                state.m = now.getMinutes();
                state.mode = 'hour';
                render();
                return;
            }
            if (e.target.closest('[data-clock="ok"]')) commit(true);
        });

        el.addEventListener('pointerdown', (e) => {
            if (!e.target.closest('svg.cpop-dial')) return;
            e.preventDefault();
            dragging = true;
            el.classList.add('is-dragging');
            try { svg.setPointerCapture(e.pointerId); } catch (_) { /* older browsers */ }
            applyAngle(e);
        });
        el.addEventListener('pointermove', (e) => {
            if (dragging) applyAngle(e);
        });

        const endDrag = () => {
            if (!dragging) return;
            dragging = false;
            el.classList.remove('is-dragging');
            if (state.mode === 'hour') {
                state.mode = 'minute';
                render();
            } else {
                commit(true);
            }
        };
        el.addEventListener('pointerup', endDrag);
        el.addEventListener('pointercancel', endDrag);

        // Fine control without dragging: the wheel steps by 1 (Shift = 5)
        // and the arrow keys adjust while anything inside the popup has focus.
        const step = (dir) => {
            if (state.mode === 'hour') {
                state.h = (state.h + dir + 24) % 24;
            } else {
                state.m = (state.m + dir + 60) % 60;
            }
            render();
        };

        el.addEventListener('wheel', (e) => {
            if (!e.target.closest('.cpop-dial')) return;
            e.preventDefault();
            const dir = e.deltaY < 0 ? 1 : -1;
            for (let i = 0; i < (e.shiftKey ? 5 : 1); i += 1) step(dir);
        }, { passive: false });

        el.addEventListener('keydown', (e) => {
            const keys = { ArrowUp: 1, ArrowRight: 1, ArrowDown: -1, ArrowLeft: -1 };
            if (!(e.key in keys)) return;
            e.preventDefault();
            step(keys[e.key]);
        });

        const open = () => {
            if (!el.hidden) return;
            // A drag interrupted by an outside-click close never got its
            // pointerup; reset so the next open starts clean.
            dragging = false;
            el.classList.remove('is-dragging');
            const minutes = parseClock(input.value);
            if (minutes !== null) {
                state.h = Math.floor(minutes / 60);
                state.m = minutes % 60;
            } else {
                state.h = 8;
                state.m = 0;
            }
            state.mode = 'hour';
            render();
            openPopup(el, host);
        };

        btn?.addEventListener('click', () => (el.hidden ? open() : closePopup()));
        input.addEventListener('click', open);
    };

    // --- Create dialog: date/time state and submit conversion ------------
    const dateField = document.getElementById('exam-date');
    const timeField = document.getElementById('exam-time');
    const endField = document.getElementById('exam-end-time');
    const hiddenDate = document.getElementById('exam-date-g');
    const hiddenDuration = document.getElementById('exam-duration-g');
    const dateError = document.getElementById('exam-date-error');
    const timeError = document.getElementById('exam-time-error');
    const form = document.getElementById('create-exam-form');

    attachCalendar(dateField);
    attachClock(timeField);
    attachClock(endField);

    const showDateError = (message) => {
        if (!dateError) return;
        dateError.textContent = message;
        dateError.hidden = false;
    };

    const showTimeError = (message) => {
        if (!timeError) return;
        timeError.textContent = message;
        timeError.hidden = false;
    };

    // The form only ever ships the hidden ISO `date` ("Y-m-d H:i") plus the
    // computed duration; the visible Jalali/clock fields are input surfaces.
    form?.addEventListener('submit', (event) => {
        if (!dateField || !hiddenDate) return;

        const digits = toAscii(dateField.value).trim();
        let dateStr = '';

        // Nothing typed but a valid hidden value survived a failed submit: keep it.
        if (!digits && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(hiddenDate.value)) {
            dateStr = hiddenDate.value.slice(0, 10);
        } else {
            const d = digits.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
            if (!d) {
                showDateError('تاریخ را از تقویم انتخاب کنید.');
                event.preventDefault();
                return;
            }
            const gregorian = jalaliToUtc(Number(d[1]), Number(d[2]), Number(d[3]));
            if (!gregorian) {
                showDateError('این تاریخ معتبر نیست.');
                event.preventDefault();
                return;
            }
            dateStr = `${gregorian.getUTCFullYear()}-${pad(gregorian.getUTCMonth() + 1)}-${pad(gregorian.getUTCDate())}`;
        }
        if (dateError) dateError.hidden = true;

        const start = parseClock(timeField?.value);
        if (start === null) {
            showTimeError('ساعت شروع را از صفحه ساعت انتخاب کنید.');
            event.preventDefault();
            return;
        }

        const end = parseClock(endField?.value);
        if (end === null) {
            showTimeError('ساعت پایان را از صفحه ساعت انتخاب کنید.');
            event.preventDefault();
            return;
        }

        if (end <= start) {
            showTimeError('ساعت پایان باید بعد از ساعت شروع باشد.');
            event.preventDefault();
            return;
        }
        if (timeError) timeError.hidden = true;

        hiddenDate.value = `${dateStr} ${pad(Math.floor(start / 60))}:${pad(start % 60)}`;
        if (hiddenDuration) hiddenDuration.value = String(end - start);
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
    const createModal = document.getElementById('create-exam-modal');
    const pickerModal = document.getElementById('pick-questions-modal');
    const open = (id) => document.getElementById(id)?.showModal();
    const close = (id) => document.getElementById(id)?.close();

    document.getElementById('open-create-exam')?.addEventListener('click', () => open('create-exam-modal'));
    app.querySelector('.exam-modal-cancel')?.addEventListener('click', () => close('create-exam-modal'));

    [createModal, pickerModal].forEach((dialog) => {
        dialog?.addEventListener('click', (e) => {
            if (e.target === dialog) dialog.close();
        });
    });

    // Server-side validation failed → reopen the dialog with the old input.
    if (document.getElementById('exam-form-errors')?.dataset.hasErrors === '1') {
        open('create-exam-modal');
    }

    // --- Question bank dialog: opened from the form; its chip rows filter
    // the bank, and the lesson chips submit with the create form via the
    // form="create-exam-form" attribute. ---
    const pickerRoot = document.getElementById('picker-root');
    const lessonSummary = document.getElementById('lesson-summary');
    const summaryDefault = lessonSummary?.textContent.trim() ?? '';

    if (pickerModal && pickerRoot && createModal) {
        const tray = document.getElementById('picker-tray');
        const payload = document.getElementById('exam-questions-inputs');
        const badge = document.getElementById('picked-count');
        const lessonChips = [...pickerModal.querySelectorAll('#lesson-chips input')];
        const difficultyChips = [...pickerModal.querySelectorAll('#difficulty-chips input')];
        const corpChips = [...pickerModal.querySelectorAll('#corp-chips input')];

        // Survive a failed server-side submit (seeded from Blade via data-selected).
        let selected = JSON.parse(payload.dataset.selected || '[]').map(Number);

        // question id → short text, captured as cards render, shown in the tray.
        const snippets = {};

        const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[ch]));

        const chosenLessons = () => lessonChips.filter((c) => c.checked).map((c) => c.value);

        const updateSummary = () => {
            if (!lessonSummary) return;
            const lessons = chosenLessons();
            lessonSummary.textContent = lessons.length
                ? `درس‌های آزمون: ${lessons.join('، ')}`
                : summaryDefault;
        };

        const sync = () => {
            payload.innerHTML = selected
                .map((id) => `<input type="hidden" name="questions[]" value="${id}">`)
                .join('');
            badge.textContent = selected.length ? `${toFa(selected.length)} سوال انتخاب شد` : '';
            renderTray();
            updateSummary();
            markSelected();
        };

        const renderTray = () => {
            tray.innerHTML = selected.length
                ? selected.map((id, i) => `
                    <li class="tray-item" data-id="${id}">
                        <span class="tray-num">${toFa(i + 1)}</span>
                        <span class="tray-id">${snippets[id] ? escapeHtml(snippets[id]) : `سوال ${toFa(id)}`}</span>
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
            const diff = difficultyChips.find((c) => c.checked)?.value;
            const corp = corpChips.find((c) => c.checked)?.value;
            if (search) url.searchParams.set('search', search);
            if (diff) url.searchParams.set('difficulty', diff);
            if (corp) url.searchParams.set('corp', corp);
            chosenLessons().forEach((l) => url.searchParams.append('lessons[]', l));
            url.searchParams.set('page', page);

            pickerRoot.classList.add('is-loading');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.text())
                .then((html) => {
                    pickerRoot.innerHTML = html;
                    pickerRoot.dataset.page = page;
                    markSelected();
                })
                .finally(() => pickerRoot.classList.remove('is-loading'));
        };

        const markSelected = () => pickerRoot.querySelectorAll('.picker-item').forEach((el) => {
            const pos = selected.indexOf(+el.dataset.questionId);
            el.classList.toggle('is-selected', pos !== -1);
            el.setAttribute('aria-checked', pos === -1 ? 'false' : 'true');
            const num = el.querySelector('.picker-pos');
            if (num) num.textContent = pos === -1 ? '' : toFa(pos + 1);
            if (el.dataset.snippet) {
                snippets[+el.dataset.questionId] = el.dataset.snippet.replace(/\s+/g, ' ').trim();
            }
        });

        const toggleItem = (item) => {
            const id = +item.dataset.questionId;
            if (item.dataset.snippet) {
                snippets[id] = item.dataset.snippet.replace(/\s+/g, ' ').trim();
            }
            selected = selected.includes(id)
                ? selected.filter((x) => x !== id)
                : [...selected, id];
            sync();
        };

        // The whole card is the click target; no checkbox anywhere.
        pickerRoot.addEventListener('click', (e) => {
            const pageBtn = e.target.closest('.picker-page');
            if (pageBtn && !pageBtn.disabled) {
                loadPicker(+pageBtn.dataset.page);
                return;
            }
            const item = e.target.closest('.picker-item');
            if (item) toggleItem(item);
        });

        pickerRoot.addEventListener('keydown', (e) => {
            const item = e.target.closest('.picker-item');
            if (item && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                toggleItem(item);
            }
        });

        let searchTimer;
        document.getElementById('picker-search')?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadPicker(1), 350);
        });
        [...difficultyChips, ...corpChips].forEach((chip) => chip.addEventListener('change', () => {
            if (pickerModal.open) loadPicker(1);
        }));
        lessonChips.forEach((chip) => chip.addEventListener('change', () => {
            updateSummary();
            if (pickerModal.open) loadPicker(1);
        }));

        tray?.addEventListener('click', (e) => {
            const li = e.target.closest('.tray-item');
            if (!li) return;
            const id = +li.dataset.id;
            const i = selected.indexOf(id);
            if (e.target.closest('[data-remove]')) selected.splice(i, 1);
            if (e.target.closest('[data-move="up"]') && i > 0) [selected[i - 1], selected[i]] = [selected[i], selected[i - 1]];
            if (e.target.closest('[data-move="down"]') && i < selected.length - 1) [selected[i + 1], selected[i]] = [selected[i], selected[i + 1]];
            sync();
        });

        // Every open re-fetches so the chips always describe the list.
        document.getElementById('open-question-picker')?.addEventListener('click', () => {
            pickerModal.showModal();
            loadPicker(1);
        });

        document.getElementById('picker-done')?.addEventListener('click', () => pickerModal.close());
        document.getElementById('picker-cancel')?.addEventListener('click', () => pickerModal.close());

        sync();
    }

}
// The router calls init() on every render of this page; this fallback only
// boots the page when app.js never made it (so a JS error can't leave a
// dead exam list).
if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}
