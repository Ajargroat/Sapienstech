// Consultant weekly schedule editor.
//
// Behavioral reference: consultant_schedule_editor.php / consultant_schedule_script.js
// (60px/hour grid, 15-minute snapping, drag-to-create, Saturday-first
// Persian week, mobile day tabs). Ported to talk to Laravel JSON routes
// instead of api_schedule.php, and to derive event dates from
// week_start_date + day_index + start_time/end_time (validated
// server-side) instead of sending a fully-formed datetime string.

const PIXELS_PER_HOUR = 60;
const SNAP_MINUTES = 15;
const SNAP_PIXELS = (SNAP_MINUTES / 60) * PIXELS_PER_HOUR;

const DAYS = [
    { id: 0, name: 'شنبه' },
    { id: 1, name: 'یکشنبه' },
    { id: 2, name: 'دوشنبه' },
    { id: 3, name: 'سه‌شنبه' },
    { id: 4, name: 'چهارشنبه' },
    { id: 5, name: 'پنجشنبه' },
    { id: 6, name: 'جمعه' },
];

// Accent hex per category. Background/text are derived at render time via
// color-mix() against the current theme's surface/text CSS variables, so
// event chips stay legible in both light and dark mode automatically.
const COLOR_THEMES = {
    blue: '#3b82f6',
    green: '#22c55e',
    yellow: '#f59e0b',
    red: '#ef4444',
    purple: '#a855f7',
    pink: '#ec4899',
    orange: '#fb923c',
    teal: '#2dd4bf',
    lime: '#a3e635',
    cyan: '#22d3ee',
    rose: '#fb7185',
};

const DEFAULT_COLOR = 'blue';

// Swatch key -> the tenant accent token that key resolves to. The swatch
// stylesheet already declares these per [data-color]; this map is the same
// relationship for the cards, so both ends of the system name one ramp.
const ACCENT_VAR = {
    blue: 'blue',
    green: 'emerald',
    yellow: 'amber',
    red: 'red',
    purple: 'violet',
    pink: 'pink',
    orange: 'orange',
    teal: 'teal',
    lime: 'lime',
    cyan: 'cyan',
    rose: 'rose',
};

// Day labels used by the notes card and the copy-source picker.
const DAY_NAMES = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

