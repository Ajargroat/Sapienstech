/**
 * Teacher panel bundle: shared page behaviors for every /teacher screen.
 *
 * - Renders Jalali dates and Persian weekday names over the server's
 *   Gregorian fallback (<time.fa-date> / [data-fa-weekday]), with the same
 *   pinned-UTC Intl approach as student-dashboard.js — stored datetimes are
 *   wall-clock, so formatting them as UTC can never shift the day.
 * - Confirms destructive forms (data-confirm) before submitting.
 * - Drives the schedule dialog: opens for create, and when an edit button
 *   carries per-item data attributes, rewrites the form to the PUT update
 *   route and pre-fills every field.
 * - Drives the assignment create dialog, whose classroom picker follows the
 *   chosen grade.
 * - Clock-face time popups (Material-style dial) for time inputs.
 * - Swatch-based color picker.
 * - CSV import for the schedule table.
 * - Animated dialog open/close (top-right → center).
 */

const STATUS_DIALOG_METHOD = 'PUT';

/* ── Popup manager (reused from consultant-exams.js) ─────────────────── */

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

/* ── Persian digit helpers ──────────────────────────────────────────── */

const toAscii = (value) => String(value)
    .replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
    .replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));

const toFa = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[+d]);

const pad = (n) => String(n).padStart(2, '0');

/* ── Clock-face popup (Material-style dial) ─────────────────────────── */

const parseClock = (raw) => {
    const m = toAscii(raw || '').trim().match(/^(\d{1,2}):(\d{2})$/);
    if (!m || +m[1] > 23 || +m[2] > 59) return null;
    return (+m[1]) * 60 + (+m[2]);
};

function attachClock(input) {
    const host = input?.closest('.popup-field');
    if (!host || host.querySelector('.cpop')) return;
    const btn = host.querySelector('[data-popup="clock"]');

    const R = 78;
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
}

/* ── Date / confirm helpers ──────────────────────────────────────────── */

function initDates(region) {
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

function initConfirms(region) {
    (region || document).querySelectorAll('form[data-confirm]').forEach((form) => {
        if (form.dataset.confirmBound === '1') return;
        form.dataset.confirmBound = '1';
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
}

function setMethod(form, method) {
    let input = form.querySelector('input[name="_method"]');

    if (method) {
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_method';
            form.prepend(input);
        }
        input.value = method;
    } else if (input) {
        input.remove();
    }
}

/* ── Swatch color picker ─────────────────────────────────────────────── */

function initColorPicker(form) {
    const container = form.querySelector('[data-color-picker]');
    if (!container || container.dataset.pickerBound === '1') return;
    container.dataset.pickerBound = '1';

    const hidden = form.querySelector('[data-color-value]');
    const swatches = container.querySelectorAll('.color-swatch');

    swatches.forEach((btn) => {
        btn.addEventListener('click', () => {
            swatches.forEach((b) => b.classList.remove('selected'));
            btn.classList.add('selected');
            if (hidden) hidden.value = btn.dataset.color;
        });
    });
}

/* ── CSV import ──────────────────────────────────────────────────────── */

function initCsvImport(form, dialog) {
    const input = dialog.querySelector('[data-csv-input]');
    if (!input || input.dataset.csvBound === '1') return;
    input.dataset.csvBound = '1';

    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            const text = e.target.result;
            const rows = parseCsv(text);
            if (rows.length) populateFormFromCsv(form, rows[0]);
        };
        reader.readAsText(file);
        input.value = '';
    });
}

function parseCsv(text) {
    const lines = text.trim().split(/\r?\n/);
    const rows = [];
    for (const line of lines) {
        if (!line.trim()) continue;
        // Simple CSV split (no quoted commas for now)
        rows.push(line.split(',').map((c) => c.trim()));
    }
    return rows;
}

function populateFormFromCsv(form, fields) {
    // Expected CSV columns: title, subject, grade, day, start, end, room, color, description
    const map = ['title', 'subject', 'grade', 'day_of_week', 'start_time', 'end_time', 'room', 'color', 'description'];
    fields.forEach((value, i) => {
        if (i >= map.length) return;
        const input = form.querySelector(`[name="${map[i]}"]`);
        if (input) input.value = value;
    });

    // Sync color swatch
    const colorInput = form.querySelector('[data-color-value]');
    if (colorInput && fields[7]) {
        const swatch = form.querySelector(`.color-swatch[data-color="${fields[7]}"]`);
        if (swatch) swatch.click();
    }
}

