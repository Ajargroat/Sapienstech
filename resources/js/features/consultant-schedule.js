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
};

function init() {
    const root = document.getElementById('schedule-app');
    if (!root) return;

    const config = {
        studentId: root.dataset.studentId,
        csrf: root.dataset.csrf,
        urlItems: root.dataset.urlItems,
        urlStore: root.dataset.urlStore,
        urlUpdateTemplate: root.dataset.urlUpdateTemplate,
        urlDestroyTemplate: root.dataset.urlDestroyTemplate,
        urlCommentsTemplate: root.dataset.urlCommentsTemplate,
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
    };

    let activeMobileDay = 0;
    let events = [];
    let currentWeekStartDate = null;

    const dragState = { isDragging: false, dayIndex: null, startY: null, currentY: null, ghostEl: null };

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
                    title: ev.title,
                    start: `${String(startDateObj.getHours()).padStart(2, '0')}:${String(startDateObj.getMinutes()).padStart(2, '0')}`,
                    end: `${String(endDateObj.getHours()).padStart(2, '0')}:${String(endDateObj.getMinutes()).padStart(2, '0')}`,
                    color: mapHexToTheme(ev.color),
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
            if (!weekStart) scrollToHour(7);
        } catch (err) {
            console.error('Failed to fetch schedule:', err);
            el.weekDisplay.innerText = 'خطا در بارگذاری';
        }
    }

    async function saveEvent() {
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
        const hexColor = COLOR_THEMES[colorThemeKey];

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

        const url = id ? config.urlUpdateTemplate.replace('__ITEM__', id) : config.urlStore;
        const method = id ? 'PUT' : 'POST';

        try {
            const response = await fetch(url, {
                method,
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));

            if (response.ok && result.success) {
                showToast(id ? 'برنامه با موفقیت بروزرسانی شد' : 'برنامه جدید افزوده شد');
                closeModal();
                fetchSchedule(currentWeekStartDate);
            } else {
                alert(`خطا: ${result.error || summarizeErrors(result) || 'خطای ناشناخته'}`);
            }
        } catch (err) {
            console.error('Save failed:', err);
            alert('خطا در ارتباط با سرور');
        }
    }

    function summarizeErrors(result) {
        if (!result || !result.errors) return null;
        return Object.values(result.errors).flat().join(' ');
    }

    async function deleteEvent() {
        if (!confirm('آیا از حذف این برنامه اطمینان دارید؟')) return;

        const id = document.getElementById('event_id').value;
        const url = config.urlDestroyTemplate.replace('__ITEM__', id);

        try {
            const response = await fetch(url, { method: 'DELETE', headers: authHeaders() });
            const result = await response.json().catch(() => ({}));

            if (response.ok && result.success) {
                showToast('برنامه با موفقیت حذف شد');
                closeModal();
                fetchSchedule(currentWeekStartDate);
            } else {
                alert(`خطا: ${result.error || 'خطای ناشناخته'}`);
            }
        } catch (err) {
            console.error('Delete failed:', err);
            alert('خطا در ارتباط با سرور');
        }
    }

    function mapHexToTheme(hex) {
        if (!hex) return 'blue';
        for (const [key, val] of Object.entries(COLOR_THEMES)) {
            if (val === hex) return key;
        }
        return 'blue';
    }

    function navigateWeek(daysOffset) {
        if (!currentWeekStartDate) return;
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

    // --- Drag-to-create (mouse + touch) ---
    function handleMouseDown(e) {
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

            const top = timeToPixels(event.start);
            const height = timeToPixels(event.end) - top;

            const card = document.createElement('div');
            card.className = 'event-card flex flex-col gap-1';

            if (event.item_type === 'student_personal_block') {
                card.style.opacity = '0.8';
                card.style.border = '2px dashed var(--c-border-strong)';
                card.style.backgroundColor = 'var(--c-surface-alt)';
                card.style.color = 'var(--c-muted)';
            } else {
                const accent = COLOR_THEMES[event.color] || COLOR_THEMES.blue;
                card.style.backgroundColor = `color-mix(in srgb, ${accent} 16%, var(--c-surface))`;
                card.style.borderColor = accent;
                card.style.color = 'var(--c-text)';
            }

            card.style.top = `${top}px`;
            card.style.height = `${height}px`;

            let tagsHtml = '';
            if (event.book) tagsHtml += `<span class="flex items-center gap-1 bg-[var(--c-surface)]/70 px-1.5 py-0.5 rounded text-[10px] leading-none"><i data-lucide="book" class="w-3 h-3"></i> ${escapeHtml(event.book)}</span>`;
            if (event.tests) tagsHtml += `<span class="flex items-center gap-1 bg-[var(--c-surface)]/70 px-1.5 py-0.5 rounded text-[10px] leading-none"><i data-lucide="check-square" class="w-3 h-3"></i> ${event.tests} تست</span>`;

            if (height <= 30) {
                card.innerHTML = `
                    <div class="flex items-center gap-2 h-full overflow-hidden truncate pr-4 relative">
                        ${event.is_completed ? '<i data-lucide="check-circle-2" class="w-4 h-4 text-[var(--c-success)] absolute left-1 top-1/2 -translate-y-1/2 bg-[var(--c-surface)] rounded-full"></i>' : ''}
                        <span class="font-bold text-[10px] sm:text-xs shrink-0">${event.start}</span>
                        <span class="font-bold truncate text-[10px] sm:text-xs">${escapeHtml(event.title)}</span>
                    </div>`;
            } else {
                card.innerHTML = `
                    <div class="font-bold text-[10px] sm:text-xs shrink-0 opacity-80 flex justify-between items-center">
                        <span>${event.start} - ${event.end}</span>
                        ${event.is_completed ? '<i data-lucide="check-circle-2" class="w-4 h-4 text-[var(--c-success)] bg-[var(--c-surface)] rounded-full"></i>' : ''}
                    </div>
                    <div class="font-bold text-xs sm:text-sm leading-tight truncate mt-0.5">${escapeHtml(event.title)}</div>
                    ${tagsHtml ? `<div class="flex flex-wrap gap-1 mt-auto pt-1 hidden sm:flex">${tagsHtml}</div>` : ''}`;
            }

            card.onclick = (e) => {
                e.stopPropagation();
                if (event.item_type === 'student_personal_block') {
                    alert(`توضیحات دانش‌آموز:\n\n${event.description || 'بدون توضیح'}`);
                } else {
                    openModalForEdit(event.id);
                }
            };

            col.appendChild(card);
        });

        if (window.lucide) window.lucide.createIcons();
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str ?? '';
        return div.innerHTML;
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

    function setupColorPicker() {
        const btns = document.querySelectorAll('#color-picker button');
        btns.forEach((btn) => {
            btn.onclick = () => {
                btns.forEach((b) => {
                    b.classList.add('border-transparent');
                    b.classList.remove(`border-${b.dataset.color}-500`);
                });
                const color = btn.dataset.color;
                btn.classList.remove('border-transparent');
                btn.classList.add(`border-${color}-500`);
                document.getElementById('color_theme').value = color;
            };
        });
    }

    function resetForm() {
        document.getElementById('event-form').reset();
        document.getElementById('event_id').value = '';
        document.getElementById('btn-delete').classList.add('hidden');
        document.querySelector('#color-picker button[data-color="blue"]').click();
    }

    function openModalForNew(dayIndex, start, end) {
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
        const ev = events.find((e) => e.id === eventId);
        if (!ev) return;

        resetForm();
        document.getElementById('modal-title').innerText = 'ویرایش برنامه';
        document.getElementById('event_id').value = ev.id;
        document.getElementById('title').value = ev.title;
        selectModalDay(ev.day_index);
        document.getElementById('start_time').value = ev.start;
        document.getElementById('end_time').value = ev.end;

        const colorBtn = document.querySelector(`#color-picker button[data-color="${ev.color}"]`);
        if (colorBtn) colorBtn.click();

        document.getElementById('book_name').value = ev.book || '';
        document.getElementById('test_count').value = ev.tests || '';
        document.getElementById('page_count').value = ev.pages || '';
        document.getElementById('description').value = ev.description || '';
        document.getElementById('link_url').value = ev.link || '';

        const statusContainer = document.getElementById('status-container');
        const statusText = document.getElementById('completion-status');
        const commentsSection = document.getElementById('comments-section');
        const commentsList = document.getElementById('comments-list');

        statusContainer.classList.remove('hidden');
        commentsSection.classList.remove('hidden');
        commentsList.innerHTML = '<div class="text-xs text-[var(--c-muted)] text-center py-2">در حال بارگذاری نظرات...</div>';

        statusText.innerHTML = ev.is_completed
            ? `<span class="text-green-600 flex items-center gap-1" title="زمان انجام: ${ev.completion_timestamp || ''}"><i data-lucide="check-circle-2" class="w-4 h-4"></i> انجام شده</span>`
            : '<span class="text-amber-500 flex items-center gap-1"><i data-lucide="clock" class="w-4 h-4"></i> در انتظار</span>';

        fetch(config.urlCommentsTemplate.replace('__ITEM__', eventId), { headers: authHeaders() })
            .then((res) => res.json())
            .then((data) => {
                if (!Array.isArray(data) || !data.length) {
                    commentsList.innerHTML = '<div class="text-xs text-[var(--c-muted)] text-center py-2">هیچ نظری ثبت نشده است.</div>';
                    return;
                }
                commentsList.innerHTML = data.map((c) => `
                    <div class="bg-[var(--c-surface)] border border-[var(--c-border)] p-2 rounded-lg">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-xs font-bold text-primary">${escapeHtml(c.username)}</span>
                            <span class="text-[10px] text-[var(--c-muted)]" dir="ltr">${escapeHtml(c.created_at)}</span>
                        </div>
                        <p class="text-xs text-[var(--c-text)] whitespace-pre-wrap">${escapeHtml(c.comment_text)}</p>
                    </div>`).join('');
            })
            .catch(() => {
                commentsList.innerHTML = '<div class="text-xs text-red-500 text-center py-2">خطا در بارگذاری نظرات</div>';
            });

        document.getElementById('btn-delete').classList.remove('hidden');
        openModal();
        setTimeout(() => { if (window.lucide) window.lucide.createIcons(); }, 50);
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

    // --- Wire up + init ---
    el.addEventButton.addEventListener('click', () => openModalForNew(0, '08:00', '10:00'));
    el.prevWeekBtn.addEventListener('click', () => navigateWeek(7));
    el.nextWeekBtn.addEventListener('click', () => navigateWeek(-7));
    el.modal.addEventListener('click', (e) => { if (e.target === el.modal) closeModal(); });

    if (window.lucide) window.lucide.createIcons();
    buildGrid();
    setupColorPicker();
    setupDayPills();
    fetchSchedule();

    // Exposed for the inline onclick handlers in the Blade view.
    window.ScheduleApp = { closeModal, saveEvent, deleteEvent };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
