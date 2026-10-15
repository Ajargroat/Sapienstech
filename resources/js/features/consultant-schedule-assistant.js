/**
 * Weekly-planner AI assistant.
 *
 * Owns exactly one region of the planner: #assistant-log and the composer
 * beneath it. Nothing here touches the calendar grid, the event modal or the
 * draft tray — and critically, nothing here writes to the schedule.
 *
 * The safety property that shapes this whole module: a suggestion is a
 * *prefill*, never a write. "Apply" hands the suggestion's payload to the
 * planner's existing event dialog, which the consultant then confirms through
 * the normal store/update endpoints. So an assistant mistake costs a wasted
 * click, never a corrupted timetable — and every block still passes the one
 * validation path the rest of the planner uses.
 *
 * That hand-off is why this module talks to the editor through a tiny exposed
 * API (window.plannerAssistantBridge) rather than importing it: two Vite entry
 * points share no module instance, so a direct import would be two copies of
 * the editor's state that silently diverge.
 */

const el = (id) => document.getElementById(id);

/** Suggestions at or above this confidence get an action button. */
const APPLIABLE_CONFIDENCE = 'high';

export default function init() {
    const root = el('schedule-app');
    const panel = el('assistant-panel');

    // Absent panel = the tenant has the assistant switched off. Not an error.
    if (!root || !panel) return;

    const config = {
        studentId: root.dataset.studentId,
        staffId: root.dataset.staffId,
        tenantId: root.dataset.tenantId,
        csrf: root.dataset.csrf,
        urlShow: root.dataset.urlAssistant,
        urlSend: root.dataset.urlAssistantSend,
        urlClear: root.dataset.urlAssistantClear,
        urlHealth: root.dataset.urlAssistantHealth,
        enabled: root.dataset.assistantEnabled === '1',
        emptyText: root.dataset.assistantEmptyText || 'هنوز گفتگویی نیست.',
        genericErrorText: root.dataset.assistantGenericErrorText || 'پاسخ‌گویی ناموفق بود.',
    };

    const ui = {
        toggle: el('assistant-toggle'),
        log: el('assistant-log'),
        input: el('assistant-input'),
        send: el('assistant-send-button'),
        briefing: el('assistant-briefing-button'),
        clear: el('assistant-clear-button'),
        healthBtn: el('assistant-health-button'),
        status: el('assistant-status'),
        statusText: el('assistant-status-text'),
        meter: el('assistant-meter'),
        meterFill: el('assistant-meter-fill'),
        meterText: el('assistant-meter-text'),
    };

    /**
     * In-flight guard. A request token, not a boolean: the same
     * "one owner per token" rule the draft editor learned the hard way — a
     * boolean lets a stale response clear a newer request's busy state.
     */
    let token = 0;
    let busy = false;
    let loaded = false;

    const state = {
        messages: [],
        usage: null,
        health: null,
    };

    // =========================================================================
    // Panel open/close
    // =========================================================================

    function isOpen() {
        return !panel.classList.contains('hidden');
    }

    function openPanel() {
        panel.classList.remove('hidden');
        ui.toggle?.setAttribute('aria-expanded', 'true');

        // Load once, on first open: a consultant who never opens the panel
        // never triggers a provider health probe or a history fetch.
        if (!loaded) {
            loaded = true;
            void loadConversation();
        }
    }

    function closePanel() {
        panel.classList.add('hidden');
        ui.toggle?.setAttribute('aria-expanded', 'false');
    }

    ui.toggle?.addEventListener('click', () => {
        if (isOpen()) closePanel();
        else openPanel();
    });

    // Escape closes the panel, matching the planner's other dismissible
    // surfaces. Ignored while focus is in the composer so Escape can still be
    // used for its usual purpose in a text field.
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !isOpen()) return;
        if (document.activeElement === ui.input) return;
        closePanel();
    });

    // =========================================================================
    // Load / render
    // =========================================================================

    async function loadConversation() {
        setStatus('checking');
        try {
            const response = await fetch(config.urlShow, { headers: authHeaders() });
            const data = await response.json();

            state.messages = data.messages || [];
            state.usage = data.usage || null;
            state.health = data.health || null;

            renderHealth();
            renderMeter();
            renderLog();
        } catch (error) {
            // A failed *load* is reported, never swallowed: a silent failure
            // here reads to the consultant as "the panel is empty", which is
            // indistinguishable from "you have no history".
            setStatus('down');
            renderNotice(config.genericErrorText);
        }
    }

    function renderHealth() {
        const health = state.health;
        if (!health) {
            setStatus('checking');
            return;
        }

        setStatus(health.status, health.ok);
    }

    function setStatus(status, ok = null) {
        if (!ui.status) return;

        const labels = {
            ok: 'آماده',
            down: 'در دسترس نیست',
            unconfigured: 'تنظیم نشده',
            disabled: 'غیرفعال',
            checking: 'در حال بررسی…',
        };

        let resolved = status;
        if (ok === false && status === 'ok') resolved = 'down';
        if (ok === true) resolved = 'ok';

        ui.status.dataset.state = resolved;
        if (ui.statusText) ui.statusText.textContent = labels[resolved] || labels.checking;

        const canAsk = resolved === 'ok';
        if (ui.send) ui.send.disabled = !canAsk || busy;
        if (ui.briefing) ui.briefing.disabled = !canAsk || busy;
    }

    function renderMeter() {
        const usage = state.usage;
        if (!usage || !ui.meterFill || !ui.meterText) return;

        const unlimited = !usage.budget || usage.budget <= 0;
        const percent = unlimited ? 0 : Math.min(100, Number(usage.percent) || 0);

        ui.meterFill.style.width = `${percent}%`;
        ui.meter.dataset.warn = usage.warn ? 'true' : 'false';

        if (unlimited) {
            ui.meterText.textContent = `مصرف این ماه: ${formatNumber(usage.tokens)} توکن (بدون سقف)`;
            return;
        }

        ui.meterText.textContent =
            `مصرف این ماه: ${formatNumber(usage.tokens)} از ${formatNumber(usage.budget)} توکن (${percent}%)`;
    }

    function renderLog() {
        if (!ui.log) return;

        // The renderer OWNS this container exclusively — everything below is
        // rebuilt wholesale, so nothing else may append siblings into it.
        ui.log.innerHTML = '';

        if (!state.messages.length) {
            const empty = document.createElement('p');
            empty.className = 'assistant-empty';
            empty.textContent = config.emptyText;
            ui.log.appendChild(empty);
            return;
        }

        state.messages.forEach((message) => ui.log.appendChild(buildMessage(message)));
        scrollLogToEnd();
    }

    function buildMessage(message) {
        const isUser = message.role === 'user';
        const isError = Boolean(message.error_code);

        const row = document.createElement('div');
        row.className = `assistant-row assistant-row--${isUser ? 'user' : 'assistant'}`;

        const bubble = document.createElement('div');
        bubble.className = 'draft-card assistant-bubble';
        if (isUser) bubble.classList.add('assistant-bubble--user');
        if (isError) bubble.classList.add('assistant-bubble--error');

        const body = document.createElement('p');
        body.className = 'assistant-bubble-body';
        body.textContent = message.content || '';
        bubble.appendChild(body);

        if (isError && message.error_code) {
            const meta = document.createElement('div');
            meta.className = 'assistant-bubble-meta';
            const icon = document.createElement('i');
            icon.className = 'fas fa-triangle-exclamation w-3 h-3';
            icon.setAttribute('aria-hidden', 'true');
            const text = document.createElement('span');
            text.textContent = 'این درخواست انجام نشد.';
            meta.append(icon, text);
            bubble.appendChild(meta);
        }

        // Suggestions only ever hang off an assistant turn.
        const suggestions = Array.isArray(message.suggestions) ? message.suggestions : [];
        if (!isUser && suggestions.length) {
            bubble.appendChild(buildSuggestions(suggestions));
        }

        row.appendChild(bubble);
        return row;
    }

    /**
     * Split suggestions into the actionable lane and the ideas lane.
     *
     * The split is a *capability* boundary, not styling: only `apply: true`
     * cards get a button, and the server sets that flag from confidence. An
     * idea is rendered as inert text, so a low-confidence thought can never
     * mutate a schedule.
     */
    function buildSuggestions(suggestions) {
        const wrap = document.createElement('div');

        const actionable = suggestions.filter((s) => s.apply && s.kind !== 'advice');
        const ideas = suggestions.filter((s) => !(s.apply && s.kind !== 'advice'));

        if (actionable.length) {
            const list = document.createElement('div');
            list.className = 'assistant-suggestions';
            actionable.forEach((suggestion) => list.appendChild(buildSuggestionCard(suggestion)));
            wrap.appendChild(list);
        }

        if (ideas.length) {
            wrap.appendChild(buildIdeasLane(ideas));
        }

        return wrap;
    }

    function buildSuggestionCard(suggestion) {
        const card = document.createElement('div');
        card.className = 'draft-card assistant-suggestion';
        card.dataset.apply = 'true';
        card.dataset.applied = 'false';
        card.dataset.kind = suggestion.kind;

        const head = document.createElement('div');
        head.className = 'assistant-suggestion-head';

        const kind = document.createElement('span');
        kind.className = 'assistant-suggestion-kind';
        kind.textContent = kindLabel(suggestion.kind);
        head.appendChild(kind);

        const when = suggestionWhen(suggestion);
        if (when) {
            const whenEl = document.createElement('span');
            whenEl.className = 'assistant-suggestion-when';
            whenEl.textContent = when;
            head.appendChild(whenEl);
        }

        const colour = suggestionColor(suggestion);
        if (colour) {
            const swatch = document.createElement('span');
            swatch.className = 'assistant-suggestion-swatch';
            // The stored value is the literal hex; painting goes through a
            // token fallback so a themed accent still wins where one exists.
            swatch.style.background = colour;
            head.appendChild(swatch);
        }

        card.appendChild(head);

        const label = document.createElement('p');
        label.className = 'assistant-suggestion-label';
        label.textContent = suggestion.label || '';
        card.appendChild(label);

        const meta = buildSuggestionMeta(suggestion);
        if (meta) card.appendChild(meta);

        if (suggestion.reason) {
            const reason = document.createElement('p');
            reason.className = 'assistant-suggestion-reason';
            reason.textContent = suggestion.reason;
            card.appendChild(reason);
        }

        const actions = document.createElement('div');
        actions.className = 'assistant-suggestion-actions';

        const apply = document.createElement('button');
        apply.type = 'button';
        apply.className = 'draft-btn draft-btn--primary draft-btn--sm';
        apply.innerHTML = '<i class="fas fa-check w-3.5 h-3.5" aria-hidden="true"></i><span>اعمال</span>';
        apply.addEventListener('click', () => void applySuggestion(suggestion, card, apply));

        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'draft-btn draft-btn--quiet draft-btn--sm';
        dismiss.innerHTML = '<i class="fas fa-xmark w-3.5 h-3.5" aria-hidden="true"></i><span>رد کردن</span>';
        dismiss.addEventListener('click', () => {
            card.dataset.applied = 'true';
            actions.remove();
        });

        actions.append(apply, dismiss);
        card.appendChild(actions);

        return card;
    }

    function buildSuggestionMeta(suggestion) {
        const entries = [];

        if (suggestion.kind === 'add_block' && suggestion.payload) {
            if (suggestion.payload.book_name) entries.push(`کتاب: ${suggestion.payload.book_name}`);
            if (suggestion.payload.page_count) entries.push(`${suggestion.payload.page_count} صفحه`);
            if (suggestion.payload.test_count) entries.push(`${suggestion.payload.test_count} آزمون`);
            if (suggestion.payload.description) entries.push(suggestion.payload.description);
        }

        if (suggestion.kind === 'edit_block' && suggestion.changes) {
            if (suggestion.changes.title) entries.push(`عنوان: ${suggestion.changes.title}`);
            if (suggestion.changes.book_name) entries.push(`کتاب: ${suggestion.changes.book_name}`);
            if (suggestion.changes.page_count) entries.push(`${suggestion.changes.page_count} صفحه`);
            if (suggestion.changes.test_count) entries.push(`${suggestion.changes.test_count} آزمون`);
        }

        if (!entries.length) return null;

        const meta = document.createElement('div');
        meta.className = 'assistant-suggestion-meta';
        entries.forEach((entry) => {
            const span = document.createElement('span');
            span.textContent = entry;
            meta.appendChild(span);
        });

        return meta;
    }

    function buildIdeasLane(ideas) {
        const wrap = document.createElement('div');
        wrap.className = 'draft-inset assistant-ideas';

        const head = document.createElement('div');
        head.className = 'assistant-ideas-head';
        // `star`, not `lightbulb`: icons must exist in resources/icons/catalog.json
        // (the tenant icon sets mask these fa-* names), and `lightbulb` is not a
        // catalogued concept. `npm run icons:audit` fails the build otherwise.
        head.innerHTML = '<i class="fas fa-star w-3.5 h-3.5" aria-hidden="true"></i>'
            + '<span>ایده‌های احتمالی — قابل اعمال نیستند</span>';
        wrap.appendChild(head);

        const list = document.createElement('ul');
        list.className = 'assistant-ideas-list';

        ideas.forEach((idea) => {
            const item = document.createElement('li');
            // textContent, always: both fields are model output and a reason
            // may quote consultant- or student-authored schedule text.
            item.textContent = idea.reason ? `${idea.label} — ${idea.reason}` : idea.label;
            list.appendChild(item);
        });

        wrap.appendChild(list);
        return wrap;
    }

    function kindLabel(kind) {
        return {
            add_block: 'افزودن بلوک',
            edit_block: 'ویرایش بلوک',
            week_note: 'یادداشت روز',
            advice: 'ایده',
        }[kind] || 'پیشنهاد';
    }

    function suggestionWhen(suggestion) {
        if (suggestion.kind === 'add_block' && suggestion.payload) {
            const day = DAY_NAMES[suggestion.payload.day_index];
            const start = suggestion.payload.start_time || '';
            const end = suggestion.payload.end_time || '';
            return `${day || ''} ${start}${end ? `–${end}` : ''}`.trim();
        }

        if (suggestion.kind === 'week_note') {
            return DAY_NAMES[suggestion.day_index] || '';
        }

        return '';
    }

    function suggestionColor(suggestion) {
        if (suggestion.kind === 'add_block') return suggestion.payload?.color || '';
        if (suggestion.kind === 'edit_block') return suggestion.changes?.color || '';
        return '';
    }

    // =========================================================================
    // Applying a suggestion — the ONLY path from a card to the schedule
    // =========================================================================

    /**
     * Hand a suggestion to the planner's own dialog.
     *
     * Note what does NOT happen: this never POSTs a block itself. It opens the
     * normal event modal prefilled, so the consultant confirms the slot and the
     * write goes through the existing store/update endpoint with its existing
     * validation. That is deliberate — a second write path would be a second
     * place for validation and tenancy to drift.
     *
     * If the bridge is missing (the editor module failed to initialise) the
     * card says so rather than pretending to work.
     */
    async function applySuggestion(suggestion, card, button) {
        const bridge = window.plannerAssistantBridge;

        if (!bridge || typeof bridge.prefillBlock !== 'function') {
            showToast('برنامه‌ریز آماده نیست؛ صفحه را دوباره بارگذاری کنید.');
            return;
        }

        button.disabled = true;

        try {
            if (suggestion.kind === 'add_block') {
                bridge.prefillBlock(suggestion.payload || {});
            } else if (suggestion.kind === 'edit_block') {
                bridge.prefillEdit(suggestion.item_id, suggestion.changes || {});
            } else if (suggestion.kind === 'week_note') {
                bridge.prefillNote(suggestion.day_index, suggestion.text || '');
            }

            // Consume the affordance: a card that has been handed to the editor
            // must not be able to apply a second time.
            card.dataset.applied = 'true';
            card.querySelector('.assistant-suggestion-actions')?.remove();
        } catch (error) {
            button.disabled = false;
            showToast(config.genericErrorText);
        }
    }

    // =========================================================================
    // Sending
    // =========================================================================

    async function send(mode) {
        if (busy) return;

        const question = mode === 'briefing' ? '' : (ui.input?.value || '').trim();

        if (mode !== 'briefing' && !question) return;

        const myToken = ++token;
        setBusy(true);

        // Show the consultant's own turn immediately. Optimistic on purpose:
        // the server persists it either way, and a spinner where their sentence
        // should be reads as a lost message.
        if (mode !== 'briefing') {
            state.messages.push({ id: `local-${myToken}`, role: 'user', content: question });
            if (ui.input) {
                ui.input.value = '';
                autoGrow(ui.input);
            }
            renderLog();
        }

        try {
            const response = await fetch(config.urlSend, {
                method: 'POST',
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    question,
                    mode,
                    week_start_date: currentWeekStart(),
                }),
            });

            const data = await response.json();

            // A newer request has superseded this one: drop the result rather
            // than render a stale answer under a newer question.
            if (myToken !== token) return;

            if (data.usage) {
                state.usage = data.usage;
                renderMeter();
            }

            if (data.ok) {
                state.messages.push({
                    role: 'assistant',
                    content: data.reply || '',
                    suggestions: data.suggestions || [],
                });
                setStatus('ok', true);
            } else {
                state.messages.push({
                    role: 'assistant',
                    content: data.error_text || config.genericErrorText,
                    error_code: data.error || 'server_error',
                });

                // Reflect a lost connection in the dot, but do not downgrade a
                // per-request throttle or a spent budget into "service down" —
                // those are the service working correctly.
                if (data.error === 'unreachable' || data.error === 'server_error') {
                    setStatus('down');
                }
                if (data.error === 'unconfigured') {
                    setStatus('unconfigured');
                }
            }

            renderLog();
        } catch (error) {
            if (myToken !== token) return;

            // Never a silent failure: "it just errors" was a real bug class in
            // this feature area once already.
            state.messages.push({
                role: 'assistant',
                content: config.genericErrorText,
                error_code: 'server_error',
            });
            setStatus('down');
            renderLog();
        } finally {
            if (myToken === token) setBusy(false);
        }
    }

    function setBusy(value) {
        busy = value;
        if (ui.log) ui.log.setAttribute('aria-busy', value ? 'true' : 'false');
        if (ui.send) ui.send.disabled = value || ui.status?.dataset.state !== 'ok';
        if (ui.briefing) ui.briefing.disabled = value || ui.status?.dataset.state !== 'ok';
        if (ui.input) ui.input.disabled = value;
    }

    ui.send?.addEventListener('click', () => void send('chat'));
    ui.briefing?.addEventListener('click', () => void send('briefing'));

    // Enter sends, Shift+Enter makes a new line: the convention for a chat box.
    ui.input?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey) return;
        event.preventDefault();
        void send('chat');
    });

    ui.input?.addEventListener('input', () => autoGrow(ui.input));

    ui.clear?.addEventListener('click', async () => {
        if (!window.confirm('گفتگوی این دانش‌آموز پاک شود؟')) return;

        try {
            const response = await fetch(config.urlClear, {
                method: 'DELETE',
                headers: authHeaders(),
            });
            const data = await response.json();

            state.messages = [];
            if (data.usage) state.usage = data.usage;
            renderMeter();
            renderLog();
            showToast('گفتگو پاک شد.');
        } catch (error) {
            showToast(config.genericErrorText);
        }
    });

    ui.healthBtn?.addEventListener('click', async () => {
        setStatus('checking');
        try {
            const response = await fetch(config.urlHealth, {
                method: 'POST',
                headers: authHeaders(),
            });
            const data = await response.json();

            state.health = data.health || null;
            if (data.usage) {
                state.usage = data.usage;
                renderMeter();
            }
            renderHealth();
        } catch (error) {
            setStatus('down');
        }
    });

    // =========================================================================
    // Helpers
    // =========================================================================

    function authHeaders(extra = {}) {
        // The token the server ACTUALLY rendered on this component. Reading
        // meta[name="csrf-token"] instead was the exact cause of a 419 in this
        // feature area once: no layout in this project emits that tag.
        return { 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json', ...extra };
    }

    function currentWeekStart() {
        // The planner publishes the week it is showing; fall back to null so
        // the server resolves the current Persian week itself.
        return window.plannerAssistantBridge?.currentWeekStart?.() || null;
    }

    function scrollLogToEnd() {
        if (ui.log) ui.log.scrollTop = ui.log.scrollHeight;
    }

    function autoGrow(field) {
        if (!field) return;
        field.style.height = 'auto';
        field.style.height = `${Math.min(field.scrollHeight, 160)}px`;
    }

    function renderNotice(text) {
        if (!ui.log) return;
        ui.log.innerHTML = '';
        const p = document.createElement('p');
        p.className = 'assistant-empty';
        p.textContent = text;
        ui.log.appendChild(p);
    }

    function showToast(message) {
        // Reuses the planner's own toast, which the editor module owns.
        const toast = el('toast');
        const toastMsg = el('toast-msg');

        if (toast && toastMsg) {
            toastMsg.textContent = message;
            toast.classList.remove('translate-y-20', 'opacity-0');
            setTimeout(() => toast.classList.add('translate-y-20', 'opacity-0'), 3200);
            return;
        }

        window.plannerAssistantBridge?.toast?.(message);
    }

    function formatNumber(value) {
        const number = Number(value) || 0;
        return number.toLocaleString('fa-IR');
    }

    return {
        open: openPanel,
        close: () => {
            // A newer request must not be able to paint into this DOM after the
            // page instance is gone, so the token is bumped and the panel hidden.
            token++;
            busy = false;
            closePanel();
        },
        reload: loadConversation,
    };
}

/** Saturday-first, matching the planner grid and ScheduleWeekNote::DAYS. */
const DAY_NAMES = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