/* ── Schedule dialog ─────────────────────────────────────────────────── */

function initScheduleDialog(region) {
    const scope = region || document;
    const dialog = scope.querySelector('[data-schedule-dialog]');
    if (!dialog || dialog.dataset.scheduleBound === '1') return;
    dialog.dataset.scheduleBound = '1';

    const form = dialog.querySelector('[data-schedule-form]');
    const title = dialog.querySelector('[data-schedule-title]');
    const fields = ['title', 'subject', 'grade', 'day_of_week', 'start_time', 'end_time', 'room', 'color', 'description'];
    const createRoute = form.action;
    const updateRouteTemplate = form.dataset.updateRoute;

    // Wire color picker and clock popups
    initColorPicker(form);
    dialog.querySelectorAll('.popup-field input').forEach((input) => attachClock(input));
    initCsvImport(form, dialog);

    const fillField = (name, value) => {
        const input = form.querySelector(`[name="${name}"]`);
        if (input) input.value = value ?? '';
    };

    const resetForCreate = () => {
        form.reset();
        form.action = createRoute;
        setMethod(form, null);
        if (title) title.textContent = 'زنگ جدید';
        fillField('color', '#06B6D4');

        // Reset swatch selection
        const firstSwatch = form.querySelector('.color-swatch');
        if (firstSwatch) {
            form.querySelectorAll('.color-swatch').forEach((s) => s.classList.remove('selected'));
            firstSwatch.classList.add('selected');
        }
    };

    const fillForEdit = (button) => {
        const id = button.dataset.scheduleEdit;
        form.action = updateRouteTemplate.replace('__ID__', id);
        setMethod(form, STATUS_DIALOG_METHOD);
        if (title) title.textContent = 'ویرایش زنگ';
        fields.forEach((name) => fillField(name, button.dataset[`edit${name.charAt(0).toUpperCase()}${name.slice(1)}`]));
        const published = form.querySelector('[name="is_published"][value="1"]');
        if (published) published.checked = button.dataset.editPublished === '1';

        // Sync color swatch
        const colorVal = button.dataset.editColor || '#06B6D4';
        const swatch = form.querySelector(`.color-swatch[data-color="${colorVal}"]`);
        if (swatch) {
            form.querySelectorAll('.color-swatch').forEach((s) => s.classList.remove('selected'));
            swatch.classList.add('selected');
        }
        const hiddenColor = form.querySelector('[data-color-value]');
        if (hiddenColor) hiddenColor.value = colorVal;
    };

    // Create buttons may live anywhere on the page (the panel header).
    scope.querySelectorAll('[data-schedule-open]').forEach((button) => {
        button.addEventListener('click', () => {
            resetForCreate();
            dialog.showModal();
        });
    });

    scope.querySelectorAll('[data-schedule-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            fillForEdit(button);
            dialog.showModal();
        });
    });

    const cancel = dialog.querySelector('[data-schedule-cancel]');
    if (cancel) cancel.addEventListener('click', () => dialog.close());

    // Clicking the backdrop closes, matching the app's shared dialog UX.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

    // Form submit: convert Persian digits in time fields back to ASCII
    form.addEventListener('submit', () => {
        form.querySelectorAll('input[inputmode="numeric"]').forEach((input) => {
            input.value = toAscii(input.value);
        });
    });
}

/* ── Assignment create dialog ────────────────────────────────────────── */