export default function init() {
    const root = document.getElementById('schedule-app');
    if (!root) return;

    const config = {
        studentId: root.dataset.studentId,
        studentName: root.dataset.studentName,
        tenantId: root.dataset.tenantId,
        // Identifies the *consultant*, so one clipboard follows them from one
        // student's planner to the next.
        staffId: root.dataset.staffId,
        csrf: root.dataset.csrf,
        urlItems: root.dataset.urlItems,
        urlStore: root.dataset.urlStore,
        urlUpdateTemplate: root.dataset.urlUpdateTemplate,
        urlDestroyTemplate: root.dataset.urlDestroyTemplate,
        urlCommentsTemplate: root.dataset.urlCommentsTemplate,
        urlDrafts: root.dataset.urlDrafts,
        urlDraftUpdateTemplate: root.dataset.urlDraftUpdateTemplate,
        urlDraftApplyTemplate: root.dataset.urlDraftApplyTemplate,
        urlNotes: root.dataset.urlNotes,
        urlNotesUpdate: root.dataset.urlNotesUpdate,
        urlSuggestions: root.dataset.urlSuggestions,
        urlCommentStoreTemplate: root.dataset.urlCommentStoreTemplate,
    };

    const el = {
        modal: document.getElementById('event-modal'),
        modalInner: document.getElementById('event-modal').querySelector('div'),
        weekDisplay: document.getElementById('week-date-display'),
        timeAxis: document.getElementById('time-axis'),
        gridContainer: document.getElementById('grid-container'),
        headerContainer: document.getElementById('day-headers-container'),
        mobileTabs: document.getElementById('mobile-day-tabs'),
        scrollArea: document.getElementById('calendar-scroll-area'),
        addEventButton: document.getElementById('add-event-button'),
        prevWeekBtn: document.getElementById('prev-week-btn'),
        nextWeekBtn: document.getElementById('next-week-btn'),
        toast: document.getElementById('toast'),
        toastMsg: document.getElementById('toast-msg'),
        draftPanel: document.getElementById('draft-panel'),
        draftList: document.getElementById('draft-list'),
        draftEditor: document.getElementById('draft-editor'),
        draftName: document.getElementById('draft-name'),
        draftStatus: document.getElementById('draft-status'),
        saveDraftBtn: document.getElementById('save-draft-button'),
        newDraftBtn: document.getElementById('new-draft-button'),
        copyDraftBtn: document.getElementById('copy-draft-button'),
        exitDraftBtn: document.getElementById('exit-draft-button'),

        // Weekly notes.
        notesSection: document.getElementById('week-notes'),
        notesRange: document.getElementById('week-notes-range'),
        notesList: document.getElementById('week-notes-list'),
        notesEmpty: document.getElementById('week-notes-empty'),
        notesStatus: document.getElementById('week-notes-status'),
        notesForm: document.getElementById('week-notes-form'),
        notesFields: document.getElementById('week-notes-fields'),
        notesSave: document.getElementById('week-notes-save'),
        notesCancel: document.getElementById('week-notes-cancel'),
        notesSaved: document.getElementById('week-notes-saved'),
        notesEdit: document.getElementById('week-notes-edit'),
        notesCopy: document.getElementById('week-notes-copy'),
        notesCopyPanel: document.getElementById('week-notes-copy-panel'),
        notesCopySource: document.getElementById('week-notes-copy-source'),
        notesCopyApply: document.getElementById('week-notes-copy-apply'),
        notesCopyCancel: document.getElementById('week-notes-copy-cancel'),

        // Modal comment composer + title suggestions.
        commentForm: document.getElementById('consultant-comment-form'),
        commentBlock: document.getElementById('consultant-comment-block'),
        commentText: document.getElementById('consultant-comment-text'),
        commentSubmit: document.getElementById('consultant-comment-submit'),
        commentsCount: document.getElementById('comments-count'),
        titleInput: document.getElementById('title'),
        titleSuggestions: document.getElementById('title-suggestions'),
        cardTpl: document.getElementById('event-card-tpl'),
    };

    let activeMobileDay = 0;
    let events = [];
    let currentWeekStartDate = null;
    let activeDraft = null;
    let draftDirty = false;
    let busy = false;
    let scheduleReady = false;
    let localId = 0;
    let draftListRequest = 0;

    const dragState = { isDragging: false, dayIndex: null, startY: null, currentY: null, ghostEl: null };

    // --- copy / paste clipboard (in-memory; never touches the server) --------
    // The user asked for "copy an existing block and drop it somewhere else".
    // A pasted block is always a NEW block: nothing about the source item is
    // mutated, so a paste can never damage the original.
    //
    // The clipboard is deliberately NOT per-student: a consultant who copies a
    // block on one student's planner expects to be able to paste it on the
    // next student's, which is the whole point of copying a good block. It is
    // held in localStorage under the consultant's own key so it also survives
    // the page-to-page navigation between students, and it only ever carries
    // the block's *content* -- never an item id -- because the id belongs to the
    // student it came from and must not be reused.
    let clipboardBlock = null;   // { ...content fields, sourceLabel }
    let clipboardOriginId = null;

    // Title -> the colour the consultant habitually uses on it. Filled from the
    // suggestions endpoint so picking a suggestion can also pick the colour.
    const suggestionColors = new Map();

    // --- notes -------------------------------------------------------------
    let notes = { week_start_date: null, days: [] };
    let notesEditing = false;
    let notesDirty = false;
    // The day the copy-panel is filling from (null = a single-day copy).
    let notesCopySourceDay = null;
    let notesRequest = 0;

    // --- Timezone-safe local date helper (no toISOString/UTC shifting) ---
    function getSafeLocalDateStr(baseDateStr, daysOffset = 0) {
        const [year, month, day] = baseDateStr.split('-').map(Number);
        const localDate = new Date(year, month - 1, day);
        localDate.setDate(localDate.getDate() + daysOffset);
        const outY = localDate.getFullYear();
        const outM = String(localDate.getMonth() + 1).padStart(2, '0');
        const outD = String(localDate.getDate()).padStart(2, '0');
        return `${outY}-${outM}-${outD}`;
    }

    function authHeaders(extra = {}) {
        return {
            'X-CSRF-TOKEN': config.csrf,
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
            ...extra,
        };
    }

    async function fetchSchedule(weekStart = null) {
        scheduleReady = false;
        setBusy(true);
        el.weekDisplay.innerText = 'در حال بارگذاری...';
        events = [];
        renderEvents();

        let url = config.urlItems;
        if (weekStart) url += `?week_start_date=${encodeURIComponent(weekStart)}`;

        try {
            const response = await fetch(url, { headers: authHeaders() });
            if (!response.ok) throw new Error('bad response');
            const data = await response.json();

            currentWeekStartDate = data.week_start_date;
            updateWeekDisplay();

            events = data.events.map((ev) => {
                const startDateObj = new Date(ev.start_datetime.replace(' ', 'T'));
                const endDateObj = new Date(ev.end_datetime.replace(' ', 'T'));
                const jsDay = startDateObj.getDay();
                const uiDayIndex = (jsDay + 1) % 7;

                return {
                    id: ev.item_id,
                    day_index: uiDayIndex,
                    // Absolute date, kept so a dragged block can be re-anchored
                    // to the week it actually landed in.
                    start_date: `${startDateObj.getFullYear()}-${String(startDateObj.getMonth() + 1).padStart(2, '0')}-${String(startDateObj.getDate()).padStart(2, '0')}`,
                    title: ev.title,
                    start: `${String(startDateObj.getHours()).padStart(2, '0')}:${String(startDateObj.getMinutes()).padStart(2, '0')}`,
                    end: `${String(endDateObj.getHours()).padStart(2, '0')}:${String(endDateObj.getMinutes()).padStart(2, '0')}`,
                    color: mapHexToTheme(ev.color),
                    rawColor: ev.color,
                    book: ev.book_name,
                    tests: ev.test_count,
                    pages: ev.page_count,
                    description: ev.description,
                    link: ev.link_url,
                    item_type: ev.item_type,
                    is_completed: !!ev.is_completed,
                    completion_timestamp: ev.completion_timestamp,
                };
            });

            renderEvents();
            scheduleReady = true;
            loadSuggestions();
            loadNotes();
            if (!weekStart) scrollToHour(7);
        } catch (err) {
            console.error('Failed to fetch schedule:', err);
            el.weekDisplay.innerText = 'خطا در بارگذاری';
        } finally {
            setBusy(false);
        }
    }

    async function saveEvent() {
        if (busy || !scheduleReady) return;
        if (!document.getElementById('event-form').reportValidity()) return;
        const id = document.getElementById('event_id').value;
        const title = document.getElementById('title').value;
        if (!title) return alert('لطفا عنوان را وارد کنید');

        const dayIndex = parseInt(document.getElementById('day_index').value, 10);
        if (Number.isNaN(dayIndex)) return alert('لطفاً یک روز از هفته را انتخاب کنید.');

        const start = document.getElementById('start_time').value;
        const end = document.getElementById('end_time').value;
        if (timeToPixels(start) >= timeToPixels(end)) {
            return alert('ساعت پایان باید بعد از ساعت شروع باشد');
        }

        const colorThemeKey = document.getElementById('color_theme').value;
        const hexColor = themeHex(colorThemeKey);

        const payload = {
            title,
            week_start_date: currentWeekStartDate,
            day_index: dayIndex,
            start_time: start,
            end_time: end,
            color: hexColor,
            book_name: document.getElementById('book_name').value,
            test_count: document.getElementById('test_count').value || null,
            page_count: document.getElementById('page_count').value || null,
            description: document.getElementById('description').value,
            link_url: document.getElementById('link_url').value || null,
        };

        if (activeDraft) {
            const event = blockToEvent(payload, id || `draft-${++localId}`);
            if (id) events = events.map((existing) => String(existing.id) === id ? event : existing);
            else events.push(event);
            markDraftDirty();
            closeModal();
            renderEvents();
            return;
        }

        const url = id ? config.urlUpdateTemplate.replace('__ITEM__', id) : config.urlStore;
        const method = id ? 'PUT' : 'POST';

        setBusy(true);
        try {
            const response = await fetch(url, {
                method,
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));

            if (response.ok && result.success) {
                showToast(id ? 'برنامه با موفقیت بروزرسانی شد' : 'برنامه جدید افزوده شد');
                // A paste is now spent. Without this the block stays armed for
                // the rest of the session and drops itself into a planner the
                // consultant has long since moved on from.
                if (!id) clearClipboard();
                closeModal();
                await fetchSchedule(currentWeekStartDate);
            } else {
                alert(`خطا: ${result.error || summarizeErrors(result) || 'خطای ناشناخته'}`);
            }
        } catch (err) {
            console.error('Save failed:', err);
            alert('خطا در ارتباط با سرور');
        } finally {
            setBusy(false);
        }
    }

    function summarizeErrors(result) {
        if (!result || !result.errors) return null;
        return Object.values(result.errors).flat().join(' ');
    }

    async function deleteEvent() {
        if (busy || !scheduleReady) return;
        if (!confirm('آیا از حذف این برنامه اطمینان دارید؟')) return;

        const id = document.getElementById('event_id').value;
        if (activeDraft) {
            events = events.filter((event) => String(event.id) !== id);
            markDraftDirty();
            closeModal();
            renderEvents();
            return;
        }
        setBusy(true);
        const url = config.urlDestroyTemplate.replace('__ITEM__', id);

        try {
            const response = await fetch(url, { method: 'DELETE', headers: authHeaders() });
            const result = await response.json().catch(() => ({}));

            if (response.ok && result.success) {
                showToast('برنامه با موفقیت حذف شد');
                closeModal();
                await fetchSchedule(currentWeekStartDate);
            } else {
                alert(`خطا: ${result.error || 'خطای ناشناخته'}`);
            }
        } catch (err) {
            console.error('Delete failed:', err);
            alert('خطا در ارتباط با سرور');
        } finally {
            setBusy(false);
        }
    }

    // Drafts use week-relative blocks; live item IDs and student progress never enter the payload.
    function eventToBlock(event) {
        return {
            title: event.title, day_index: event.day_index,
            start_time: event.start, end_time: event.end,
            color: event.rawColor || themeHex(event.color),
            book_name: event.book || null, page_count: event.pages ?? null,
            test_count: event.tests ?? null, description: event.description || null,
            link_url: event.link || null,
        };
    }

    function blockToEvent(block, id = `draft-${++localId}`) {
        return {
            id, title: block.title, day_index: Number(block.day_index),
            start: block.start_time, end: block.end_time,
            color: mapHexToTheme(block.color), rawColor: block.color,
            book: block.book_name, pages: block.page_count, tests: block.test_count,
            description: block.description, link: block.link_url,
            item_type: 'consultant_event', is_completed: false,
        };
    }

    function setBusy(value) {
        busy = value;
        [el.saveDraftBtn, el.newDraftBtn, el.copyDraftBtn, el.exitDraftBtn].forEach((button) => {
            button.disabled = busy || !scheduleReady;
        });
        el.prevWeekBtn.disabled = el.nextWeekBtn.disabled = busy || !!activeDraft;
        el.draftName.disabled = busy;
        el.draftList.querySelectorAll('button').forEach((button) => {
            button.disabled = busy || !scheduleReady;
        });
    }

    function markDraftDirty() {
        if (!activeDraft) return;
        draftDirty = true;
        el.draftStatus.textContent = 'تغییرات ذخیره نشده';
    }

    function canLeaveDraft() {
        return !draftDirty || confirm('تغییرات پیش‌نویس ذخیره نشده است. ادامه می‌دهید؟');
    }

    function warnUnsavedDraft(event) {
        if (!draftDirty) return;
        event.preventDefault();
        event.returnValue = '';
    }

    function enterDraft(draft) {
        activeDraft = { id: draft.id, name: draft.name };
        draftDirty = false;
        el.draftName.value = draft.name;
        events = draft.blocks.map((block) => blockToEvent(block));
        el.draftEditor.classList.remove('hidden');
        el.draftPanel.classList.add('hidden');
        el.addEventButton.setAttribute('aria-expanded', 'false');
        el.draftStatus.textContent = draft.id ? 'پیش‌نویس ذخیره‌شده' : 'پیش‌نویس جدید';
        setBusy(false);
        renderEvents();
    }

    async function draftRequest(url, method, payload) {
        const response = await fetch(url, {
            method, headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(payload),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.success) {
            throw new Error(result.error || summarizeErrors(result) || 'خطا در ذخیره پیش‌نویس');
        }
        return result;
    }

    async function saveDraft(asCopy = false) {
        if (busy || !scheduleReady) return;
        const name = activeDraft ? el.draftName.value.trim() : prompt('نام پیش‌نویس برنامه هفتگی:')?.trim();
        if (name == null) return;
        if (!name || name.length > 255) return alert('نام پیش‌نویس را وارد کنید (حداکثر ۲۵۵ نویسه).');
        const id = !asCopy && activeDraft?.id;
        const payload = {
            name, week_start_date: currentWeekStartDate,
            blocks: events.filter((event) => event.item_type === 'consultant_event').map(eventToBlock),
        };
        setBusy(true);
        try {
            const result = await draftRequest(
                id ? config.urlDraftUpdateTemplate.replace('__DRAFT__', id) : config.urlDrafts,
                id ? 'PUT' : 'POST', payload,
            );
            enterDraft(result.draft);
            showToast('پیش‌نویس ذخیره شد');
        } catch (error) {
            alert(error.message);
        } finally {
            setBusy(false);
        }
    }

    async function loadDrafts() {
        const request = ++draftListRequest;
        el.draftList.textContent = 'در حال بارگذاری...';
        try {
            const response = await fetch(config.urlDrafts, { headers: authHeaders() });
            if (!response.ok) throw new Error('خطا در بارگذاری پیش‌نویس‌ها. دوباره تلاش کنید.');
            const result = await response.json();
            if (request !== draftListRequest) return;
            el.draftList.replaceChildren();
            if (!result.drafts.length) el.draftList.textContent = 'هنوز پیش‌نویسی ذخیره نشده است.';
            result.drafts.forEach((draft) => {
                const row = document.createElement('div');
                row.className = 'flex flex-wrap items-center gap-3 border border-[var(--c-border)] rounded-lg p-3 text-sm text-[var(--c-text)]';
                const label = document.createElement('span');
                label.className = 'flex-1 break-words';
                label.textContent = `${draft.name} — ${draft.blocks.length.toLocaleString('fa-IR')} بلوک`;
                const edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'text-primary';
                edit.textContent = 'باز کردن';
                edit.addEventListener('click', () => {
                    if (!busy && scheduleReady && canLeaveDraft()) enterDraft(draft);
                });
                const apply = document.createElement('button');
                apply.type = 'button';
                apply.className = 'text-primary';
                apply.textContent = 'افزودن به این هفته';
                apply.addEventListener('click', () => applyDraft(draft));
                row.append(label, edit, apply);
                el.draftList.append(row);
            });
            setBusy(busy);
        } catch (error) {
            if (request === draftListRequest) el.draftList.textContent = error.message;
        }
    }

    async function applyDraft(draft) {
        if (busy || !scheduleReady || !canLeaveDraft()) return;
        if (!draft.blocks.length) return alert('این پیش‌نویس هنوز بلوکی ندارد.');
        if (!confirm(`پیش‌نویس «${draft.name}» به هفته ${el.weekDisplay.innerText} اضافه شود؟ برنامه فعلی حذف نمی‌شود.`)) return;
        setBusy(true);
        try {
            await draftRequest(config.urlDraftApplyTemplate.replace('__DRAFT__', draft.id), 'POST', {
                week_start_date: currentWeekStartDate,
            });
            activeDraft = null;
            draftDirty = false;
            el.draftEditor.classList.add('hidden');
            el.draftPanel.classList.add('hidden');
            el.addEventButton.setAttribute('aria-expanded', 'false');
            await fetchSchedule(currentWeekStartDate);
            showToast('پیش‌نویس به برنامه هفته اضافه شد');
        } catch (error) {
            alert(error.message);
        } finally {
            setBusy(false);
        }
    }

    /**
     * Stored colour -> swatch key.
     *
     * A stored value is either the canonical literal ("#22c55e") or the tenant
     * accent expression the swatch variable resolves to
     * ("var(--c-accent-emerald, #22c55e)"), because stores what the tenant's
     * palette decided rather than a fixed hex. Both forms have to resolve, or a
     * theming tenant would silently fall back to the first swatch and the
     * selected marker would be wrong.
     */
    function mapHexToTheme(hex) {
        if (!hex) return DEFAULT_COLOR;

        const value = String(hex).trim();

        for (const [key, literal] of Object.entries(COLOR_THEMES)) {
            if (literal.toLowerCase() === value.toLowerCase()) return key;
        }

        // var(--c-accent-x, #hex) -- match on the fallback literal inside it.
        const fallback = value.match(/#[0-9a-f]{3,8}/i);
        if (fallback) {
            for (const [key, literal] of Object.entries(COLOR_THEMES)) {
                if (literal.toLowerCase() === fallback[0].toLowerCase()) return key;
            }
        }

        // Last resort: the accent variable's own name. accent_violet -> purple.
        const named = value.match(/--c-accent-([a-z]+)/i);
        if (named) {
            const aliases = { emerald: 'green', amber: 'yellow', violet: 'purple' };
            const candidate = aliases[named[1].toLowerCase()] || named[1].toLowerCase();
            if (COLOR_THEMES[candidate]) return candidate;
        }

        return DEFAULT_COLOR;
    }

    /**
     * The canonical literal for a colour key. This -- not the var() expression
     * -- is what is persisted, so a block created today still renders the same
     * if the tenant re-themes tomorrow.
     */
    function themeHex(key) {
        return COLOR_THEMES[key] || COLOR_THEMES[DEFAULT_COLOR];
    }

    /** The colour of a card: the named CSS chain, never a computed hex. */
    function accentCss(key) {
        return `var(--c-accent-${ACCENT_VAR[key] || 'blue'}, ${themeHex(key)})`;
    }

    function navigateWeek(daysOffset) {
        if (!currentWeekStartDate || busy || activeDraft) return;
        fetchSchedule(getSafeLocalDateStr(currentWeekStartDate, daysOffset));
    }

    function updateWeekDisplay() {
        if (!currentWeekStartDate) return;
        const [y, m, d] = currentWeekStartDate.split('-').map(Number);
        const startDate = new Date(y, m - 1, d);
        const endDate = new Date(y, m - 1, d);
        endDate.setDate(startDate.getDate() + 6);

        const options = { month: 'long', day: 'numeric' };
        el.weekDisplay.innerText = `${startDate.toLocaleDateString('fa-IR', options)} - ${endDate.toLocaleDateString('fa-IR', options)}`;

        DAYS.forEach((day, index) => {
            const colDate = new Date(startDate);
            colDate.setDate(colDate.getDate() + index);
            const label = colDate.toLocaleDateString('fa-IR', { day: 'numeric', month: 'long' });
            const span = document.querySelector(`.day-col-header-${index} .date-subtext`);
            if (span) span.innerText = label;
        });
    }

    function buildGrid() {
        el.timeAxis.innerHTML = '';
        el.gridContainer.innerHTML = '';
        el.headerContainer.innerHTML = '';
        el.mobileTabs.innerHTML = '';

        for (let i = 0; i < 24; i++) {
            const timeDiv = document.createElement('div');
            timeDiv.className = 'absolute w-full text-center text-[10px] sm:text-xs text-[var(--c-muted)] font-medium';
            timeDiv.style.top = `${i * PIXELS_PER_HOUR - 8}px`;
            timeDiv.innerText = `${String(i).padStart(2, '0')}:00`;
            el.timeAxis.appendChild(timeDiv);
        }

        DAYS.forEach((day, index) => {
            const headerDiv = document.createElement('div');
            headerDiv.className = `flex-1 text-center py-2 sm:py-3 font-medium text-xs sm:text-sm text-[var(--c-text)] day-col-header-${index}`;
            headerDiv.innerHTML = `<span>${day.name}</span> <span class="block text-[10px] sm:text-xs text-[var(--c-muted)] mt-0.5 date-subtext">...</span>`;
            el.headerContainer.appendChild(headerDiv);

            const tabBtn = document.createElement('button');
            tabBtn.type = 'button';
            tabBtn.className = `px-4 sm:px-5 py-1.5 sm:py-2 rounded-full text-xs sm:text-sm whitespace-nowrap transition-colors font-medium border ${index === activeMobileDay ? 'bg-primary text-black dark:text-white border-primary shadow-sm' : 'bg-[var(--c-surface)] text-[var(--c-muted)] border-[var(--c-border)]'}`;
            tabBtn.innerText = day.name;
            tabBtn.onclick = () => setActiveMobileDay(index);
            el.mobileTabs.appendChild(tabBtn);

            const colDiv = document.createElement('div');
            colDiv.className = `day-column flex-1 day-col-grid-${index}`;
            colDiv.dataset.dayIndex = index;
            colDiv.addEventListener('mousedown', handleMouseDown);
            colDiv.addEventListener('touchstart', handleTouchStart, { passive: false });
            // Paste anywhere the grid is empty (or on a block: a paste is a new
            // block either way, so nothing is ever overwritten).
            colDiv.addEventListener('dblclick', (e) => {
                if (!clipboardBlock) return;
                const rect = colDiv.getBoundingClientRect();
                pasteBlockIntoForm(index, e.clientY - rect.top);
            });
            el.gridContainer.appendChild(colDiv);
        });

        updateMobileVisibility();
    }

    function setActiveMobileDay(index) {
        activeMobileDay = index;
        buildGrid();
        updateWeekDisplay();
        renderEvents();
    }

    function updateMobileVisibility() {
        const isDesktop = window.innerWidth >= 1024;
        DAYS.forEach((day, index) => {
            const header = document.querySelector(`.day-col-header-${index}`);
            const gridCol = document.querySelector(`.day-col-grid-${index}`);
            const show = isDesktop || index === activeMobileDay;
            if (header) header.classList.toggle('hidden', !show);
            if (gridCol) gridCol.classList.toggle('hidden', !show);
        });
    }
    window.addEventListener('resize', updateMobileVisibility);

    function scrollToHour(hour) {
        el.scrollArea.scrollTop = hour * PIXELS_PER_HOUR - 20;
    }

    /**
     * Ctrl/Cmd + C / Ctrl/Cmd + V on the schedule.
     *
     * Copy takes whatever block is currently open in the dialog (the user
     * clicked it, so that *is* the selection); paste opens the new-block dialog
     * pre-filled and drops it on the active day. Both are ignored while the
     * user is typing in a field, so the browser's own text copy/paste keeps
     * working inside the form.
     */
    function onScheduleKeydown(event) {
        if (!(event.ctrlKey || event.metaKey) || event.altKey) return;
        if (!scheduleReady) return;

        const key = event.key.toLowerCase();
        const typing = event.target.closest?.('input, textarea, select, [contenteditable="true"]');

        if (typing) return;

        if (key === 'c') {
            const openId = document.getElementById('event_id')?.value;

            if (openId && !el.modal.classList.contains('hidden')) {
                const ev = events.find((item) => String(item.id) === String(openId));
                if (ev && ev.item_type !== 'student_personal_block') {
                    event.preventDefault();
                    copyBlockToClipboard(ev);
                    return;
                }
            }

            // Nothing open: copy the first block on the active day, which is
            // what "duplicate this one" almost always means.
            const first = events.find((item) => item.day_index === activeMobileDay
                && item.item_type !== 'student_personal_block');

            if (first) {
                event.preventDefault();
                copyBlockToClipboard(first);
            }

            return;
        }

        if (key === 'v') {
            if (!clipboardBlock) return;
            if (!el.modal.classList.contains('hidden')) return; // the dialog owns paste then
            event.preventDefault();
            pasteIntoFocusedColumn();
        }
    }

    // --- Drag-to-create (mouse + touch) ---
    function handleMouseDown(e) {
        if (busy || !scheduleReady) return;
        if (e.target.closest('.event-card')) return;
        const col = e.currentTarget;
        dragState.isDragging = true;
        dragState.dayIndex = parseInt(col.dataset.dayIndex, 10);
        const rect = col.getBoundingClientRect();
        startDrag(e.clientY - rect.top, col);
        window.addEventListener('mousemove', handleMouseMove);
        window.addEventListener('mouseup', handleMouseUp);
        document.body.style.userSelect = 'none';
    }

    function handleTouchStart(e) {
        if (busy || !scheduleReady) return;
        if (e.target.closest('.event-card')) return;
        if (e.cancelable) e.preventDefault();
        const col = e.currentTarget;
        dragState.isDragging = true;
        dragState.dayIndex = parseInt(col.dataset.dayIndex, 10);
        const rect = col.getBoundingClientRect();
        const touch = e.touches[0];
        startDrag(touch.clientY - rect.top, col);
        window.addEventListener('touchmove', handleTouchMove, { passive: false });
        window.addEventListener('touchend', handleTouchEnd);
    }

    function startDrag(rawY, col) {
        dragState.startY = Math.floor(rawY / SNAP_PIXELS) * SNAP_PIXELS;
        dragState.currentY = dragState.startY + SNAP_PIXELS;
        dragState.ghostEl = document.createElement('div');
        dragState.ghostEl.className = 'drag-ghost flex flex-col justify-center items-center shadow-inner';
        col.appendChild(dragState.ghostEl);
        updateGhostEl();
    }

    function handleMouseMove(e) {
        if (!dragState.isDragging) return;
        const col = document.querySelector(`.day-col-grid-${dragState.dayIndex}`);
        const rect = col.getBoundingClientRect();
        processDragMove(e.clientY - rect.top);
    }

    function handleTouchMove(e) {
        if (!dragState.isDragging) return;
        if (e.cancelable) e.preventDefault();
        const col = document.querySelector(`.day-col-grid-${dragState.dayIndex}`);
        const rect = col.getBoundingClientRect();
        processDragMove(e.touches[0].clientY - rect.top);
    }

    function processDragMove(rawY) {
        rawY = Math.max(0, Math.min(1440, rawY));
        let snappedY = Math.round(rawY / SNAP_PIXELS) * SNAP_PIXELS;
        if (snappedY === dragState.startY) snappedY = dragState.startY + SNAP_PIXELS;
        dragState.currentY = snappedY;
        updateGhostEl();
    }

    function handleMouseUp() {
        endDrag();
        window.removeEventListener('mousemove', handleMouseMove);
        window.removeEventListener('mouseup', handleMouseUp);
    }

    function handleTouchEnd() {
        endDrag();
        window.removeEventListener('touchmove', handleTouchMove);
        window.removeEventListener('touchend', handleTouchEnd);
    }

    function endDrag() {
        if (!dragState.isDragging) return;
        document.body.style.userSelect = '';
        dragState.isDragging = false;
        if (dragState.ghostEl) {
            dragState.ghostEl.remove();
            dragState.ghostEl = null;
        }
        const topY = Math.min(dragState.startY, dragState.currentY);
        const bottomY = Math.max(dragState.startY, dragState.currentY);
        openModalForNew(dragState.dayIndex, pixelsToTime(topY), pixelsToTime(bottomY));
    }

    function updateGhostEl() {
        if (!dragState.ghostEl) return;
        const top = Math.min(dragState.startY, dragState.currentY);
        const height = Math.abs(dragState.currentY - dragState.startY);
        dragState.ghostEl.style.top = `${top}px`;
        dragState.ghostEl.style.height = `${height}px`;
        const start = pixelsToTime(top);
        const end = pixelsToTime(top + height);
        dragState.ghostEl.innerHTML = `<span class="bg-[var(--c-surface)]/80 px-2 py-0.5 rounded backdrop-blur-sm text-[10px] sm:text-xs font-bold whitespace-nowrap">${start} - ${end}</span>`;
    }

    function pixelsToTime(pixels) {
        const totalMins = (pixels / PIXELS_PER_HOUR) * 60;
        const h = Math.floor(totalMins / 60);
        const m = Math.floor(totalMins % 60);
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    }

    function timeToPixels(timeStr) {
        const [h, m] = timeStr.split(':').map(Number);
        return h * PIXELS_PER_HOUR + (m / 60) * PIXELS_PER_HOUR;
    }

    function renderEvents() {
        document.querySelectorAll('.event-card').forEach((e) => e.remove());

        events.forEach((event) => {
            const col = document.querySelector(`.day-col-grid-${event.day_index}`);
            if (!col) return;

            const card = buildEventCard(event);
            if (!card) return;

            col.appendChild(card);

            // A block can be dragged to another day/time. The pill cluster and
            // the delete button are excluded so a tap on a control is never a
            // drag. Personal blocks stay read-only.
            if (card.dataset.itemType === 'consultant_event') {
                card.addEventListener('mousedown', (e) => {
                    if (e.target.closest('.event-card-tool')) return;
                    startBlockDrag(e, event);
                });
                card.addEventListener('touchstart', (e) => {
                    if (e.target.closest('.event-card-tool')) return;
                    startBlockDrag(e, event);
                }, { passive: false });
            }
        });

        applyCopiedMarks();
    }

    /**
     * One card, built from the <template> the Blade view ships.
     *
     * Why a template and not innerHTML here: the card carries Tailwind utility
     * classes (flex flex-col gap-1, the tools cluster, the colour-mix tints).
     * Strings composed in JS are invisible to the Tailwind scanner, so those
     * classes would be purged from the build and the card would lose its
     * layout in production. The template keeps every literal in a scanned file.
     */
    function buildEventCard(event) {
        const tpl = el.cardTpl;
        if (!tpl) return null;

        const card = tpl.content.firstElementChild.cloneNode(true);
        const body = card.querySelector('.event-card-body');
        const tools = card.querySelector('.event-card-tools');

        const top = timeToPixels(event.start);
        const height = timeToPixels(event.end) - top;

        const isPersonal = event.item_type === 'student_personal_block';
        const accent = isPersonal
            ? 'var(--c-border-strong)'
            : accentCss(event.color);

        card.dataset.eventId = String(event.id);
        card.dataset.itemType = event.item_type || 'consultant_event';

        // The accent is DATA (which tag the block is), so it is the one inline
        // custom property a card may set -- exactly how the student portal's
        // .sched-box does it. No chrome value is hardcoded here.
        card.style.setProperty('--accent', accent);
        card.style.top = `${top}px`;
        card.style.height = `${height}px`;

        if (isPersonal) {
            card.style.background = 'var(--c-surface-alt)';
            card.style.border = '2px dashed var(--c-border-strong)';
            card.style.color = 'var(--c-muted)';
            card.style.opacity = '0.8';
            tools?.remove();
        } else {
            // Tint + accent come from the tenant ramp, mixed against the
            // tenant surface, so the chip stays legible in light and dark.
            card.style.background = `color-mix(in srgb, ${accent} 16%, var(--c-surface))`;
            card.style.borderColor = accent;
            card.style.color = 'var(--c-text)';
            card.style.borderRightColor = accent;
        }

        if (event.is_completed) {
            card.classList.add('event-card--done');
        }

        if (body) {
            body.innerHTML = cardBodyHtml(event, height);
        }

        const copyBtn = tools?.querySelector('[data-card-action="copy"]');
        if (copyBtn) {
            copyBtn.setAttribute('aria-pressed', clipboardOriginId === event.id ? 'true' : 'false');
            copyBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                copyBlockToClipboard(event);
            });
        }

        card.addEventListener('click', (e) => {
            if (e.target.closest('.event-card-tool')) return;
            e.stopPropagation();
            if (isPersonal) {
                alert(`توضیحات دانش‌آموز:\n\n${event.description || 'بدون توضیح'}`);
            } else {
                openModalForEdit(event.id);
            }
        });

        return card;
    }

    /** The inner markup of a card, sized to whatever room the block has. */
    function cardBodyHtml(event, height) {
        const done = event.is_completed
            ? '<i class="fas fa-circle-check draft-success-text w-4 h-4 shrink-0" title="تکمیل شد" aria-hidden="true"></i>'
            : '';

        if (height <= 30) {
            return `<div class="flex items-center gap-2 h-full overflow-hidden relative pe-4">
                ${done}
                <span class="font-bold text-[10px] sm:text-xs shrink-0">${escapeHtml(event.start)}</span>
                <span class="font-bold truncate text-[10px] sm:text-xs">${escapeHtml(event.title)}</span>
            </div>`;
        }

        let tagsHtml = '';
        if (event.book) {
            tagsHtml += `<span class="draft-row-meta"><i class="fas fa-book w-3 h-3" aria-hidden="true"></i> ${escapeHtml(event.book)}</span>`;
        }
        if (event.tests) {
            tagsHtml += `<span class="draft-row-meta"><i class="fas fa-list-check w-3 h-3" aria-hidden="true"></i> ${escapeHtml(String(event.tests))} تست</span>`;
        }
        if (event.pages) {
            tagsHtml += `<span class="draft-row-meta"><i class="fas fa-file-lines w-3 h-3" aria-hidden="true"></i> ${escapeHtml(String(event.pages))} صفحه</span>`;
        }

        return `<div class="font-bold text-[10px] sm:text-xs shrink-0 opacity-80 flex justify-between items-center gap-2">
                <span>${escapeHtml(event.start)} - ${escapeHtml(event.end)}</span>
                ${done}
            </div>
            <div class="font-bold text-xs sm:text-sm leading-tight truncate mt-0.5">${escapeHtml(event.title)}</div>
            ${tagsHtml ? `<div class="flex flex-wrap gap-1 mt-auto pt-1 hidden sm:flex">${tagsHtml}</div>` : ''}`;
    }

    /** Mark the copied block and arm every paste surface. */
    /**
     * The clipboard key is per consultant, not per student (see above). The
     * stored value is only ever the block's content, so a stale entry can only
     * ever be pasted as a new block -- never as a mutation of someone else's.
     */
    function clipboardStorageKey() {
        // Tenant first, then the consultant: a consultant who works in two
        // academies must not carry one academy's block into the other's
        // planner, and each keeps their own clipboard.
        return `sapienstech.schedule.clipboard.${config.tenantId || 'anon'}.${config.staffId || 'anon'}`;
    }

    function persistClipboard() {
        try {
            if (!clipboardBlock) {
                window.localStorage.removeItem(clipboardStorageKey());
                return;
            }
            window.localStorage.setItem(clipboardStorageKey(), JSON.stringify(clipboardBlock));
        } catch {
            // Private mode / a full quota: the in-memory clipboard still works,
            // it just will not survive the trip to another student.
        }
    }

    /** Re-arm the clipboard from a previous page (another student, or a reload). */
    function restoreClipboard() {
        if (clipboardBlock) return;

        try {
            const stored = window.localStorage.getItem(clipboardStorageKey());
            if (!stored) return;

            const parsed = JSON.parse(stored);
            // Guard the shape: a hand-edited or half-written entry must be
            // ignored, not crash the planner on load.
            if (!parsed || typeof parsed !== 'object' || typeof parsed.title !== 'string') {
                window.localStorage.removeItem(clipboardStorageKey());
                return;
            }

            clipboardBlock = parsed;
            clipboardOriginId = null; // the origin card is on another student's page
            applyCopiedMarks();
        } catch {
            // Unparseable: forget it rather than retrying on every render.
            try {
                window.localStorage.removeItem(clipboardStorageKey());
            } catch {
                // Nothing more to do.
            }
        }
    }

    function applyCopiedMarks() {
        document.querySelectorAll('.event-card').forEach((card) => {
            const isOrigin = clipboardOriginId !== null && card.dataset.eventId === String(clipboardOriginId);
            const isArmed = Boolean(clipboardBlock);
            card.classList.toggle('event-card--copied', isOrigin && isArmed);
            card.classList.toggle('event-card--source', isOrigin && isArmed);

            const btn = card.querySelector('[data-card-action="copy"]');
            if (btn) {
                btn.classList.toggle('is-on', isOrigin && isArmed);
                btn.setAttribute('aria-pressed', isOrigin && isArmed ? 'true' : 'false');
            }
        });

        // The grid and the notes surface both accept a paste, so both light up
        // with the same class rather than each inventing its own affordance.
        // The grid is only a paste target while a block is on the clipboard.
        el.gridContainer.classList.toggle('paste-armed', Boolean(clipboardBlock));
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str ?? '';
        return div.innerHTML;
    }

    // =====================================================================
    // Copy / paste of blocks
    // ---------------------------------------------------------------------
    // "Copy an existing block and drop it somewhere else" has two front ends:
    // the per-card copy button plus Ctrl+C/Ctrl+V, and drag-to-move (which is
    // the same result while preserving the block's identity when the user
    // wants it). Both end in the same place: a *validated* save, so the
    // server still re-derives the datetimes and nothing is trusted from here.
    // =====================================================================

    /** Snapshot a block onto the in-memory clipboard (no server round-trip). */
    function copyBlockToClipboard(event) {
        if (event.item_type === 'student_personal_block') {
            return; // the student's own blocks are read-only here
        }

        clipboardBlock = {
            title: event.title,
            book: event.book || null,
            pages: event.pages ?? null,
            tests: event.tests ?? null,
            description: event.description || null,
            link: event.link || null,
            color: event.color || DEFAULT_COLOR,
            rawColor: event.rawColor || null,
            // Keep the duration: a copy keeps the block's length and only
            // moves where and when it starts.
            duration: Math.max(SNAP_MINUTES, timeToPixels(event.end) - timeToPixels(event.start)),
            // Which planner it came from, so the dialog can say so. Content
            // only: the item id is never carried across students.
            sourceLabel: config.studentName || null,
            sourceStudentId: config.studentId || null,
        };
        clipboardOriginId = event.id;

        persistClipboard();
        applyCopiedMarks();
        showToast('بلوک کپی شد؛ می‌توانید همین‌جا یا در برنامه دانش‌آموز دیگری جای‌گذاری کنید (Ctrl + V)');
    }

    function clearClipboard() {
        clipboardBlock = null;
        clipboardOriginId = null;
        persistClipboard();
        applyCopiedMarks();
    }

    /** Escape clears an armed clipboard; the user asked for a way out. */
    function onScheduleEscape(event) {
        if (event.key !== 'Escape') return;
        if (!clipboardBlock) return;
        clearClipboard();
        showToast('کپی لغو شد');
    }

    /** Turn a pixel Y inside a column into a snapped {start,end} pair. */
    function snappedRange(rawY, durationPixels) {
        const maxTop = 1440 - durationPixels;
        const top = Math.max(0, Math.min(maxTop, Math.floor(rawY / SNAP_PIXELS) * SNAP_PIXELS));

        return {
            start: pixelsToTime(top),
            end: pixelsToTime(top + durationPixels),
        };
    }

    /**
     * Paste the clipboard onto a day at a Y position.
     *
     * A paste always creates a NEW block. In draft mode it is local (the draft
     * tray owns persistence); outside a draft it is a normal store() call, so
     * the new row goes through exactly the same validation as a hand-created
     * block.
     */
    async function pasteBlockAt(dayIndex, rawY) {
        if (!clipboardBlock || busy || !scheduleReady) return;

        const range = snappedRange(rawY, clipboardBlock.duration);

        await createBlockFromClipboard(dayIndex, range.start, range.end);
    }

    /** Open the normal dialog on the pasted values so nothing is implicit. */
    function pasteBlockIntoForm(dayIndex, rawY) {
        if (!clipboardBlock || busy || !scheduleReady) return;

        const range = snappedRange(rawY, clipboardBlock.duration);

        openModalForNew(dayIndex, range.start, range.end);
        applyClipboardToForm();
        copyBlockToClipboardPlaceholder();
    }

    /** Fill the already-open dialog with the clipboard's contents. */
    function applyClipboardToForm() {
        if (!clipboardBlock) return;

        renderClipboardHint();

        // A paste is a NEW block: clearing the id (and the delete button) is
        // what stops saveEvent() from PUTting over the block that was copied.
        document.getElementById('event_id').value = '';
        document.getElementById('btn-delete').classList.add('hidden');

        document.getElementById('title').value = clipboardBlock.title || '';
        document.getElementById('book_name').value = clipboardBlock.book || '';
        document.getElementById('page_count').value = clipboardBlock.pages ?? '';
        document.getElementById('test_count').value = clipboardBlock.tests ?? '';
        document.getElementById('description').value = clipboardBlock.description || '';
        document.getElementById('link_url').value = clipboardBlock.link || '';

        const colorBtn = document.querySelector(`#color-picker button[data-color="${clipboardBlock.color}"]`);
        if (colorBtn) selectColor(colorBtn.dataset.color);
    }

    /**
     * A copied block that came from a different student needs to say so: the
     * consultant is looking at a dialog that looks like "edit this student's
     * block" while the fields actually hold someone else's. The hint is a data
     * attribute plus a class, never an inline style, and it disappears the
     * moment the block is saved into this student's planner.
     */
    function renderClipboardHint() {
        const hint = document.getElementById('clipboard-hint');
        if (!hint) return;

        const fromOtherStudent = clipboardBlock?.sourceStudentId
            && String(clipboardBlock.sourceStudentId) !== String(config.studentId);

        const show = Boolean(clipboardBlock?.sourceLabel) && fromOtherStudent;

        if (!show) {
            hideClipboardHint();
            return;
        }

        hint.classList.remove('hidden');
        hint.dataset.clipboardState = 'cross-student';
        hint.textContent = `این بلوک از برنامه «${clipboardBlock.sourceLabel}» کپی شده و به عنوان بلوک جدید این دانش‌آموز ذخیره می‌شود.`;
    }

    /**
     * Force the notice away, whatever is on the clipboard. Used by resetForm():
     * a dialog showing a real block is not showing a pasted one, so the notice
     * must not survive into it just because the clipboard is still armed.
     */
    function hideClipboardHint() {
        const hint = document.getElementById('clipboard-hint');
        if (!hint) return;

        hint.classList.add('hidden');
        hint.dataset.clipboardState = '';
        hint.textContent = '';
    }

    function copyBlockToClipboardPlaceholder() {
        // The dialog is now the block's editor; the clipboard stays armed so
        // the same block can be dropped several times in a row.
        applyCopiedMarks();
    }

    /** The timer-free "paste" used by Ctrl+V outside a dialog. */
    function pasteIntoFocusedColumn() {
        if (!clipboardBlock) return false;

        const col = document.querySelector(`.day-col-grid-${activeMobileDay}`)
            || document.querySelector('.day-col-grid-0');

        if (!col) return false;

        // Default to the first free-looking slot: the top of the visible area,
        // snapped. The user then drags the result if they want it elsewhere.
        const baseTop = Math.max(0, Math.round((el.scrollArea.scrollTop + 40) / SNAP_PIXELS) * SNAP_PIXELS);
        pasteBlockIntoForm(parseInt(col.dataset.dayIndex, 10), baseTop);

        return true;
    }

    /** Shared create path for "paste as a new block", used by keyboard paste. */
    async function createBlockFromClipboard(dayIndex, start, end) {
        // Reuse the dialog's save pipeline: fill the form, then let saveEvent()
        // do the talking so validation, toast and refresh stay in one place.
        openModalForNew(dayIndex, start, end);
        applyClipboardToForm();
        await saveEvent();
    }

    /**
     * Move an existing block to another day / time.
     *
     * Moving a block means deleting the old row and creating a new one, because
     * the server owns start/end derivation and a "move" is exactly a new
     * (day_index, start_time, end_time) triple. Wrapping both in the store
     * pipeline keeps the existing invariants (end > start, valid day, tenant
     * scope) instead of adding a bespoke endpoint that could skip them.
     */
    async function moveBlockTo(event, dayIndex, rawY) {
        if (busy || !scheduleReady) return;

        const duration = Math.max(SNAP_MINUTES, timeToPixels(event.end) - timeToPixels(event.start));
        const range = snappedRange(rawY, duration);

        if (dayIndex === event.day_index && range.start === event.start && range.end === event.end) return;

        const payload = {
            title: event.title,
            week_start_date: currentWeekStartDate,
            day_index: dayIndex,
            start_time: range.start,
            end_time: range.end,
            color: event.rawColor || themeHex(event.color),
            book_name: event.book || null,
            test_count: event.tests ?? null,
            page_count: event.pages ?? null,
            description: event.description || null,
            link_url: event.link || null,
        };

        if (activeDraft) {
            // Draft mode is local: patch the in-memory block and re-render.
            events = events.map((existing) => String(existing.id) === String(event.id)
                ? { ...existing, day_index: dayIndex, start: range.start, end: range.end }
                : existing);
            markDraftDirty();
            renderEvents();
            return;
        }

        setBusy(true);
        try {
            const response = await fetch(config.urlUpdateTemplate.replace('__ITEM__', event.id), {
                method: 'PUT',
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(payload),
            });

            if (!response.ok) throw new Error('move failed');

            showToast('بلوک جابه‌جا شد');
            await fetchSchedule(currentWeekStartDate);
        } catch (err) {
            console.error('Move failed:', err);
            alert('خطا در جابه‌جایی بلوک');
            await fetchSchedule(currentWeekStartDate);
        } finally {
            setBusy(false);
        }
    }

    // =====================================================================
    // Drag an EXISTING block
    // =====================================================================
    const blockDrag = { event: null, ghost: null };

    function startBlockDrag(e, event) {
        if (busy || !scheduleReady) return;
        if (activeDraft && event.item_type !== 'consultant_event') return;

        e.stopPropagation();
        e.preventDefault();

        blockDrag.event = event;

        const duration = Math.max(SNAP_MINUTES, timeToPixels(event.end) - timeToPixels(event.start));
        const ghost = document.createElement('div');
        ghost.className = 'drag-ghost flex flex-col justify-center items-center';
        ghost.style.height = `${duration}px`;
        ghost.innerHTML = `<span class="draft-row-meta">${escapeHtml(event.start)} - ${escapeHtml(event.end)}</span>`;
        blockDrag.ghost = ghost;

        document.body.style.userSelect = 'none';
        window.addEventListener('mousemove', onBlockDragMove);
        window.addEventListener('mouseup', onBlockDragEnd);
        window.addEventListener('touchmove', onBlockDragMove, { passive: false });
        window.addEventListener('touchend', onBlockDragEnd);
    }

    function onBlockDragMove(e) {
        if (!blockDrag.event || !blockDrag.ghost) return;
        if (e.cancelable) e.preventDefault();

        const point = e.touches ? e.touches[0] : e;
        const col = columnAtPoint(point.clientX, point.clientY);
        if (!col) return;

        const rect = col.getBoundingClientRect();
        const rawY = point.clientY - rect.top;
        const duration = Math.max(SNAP_MINUTES, timeToPixels(blockDrag.event.end) - timeToPixels(blockDrag.event.start));
        const range = snappedRange(rawY, duration);

        blockDrag.ghost.style.top = `${timeToPixels(range.start)}px`;
        blockDrag.ghost.style.left = '4px';
        blockDrag.ghost.style.right = '4px';
        blockDrag.target = { col, dayIndex: parseInt(col.dataset.dayIndex, 10), rawY };

        if (blockDrag.ghost.parentElement !== col) col.appendChild(blockDrag.ghost);
    }

    function onBlockDragEnd() {
        window.removeEventListener('mousemove', onBlockDragMove);
        window.removeEventListener('mouseup', onBlockDragEnd);
        window.removeEventListener('touchmove', onBlockDragMove);
        window.removeEventListener('touchend', onBlockDragEnd);
        document.body.style.userSelect = '';

        const event = blockDrag.event;
        const target = blockDrag.target;
        blockDrag.ghost?.remove();
        blockDrag.ghost = null;
        blockDrag.target = null;
        blockDrag.event = null;

        if (event && target) moveBlockTo(event, target.dayIndex, target.rawY);
    }

    /** Which day column is under this viewport point? */
    function columnAtPoint(x, y) {
        for (const col of document.querySelectorAll('.day-column')) {
            if (col.classList.contains('hidden')) continue;
            const rect = col.getBoundingClientRect();
            if (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom) return col;
        }

        return null;
    }

    // --- Modal ---
    function setupDayPills() {
        const container = document.getElementById('modal-day-pills');
        container.innerHTML = '';
        DAYS.forEach((day) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg text-xs font-medium border transition day-pill';
            btn.innerText = day.name;
            btn.onclick = () => selectModalDay(day.id);
            container.appendChild(btn);
        });
    }

    function selectModalDay(dayIndex) {
        document.getElementById('day_index').value = dayIndex;
        document.querySelectorAll('.day-pill').forEach((btn, idx) => {
            const active = idx === parseInt(dayIndex, 10);
            btn.classList.toggle('bg-primary', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('border-primary', active);
            btn.classList.toggle('bg-[var(--c-surface-alt)]', !active);
            btn.classList.toggle('text-[var(--c-muted)]', !active);
            btn.classList.toggle('border-[var(--c-border)]', !active);
        });
    }

    /**
     * Colour swatches.
     *
     * The selected state is a single class the stylesheet owns
     * (.color-swatch.selected) plus the role=radio aria state -- it used to be
     * built from `border-${color}-500` utilities that Tailwind never emits, so
     * choosing a colour marked nothing at all. Nothing here composes a class
     * name from data any more.
     */
    function setupColorPicker() {
        document.querySelectorAll('#color-picker button').forEach((btn) => {
            btn.addEventListener('click', () => selectColor(btn.dataset.color));
        });

        const initial = document.getElementById('color_theme').value || DEFAULT_COLOR;
        selectColor(initial);
    }

    function selectColor(colorKey) {
        const key = COLOR_THEMES[colorKey] ? colorKey : DEFAULT_COLOR;

        document.querySelectorAll('#color-picker button').forEach((btn) => {
            const on = btn.dataset.color === key;
            btn.classList.toggle('selected', on);
            btn.setAttribute('aria-checked', on ? 'true' : 'false');
        });

        document.getElementById('color_theme').value = key;
    }

    function resetForm() {
        document.getElementById('event-form').reset();
        document.getElementById('event_id').value = '';
        document.getElementById('btn-delete').classList.add('hidden');
        selectColor(DEFAULT_COLOR);
        // The hint describes the block currently in the form. A reset empties
        // that form, so the notice is forced away -- it is raised again only by
        // applyClipboardToForm(), i.e. only when a paste actually fills it.
        hideClipboardHint();
    }

    function openModalForNew(dayIndex, start, end) {
        if (busy || !scheduleReady) return;
        resetForm();
        document.getElementById('modal-title').innerText = 'افزودن برنامه جدید';
        document.getElementById('status-container').classList.add('hidden');
        document.getElementById('comments-section').classList.add('hidden');
        selectModalDay(dayIndex);
        document.getElementById('start_time').value = start;
        document.getElementById('end_time').value = end;
        openModal();
        setTimeout(() => document.getElementById('title').focus(), 100);
    }

    function openModalForEdit(eventId) {
        if (busy || !scheduleReady) return;
        const ev = events.find((e) => e.id === eventId);
        if (!ev) return;

        resetForm();
        document.getElementById('modal-title').innerText = 'ویرایش برنامه';
        document.getElementById('event_id').value = ev.id;
        document.getElementById('title').value = ev.title;
        selectModalDay(ev.day_index);
        document.getElementById('start_time').value = ev.start;
        document.getElementById('end_time').value = ev.end;

        selectColor(ev.color);

        document.getElementById('book_name').value = ev.book || '';
        document.getElementById('test_count').value = ev.tests || '';
        document.getElementById('page_count').value = ev.pages || '';
        document.getElementById('description').value = ev.description || '';
        document.getElementById('link_url').value = ev.link || '';

        if (activeDraft) {
            document.getElementById('status-container').classList.add('hidden');
            document.getElementById('comments-section').classList.add('hidden');
            document.getElementById('btn-delete').classList.remove('hidden');
            openModal();
            return;
        }

        const statusContainer = document.getElementById('status-container');
        const statusText = document.getElementById('completion-status');
        const commentsSection = document.getElementById('comments-section');
        const commentsList = document.getElementById('comments-list');

        statusContainer.classList.remove('hidden');
        commentsSection.classList.remove('hidden');
        // The composer is a sibling of the thread section (the thread renderer
        // owns #comments-list outright), so its visibility is set here.
        document.getElementById('consultant-comment-block')?.classList.remove('hidden');
        commentsList.replaceChildren(commentStateNode('در حال بارگذاری گفت‌وگو...'));
        if (el.commentsCount) el.commentsCount.textContent = '';

        // The completion pill is built from the tenant's own semantic tokens
        // instead of Tailwind's fixed green/amber, so a tenant whose palette
        // has no "green" still reads a completed block as completed.
        statusText.innerHTML = ev.is_completed
            ? `<span class="draft-success-text inline-flex items-center gap-1">
                    <i class="fas fa-circle-check w-4 h-4" aria-hidden="true"></i>
                    انجام شده
                    ${ev.completion_timestamp ? `<span class="draft-row-meta">${escapeHtml(ev.completion_timestamp)}</span>` : ''}
               </span>`
            : `<span class="draft-warning-text inline-flex items-center gap-1">
                    <i class="fas fa-clock w-4 h-4" aria-hidden="true"></i> در انتظار
               </span>`;

        if (el.commentText) el.commentText.value = '';

        loadComments(eventId);

        document.getElementById('btn-delete').classList.remove('hidden');
        openModal();
    }

    /** One shared empty/loading/error node for the comment thread. */
    function commentStateNode(text, kind = '') {
        const node = document.createElement('div');
        node.className = 'comment-item--empty' + (kind ? ` comment-item--${kind}` : '');
        node.textContent = text;
        return node;
    }

    function loadComments(eventId) {
        const list = document.getElementById('comments-list');
        if (!list) return;

        fetch(config.urlCommentsTemplate.replace('__ITEM__', eventId), { headers: authHeaders() })
            .then((res) => res.json())
            .then((data) => {
                renderComments(list, Array.isArray(data) ? data : []);
            })
            .catch(() => {
                list.replaceChildren(commentStateNode('خطا در بارگذاری گفت‌وگو', 'error'));
            });
    }

    function renderComments(list, comments) {
        if (el.commentsCount) {
            el.commentsCount.textContent = comments.length
                ? `${comments.length.toLocaleString('fa-IR')} پیام`
                : '';
        }

        if (!comments.length) {
            list.replaceChildren(commentStateNode('هنوز پیامی ثبت نشده است.'));
            return;
        }

        list.replaceChildren(...comments.map((c) => {
            const row = document.createElement('div');
            row.className = comments.length ? 'draft-inset comment-item' : 'comment-item';
            if (c.author === 'student') row.classList.add('comment-item--student');

            const head = document.createElement('div');
            head.className = 'comment-item-head';

            const name = document.createElement('span');
            name.className = 'comment-item-name';
            name.textContent = c.username || '';

            const time = document.createElement('span');
            time.className = 'comment-item-time';
            time.dir = 'ltr';
            time.textContent = c.created_at || '';

            head.append(name, time);

            const text = document.createElement('p');
            text.className = 'comment-item-text';
            text.textContent = c.comment_text || '';

            row.append(head, text);

            return row;
        }));
    }

    /**
     * Title suggestions for the block dialog.
     *
     * The pool is this tenant's own past block titles (see
     * StudentScheduleNoteController::suggestions) -- deliberately not a shared
     * list, because the point is to help the consultant reuse their own
     * wording and notice a duplicate before creating it.
     *
     * Sent to the browser through a <datalist>, so the field keeps its native
     * autocomplete behaviour (typing filters, arrow keys pick) with no
     * third-party widget.
     *
     * Each title also carries the colour the consultant habitually uses on it.
     * That colour is stashed on a module-level map as well as on the <option>,
     * because Firefox reports an unset `option.value` in the change event --
     * the lookup is what makes the behaviour the same in every browser.
     */
    async function loadSuggestions() {
        if (!config.urlSuggestions || !el.titleSuggestions) return;

        const studentId = config.studentId;

        try {
            const response = await fetch(config.urlSuggestions, { headers: authHeaders() });
            if (!response.ok) return;

            const data = await response.json();
            if (!Array.isArray(data.suggestions)) return;
            // A slow response from a previous week must not overwrite the
            // current one.
            if (studentId !== config.studentId) return;

            suggestionColors.clear();
            el.titleSuggestions.replaceChildren(...data.suggestions.map((entry) => {
                const option = document.createElement('option');
                option.value = entry.title;
                if (entry.color) suggestionColors.set(entry.title, entry.color);
                if (entry.usage_count > 1) {
                    option.label = `${entry.usage_count.toLocaleString('fa-IR')} بار استفاده شده`;
                }
                return option;
            }));
        } catch {
            // Suggestions are a convenience; a failure must never block the
            // dialog from opening.
        }
    }

    /**
     * If the title now matches a known suggestion, adopt that title's habitual
     * colour. Only ever an assist: an unrecognised title leaves the picker
     * exactly as the user left it.
     */
    function applySuggestedColor(title = el.titleInput?.value) {
        const value = (title || '').trim();
        if (!value) return;

        const stored = suggestionColors.get(value);
        if (!stored) return;

        // The endpoint reports whatever the consultant stored -- today that is
        // the canonical hex, but it goes through the same resolver the edit
        // dialog uses so a `var(--c-accent-x, #hex)` value maps too.
        selectColor(COLOR_THEMES[stored] ? stored : mapHexToTheme(stored));
    }

    /** Save the consultant's reply on the block's thread. */
    async function submitComment() {
        if (busy || !scheduleReady) return;

        const eventId = document.getElementById('event_id').value;
        const text = el.commentText?.value.trim();
        if (!eventId || !text) return;

        el.commentSubmit.disabled = true;

        try {
            const response = await fetch(config.urlCommentStoreTemplate.replace('__ITEM__', eventId), {
                method: 'POST',
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ comment_text: text }),
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok || !result.success) {
                alert(`خطا: ${result.message || summarizeErrors(result) || 'ثبت پیام ناموفق بود'}`);

                return;
            }

            el.commentText.value = '';
            loadComments(eventId);
            showToast('پیام ثبت شد');
        } catch (err) {
            console.error('Comment failed:', err);
            alert('خطا در ارتباط با سرور');
        } finally {
            el.commentSubmit.disabled = false;
        }
    }

    function openModal() {
        el.modal.classList.remove('hidden');
        setTimeout(() => {
            el.modal.classList.remove('opacity-0');
            el.modalInner.classList.remove('scale-95');
        }, 10);
    }

    function closeModal() {
        el.modal.classList.add('opacity-0');
        el.modalInner.classList.add('scale-95');
        setTimeout(() => el.modal.classList.add('hidden'), 300);
    }

    function showToast(msg) {
        el.toastMsg.innerText = msg;
        el.toast.classList.remove('translate-y-20', 'opacity-0');
        setTimeout(() => el.toast.classList.add('translate-y-20', 'opacity-0'), 3000);
    }

    // =====================================================================
    // Weekly notes («توضیحات هفته»)
    // ---------------------------------------------------------------------
    // The consultant writes a short brief per weekday; the student reads it in
    // their portal. Notes are week-scoped, so moving weeks reloads them rather
    // than carrying text across.
    // =====================================================================

    async function loadNotes() {
        if (!config.urlNotes || !el.notesSection) return;

        const request = ++notesRequest;
        setNotesStatus('در حال بارگذاری توضیحات...');

        const url = currentWeekStartDate
            ? `${config.urlNotes}?week_start_date=${encodeURIComponent(currentWeekStartDate)}`
            : config.urlNotes;

        try {
            const response = await fetch(url, { headers: authHeaders() });
            if (!response.ok) throw new Error('bad response');

            const data = await response.json();
            // Only a newer load (another week) may supersede this one. Nothing
            // else in the page is allowed to invalidate a response.
            if (request !== notesRequest) return;

            notes = data;
            notesEditing = false;
            notesDirty = false;
            renderNotes();
        } catch (err) {
            console.error('Failed to load notes:', err);
            if (request === notesRequest) {
                el.notesList.replaceChildren(notesStateNode('خطا در بارگذاری توضیحات'));
            }
        }
    }

    /** Invalidate any in-flight notes load (teardown / page swap). */
    function stopNotes() {
        notesRequest++;
    }

    function notesStateNode(text) {
        const node = document.createElement('p');
        node.className = 'week-notes-hint';
        node.textContent = text;
        return node;
    }

    function notesDays() {
        return Array.isArray(notes?.days) ? notes.days : [];
    }

    /** Paint every surface: range label, read view, edit view, copy view. */
    function renderNotes() {
        if (!el.notesSection) return;

        const days = notesDays();
        const filled = days.filter((day) => day.has_text);

        if (el.notesRange) {
            el.notesRange.textContent = filled.length
                ? `${filled.length.toLocaleString('fa-IR')} روز توضیح دارد`
                : '';
        }

        setNotesStatus('');
        el.notesForm.classList.toggle('hidden', !notesEditing);
        el.notesCopyPanel.classList.add('hidden');
        el.notesList.classList.toggle('hidden', notesEditing);
        el.notesEdit.classList.toggle('hidden', notesEditing);

        if (notesEditing) {
            renderNotesFields();
            return;
        }

        renderNotesCards();
    }

    /** Read view: one card per day that has something to say. */
    function renderNotesCards() {
        const days = notesDays().filter((day) => day.has_text);

        if (!days.length) {
            el.notesList.replaceChildren();
            el.notesEmpty.classList.remove('hidden');
            el.notesEmpty.textContent = 'هنوز برای این هفته توضیحی نوشته نشده است. با «ویرایش توضیحات» برای هر روز یک یادداشت بنویسید تا دانش‌آموز ببیند.';

            return;
        }

        el.notesEmpty.classList.add('hidden');

        el.notesList.replaceChildren(...days.map((day) => {
            const card = document.createElement('div');
            card.className = 'draft-inset p-3';

            const head = document.createElement('div');
            head.className = 'flex items-center justify-between gap-2 mb-1';

            const label = document.createElement('span');
            label.className = 'week-notes-target';
            label.textContent = day.label;

            const time = document.createElement('span');
            time.className = 'comment-item-time';
            time.textContent = day.updated_at || '';

            head.append(label, time);

            const text = document.createElement('p');
            text.className = 'week-notes-text';
            text.textContent = day.text || '';

            card.append(head, text);

            return card;
        }));
    }

    /** Edit view: one textarea per weekday, always all seven. */
    function renderNotesFields() {
        const days = notesDays();

        el.notesFields.replaceChildren(...days.map((day) => {
            const wrap = document.createElement('label');
            wrap.className = 'week-notes-field';

            const caption = document.createElement('span');
            caption.className = 'week-notes-label';
            caption.textContent = day.label;

            const field = document.createElement('textarea');
            field.className = 'draft-field w-full';
            field.rows = 3;
            field.maxLength = 2000;
            field.dataset.dayIndex = String(day.day_index);
            field.value = day.text || '';
            field.placeholder = `توضیح روز ${day.label} برای دانش‌آموز...`;
            field.addEventListener('input', () => {
                notesDirty = true;
                setNotesStatus('تغییرات ذخیره نشده');
            });

            wrap.append(caption, field);

            return wrap;
        }));
    }

    function setNotesStatus(text) {
        if (!el.notesStatus) return;
        el.notesStatus.textContent = text || '';
        el.notesStatus.classList.toggle('hidden', !text);
    }

    function collectNotesPayload() {
        const payload = {};

        el.notesFields.querySelectorAll('textarea[data-day-index]').forEach((field) => {
            payload[field.dataset.dayIndex] = field.value.trim();
        });

        return payload;
    }

    async function saveNotes() {
        if (busy || !scheduleReady || !notesEditing) return;

        setBusy(true);
        setNotesStatus('در حال ذخیره...');

        try {
            const response = await fetch(config.urlNotesUpdate, {
                method: 'PUT',
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    week_start_date: currentWeekStartDate,
                    notes: collectNotesPayload(),
                }),
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok || !result.success) {
                setNotesStatus(`خطا: ${result.message || summarizeErrors(result) || 'ذخیره ناموفق بود'}`);

                return;
            }

            notes = result;
            notesEditing = false;
            notesDirty = false;
            if (el.notesSaved) el.notesSaved.textContent = 'آخرین ذخیره: همین حالا';
            renderNotes();
            showToast('توضیحات هفته ذخیره شد');
        } catch (err) {
            console.error('Save notes failed:', err);
            setNotesStatus('خطا در ارتباط با سرور');
        } finally {
            setBusy(false);
        }
    }

    /**
     * Copy one day's text onto every other day.
     *
     * Kept as an explicit public helper rather than a hidden behaviour, because
     * a week's brief is often nearly identical day to day and retyping it is
     * the consultant's most obvious waste of time.
     */
    function applyNotesCopy() {
        const sourceDay = notesCopySourceDay ?? parseInt(el.notesCopySource.value, 10);
        const source = notesDays().find((day) => day.day_index === sourceDay);
        if (!source) return;

        el.notesFields.querySelectorAll('textarea[data-day-index]').forEach((field) => {
            field.value = source.text || '';
        });

        notesDirty = true;
        setNotesStatus('متن روی همه روزها اعمال شد (ذخیره را بزنید)');
        el.notesCopyPanel.classList.add('hidden');
    }

    // --- Wire up + init ---
    el.addEventButton.addEventListener('click', () => {
        const open = el.draftPanel.classList.contains('hidden');
        el.draftPanel.classList.toggle('hidden', !open);
        el.addEventButton.setAttribute('aria-expanded', String(open));
        if (open) loadDrafts();
    });
    el.saveDraftBtn.addEventListener('click', () => saveDraft());
    el.copyDraftBtn.addEventListener('click', () => saveDraft(true));
    el.newDraftBtn.addEventListener('click', () => {
        if (busy || !scheduleReady || !canLeaveDraft()) return;
        enterDraft({ id: null, name: '', blocks: [] });
        markDraftDirty();
        el.draftName.focus();
    });
    el.exitDraftBtn.addEventListener('click', async () => {
        if (busy || !canLeaveDraft()) return;
        activeDraft = null;
        draftDirty = false;
        el.draftEditor.classList.add('hidden');
        await fetchSchedule(currentWeekStartDate);
    });
    el.draftName.addEventListener('input', markDraftDirty);

    // --- notes wiring ---
    el.notesEdit?.addEventListener('click', () => {
        if (busy || !scheduleReady) return;
        notesEditing = true;
        notesDirty = false;
        renderNotes();
        el.notesFields.querySelector('textarea')?.focus();
    });
    el.notesCancel?.addEventListener('click', () => {
        if (notesDirty && !confirm('تغییرات توضیحات ذخیره نشده است. ادامه می‌دهید؟')) return;
        notesEditing = false;
        notesDirty = false;
        loadNotes();
    });
    el.notesForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        saveNotes();
    });
    el.notesCopy?.addEventListener('click', () => {
        if (busy || !scheduleReady) return;
        if (!notesEditing) {
            notesEditing = true;
            renderNotes();
        }
        const days = notesDays().filter((day) => day.has_text);
        if (!days.length) {
            setNotesStatus('برای کپی، ابتدا متن یک روز را بنویسید و ذخیره کنید.');

            return;
        }
        el.notesCopySource.replaceChildren(...days.map((day) => {
            const option = document.createElement('option');
            option.value = String(day.day_index);
            option.textContent = day.label;
            return option;
        }));
        notesCopySourceDay = days[0].day_index;
        el.notesCopyPanel.classList.remove('hidden');
    });
    el.notesCopyCancel?.addEventListener('click', () => el.notesCopyPanel.classList.add('hidden'));
    el.notesCopyApply?.addEventListener('click', applyNotesCopy);

    // --- comment composer ---
    // No <form> here on purpose (a nested form is not representable in HTML, so
    // the parser would drop it inside #event-form). The button is the action and
    // Ctrl+Enter / Enter in the textarea submits, which is what a form gave us.
    // Picking a suggestion also picks the colour the consultant habitually uses
    // on that title, so the schedule stays coherent without them having to
    // re-decide a colour they already settled on.
    el.titleInput?.addEventListener('input', () => applySuggestedColor());
    el.titleInput?.addEventListener('change', () => applySuggestedColor());

    el.commentSubmit?.addEventListener('click', () => submitComment());
    el.commentText?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submitComment();
        }
    });

    // A clipboard carried over from another student's planner arrives armed, so
    // the consultant does not have to re-copy it after navigating.
    restoreClipboard();

    // --- keyboard: Ctrl/Cmd+C copies the open block, Ctrl/Cmd+V pastes it ---
    // A paste opens the normal "new block" dialog pre-filled and pre-placed, so
    // the consultant confirms the slot instead of it appearing silently.
    document.addEventListener('keydown', onScheduleKeydown);
    document.addEventListener('keydown', onScheduleEscape);

    window.addEventListener('beforeunload', warnUnsavedDraft);
    el.prevWeekBtn.addEventListener('click', () => navigateWeek(7));
    el.nextWeekBtn.addEventListener('click', () => navigateWeek(-7));
    el.modal.addEventListener('click', (e) => { if (e.target === el.modal) closeModal(); });

    if (window.lucide) window.lucide.createIcons();
    buildGrid();
    setupColorPicker();
    setupDayPills();
    fetchSchedule();

    /*
     * Start the AI assistant panel.
     *
     * It is booted from HERE rather than from its own entry chunk because the
     * two must share one module instance: the assistant applies a suggestion by
     * calling into this editor, and two Vite entries would give it a second,
     * diverging copy of `events`/`busy`. Booting it here also guarantees the
     * ordering — window.plannerAssistantBridge below exists before the panel can
     * ever render a card.
     *
     * A failure here is swallowed on purpose: the weekly planner is the thing
     * the consultant actually needs, and a broken assistant must not take the
     * page down with it. import() is dynamic so the assistant's code is only
     * fetched on the page that has the panel.
     */
    let assistantStore = null;
    let assistantTeardown = null;

    if (document.getElementById('assistant-panel')) {
        import('./consultant-schedule-assistant.js')
            .then((module) => {
                assistantStore = module.default();
                assistantTeardown = () => {
                    assistantStore?.close();
                    assistantStore = null;
                };
            })
            .catch(() => {
                assistantStore = null;
            });
    }

    // Exposed for the inline onclick handlers in the Blade view.
    window.ScheduleApp = {
        closeModal, saveEvent, deleteEvent,
        // Re-read the current week (the same call the prev/next buttons make).
        refreshSchedule: () => fetchSchedule(currentWeekStartDate),
    };

    /*
     * The seam the AI assistant uses to hand a suggestion into this editor.
     *
     * It exists because the assistant is a SECOND Vite entry point: two entry
     * chunks share no module instance, so importing this file from there would
     * give the assistant its own copy of `events`, `busy` and the modal state —
     * two editors quietly disagreeing. A small explicit surface over the real
     * instance is the honest fix.
     *
     * Note what is NOT here: no way to create, update or delete a block. The
     * assistant can only *prefill the dialog*, so the consultant still confirms
     * the slot and the write still goes through saveEvent() and the existing
     * validated endpoint. The assistant therefore has no write path at all.
     *
     * Assigned only when the panel is actually on the page, so a tenant with the
     * assistant switched off gets no dead global.
     */
    if (document.getElementById('assistant-panel')) {
        window.plannerAssistantBridge = {
            /** Open the normal "new block" dialog, prefilled from a suggestion. */
            prefillBlock(payload = {}) {
                const day = Number(payload.day_index);
                const start = payload.start_time || '17:00';
                const end = payload.end_time || '18:00';

                if (!Number.isInteger(day) || day < 0 || day > 6) return false;

                openModalForNew(day, start, end);

                // openModalForNew() resets the form, so fill after it, never before.
                if (payload.title) document.getElementById('title').value = payload.title;
                if (payload.book_name) document.getElementById('book_name').value = payload.book_name;
                if (payload.page_count) document.getElementById('page_count').value = payload.page_count;
                if (payload.test_count) document.getElementById('test_count').value = payload.test_count;
                if (payload.description) document.getElementById('description').value = payload.description;
                if (payload.color) selectColor(mapHexToTheme(payload.color) || DEFAULT_COLOR);

                return true;
            },

            /** Open the normal edit dialog for one of this student's blocks. */
            prefillEdit(itemId, changes = {}) {
                if (!itemId) return false;

                openModalForEdit(itemId);

                // openModalForEdit() no-ops when the block is not in `events` (e.g.
                // it belongs to another week). Do not apply a partial edit on top of
                // a dialog that never opened: the consultant would be looking at the
                // wrong block while the form said otherwise.
                if (document.getElementById('event_id').value !== String(itemId)) return false;

                if (changes.title) document.getElementById('title').value = changes.title;
                if (changes.book_name) document.getElementById('book_name').value = changes.book_name;
                if (changes.page_count) document.getElementById('page_count').value = changes.page_count;
                if (changes.test_count) document.getElementById('test_count').value = changes.test_count;
                if (changes.description) document.getElementById('description').value = changes.description;
                if (changes.color) selectColor(mapHexToTheme(changes.color) || DEFAULT_COLOR);

                return true;
            },

            /**
             * Drop a suggested note into the week-notes form and reveal it, so the
             * consultant can edit it and save with the usual button. The notes form
             * is not a dialog, so this opens the form rather than a modal.
             */
            prefillNote(dayIndex, text) {
                if (!el.notesFields) return false;

                const field = el.notesFields.querySelector(`textarea[data-day-index="${Number(dayIndex)}"]`);
                if (!field) return false;

                el.notesForm?.classList.remove('hidden');
                field.value = text || '';
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.focus();

                return true;
            },

            /** The week the planner is showing, so the assistant discusses that one. */
            currentWeekStart() {
                return currentWeekStartDate;
            },
        };
    }

    // Re-entering the schedule page must not stack resize listeners. The
    // listener targets are captured here, at attach time, rather than read back
    // off the globals in the teardown: if this page was replaced by another one
    // the globals may already point at the next instance, and a teardown that
    // threw would leave the old listeners stacked on the old document.
    const listenerWindow = window;
    const listenerDocument = document;
    // Captured at attach time, like the listener targets above: read back off
    // the global at teardown this could already be the *next* instance's bridge.
    // The same self-reference the teardown deletes, so it never has to touch
    // `window` again -- a teardown must not depend on an environment that may
    // already be gone.
    const assistantBridge = window.plannerAssistantBridge;
    const hasBridge = Boolean(assistantBridge);

    return () => {
        listenerWindow.removeEventListener('resize', updateMobileVisibility);
        listenerWindow.removeEventListener('beforeunload', warnUnsavedDraft);
        listenerDocument.removeEventListener('keydown', onScheduleKeydown);
        listenerDocument.removeEventListener('keydown', onScheduleEscape);
        draftListRequest++;
        // Stop a notes load that is still in flight: this page instance is
        // going away, so its response must not paint into a dead DOM.
        stopNotes();
        // Shut the assistant panel down with this instance so its in-flight
        // fetch cannot paint into the next page's DOM.
        assistantTeardown?.();
        // Drop the assistant's seam with THIS instance, so a stray card click
        // after navigation cannot reach a torn-down editor. Guarded on
        // `hasBridge` and reachable only through the captured window, because
        // this runs during teardown, when the DOM may already be gone.
        if (hasBridge && listenerWindow.plannerAssistantBridge === assistantBridge) {
            delete listenerWindow.plannerAssistantBridge;
        }
    };
}

if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}