function initAssignmentDialog(region) {
    const scope = region || document;
    const dialog = scope.querySelector('[data-assignment-dialog]');
    if (!dialog || dialog.dataset.assignmentBound === '1') return;
    dialog.dataset.assignmentBound = '1';

    const form = dialog.querySelector('[data-assignment-form]');
    const gradeSelect = form.querySelector('[data-grade-select]');
    const classroomSelect = form.querySelector('[data-classroom-select]');
    if (!gradeSelect || !classroomSelect) return;

    // grade => {id: name}. The payload sits beside the dialog rather than
    // inside it, so re-rendering the dialog never re-parses it.
    let classrooms = {};
    try {
        classrooms = JSON.parse(scope.querySelector('[data-classroom-options]')?.dataset.classroomOptions || '{}') || {};
    } catch (error) {
        console.error('[teacher-panel] classroom options', error);
    }

    /* The classroom list only ever offers the classes of the chosen grade,
       and stays disabled until a grade with classes is picked. */
    const syncClassrooms = (preselect = '') => {
        const grade = gradeSelect.value ?? '';
        const options = (grade && classrooms[grade]) || {};
        const ids = Object.keys(options);

        classroomSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = !grade
            ? 'ابتدا پایه را انتخاب کنید'
            : (ids.length ? 'همه کلاس‌های این پایه' : 'کلاسی برای این پایه ثبت نشده');
        classroomSelect.appendChild(placeholder);

        ids.forEach((id) => {
            const option = document.createElement('option');
            option.value = id;
            option.textContent = options[id];
            classroomSelect.appendChild(option);
        });

        classroomSelect.disabled = ids.length === 0;
        classroomSelect.value = ids.includes(String(preselect)) ? String(preselect) : '';
    };

    gradeSelect.addEventListener('change', () => syncClassrooms());

    // Server-filled fields on a failed store: keep what the teacher typed and
    // restore their grade/classroom pairing instead of resetting to blank.
    let serverFilled = dialog.hasAttribute('data-open-on-load');

    const open = () => {
        if (serverFilled) {
            serverFilled = false;
            syncClassrooms(classroomSelect.dataset.selected || '');
        } else {
            form.reset();
            gradeSelect.value = '';
            syncClassrooms();
        }
        dialog.showModal();
    };

    // Create buttons live in the panel header, outside the dialog.
    scope.querySelectorAll('[data-assignment-open]').forEach((button) => {
        button.addEventListener('click', open);
    });

    const cancel = dialog.querySelector('[data-assignment-cancel]');
    if (cancel) cancel.addEventListener('click', () => dialog.close());

    // Clicking the backdrop closes, matching the schedule dialog's UX.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

    if (dialog.hasAttribute('data-open-on-load')) open();
}

/* ── Grid time configuration ─────────────────────────────────────────── */

function initGridConfig(region) {
    const scope = region || document;
    const config = scope.querySelector('[data-schedule-config]');
    const grid = scope.querySelector('[data-schedule-grid]');
    if (!config || !grid || config.dataset.gridBound === '1') return;
    config.dataset.gridBound = '1';

    // Wire clock popups for the config time inputs
    config.querySelectorAll('.popup-field input').forEach((input) => attachClock(input));

    const applyBtn = config.querySelector('[data-grid-apply]');
    if (!applyBtn) return;

    applyBtn.addEventListener('click', () => {
        const startInput = config.querySelector('[data-grid-start]');
        const endInput = config.querySelector('[data-grid-end]');
        const colsInput = config.querySelector('[data-grid-cols]');

        const startMin = parseClock(startInput?.value) ?? 480;
        const endMin = parseClock(endInput?.value) ?? 840;
        const cols = Math.max(1, Math.min(12, parseInt(colsInput?.value, 10) || 6));

        grid.style.setProperty('--grid-start', startMin);
        grid.style.setProperty('--grid-end', endMin);
        grid.style.setProperty('--grid-cols', cols);

        // Rebuild header labels
        rebuildGridHeader(grid, startMin, endMin, cols);
    });
}

function rebuildGridHeader(grid, startMin, endMin, cols) {
    const header = grid.querySelector('.teacher-grid-header');
    if (!header) return;

    // Remove all slot heads except the corner
    header.querySelectorAll('.teacher-grid-slot-head').forEach((el) => el.remove());

    const step = (endMin - startMin) / cols;
    for (let c = 0; c < cols; c += 1) {
        const t = startMin + (c * step);
        const h = Math.floor(t / 60);
        const m = Math.round(t % 60);
        const div = document.createElement('div');
        div.className = 'teacher-grid-slot-head';
        div.innerHTML = `<span dir="ltr">${toFa(`${pad(h)}:${pad(m)}`)}</span>`;
        header.appendChild(div);
    }
}

/* ── Public init ─────────────────────────────────────────────────────── */

export default function init(region = document) {
    initDates(region);
    initConfirms(region);
    initScheduleDialog(region);
    initAssignmentDialog(region);
    initGridConfig(region);
}
