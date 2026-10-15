import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { setImmediate as nextTurn } from 'node:timers/promises';
import { JSDOM } from 'jsdom';

const blade = readFileSync(new URL('../../resources/views/consultant/students/schedule.blade.php', import.meta.url), 'utf8');
const fixture = blade.slice(blade.indexOf('<div\n'), blade.lastIndexOf('@vite'))
    .replace(/<script\b[^>]*>[\s\S]*?<\/script>/g, '')
    .replace(/\{\{[\s\S]*?\}\}/g, 'fixture');
const WEEK = '2026-09-12';
const TARGET_WEEK = '2026-09-19';
const ITEMS = '/fixture/items';
const DRAFTS = '/fixture/drafts';
const NOTES = '/fixture/notes';
const SUGGESTIONS = '/fixture/suggestions';

// The notes API always answers with a full seven-day week; the harness mirrors
// that shape so the client never has to build a week it was not given.
const emptyNotes = (week = WEEK) => ({
    week_start_date: week,
    note_count: 0,
    days: ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'].map((label, index) => ({
        day_index: index, label, date: week, text: null, has_text: false, updated_at: null,
    })),
});

const weekNotes = (week = WEEK, texts = {}) => {
    const base = emptyNotes(week);
    base.days = base.days.map((day) => {
        const text = texts[day.day_index] ?? null;
        return { ...day, text, has_text: Boolean(text), updated_at: text ? '2026-09-12 08:00' : null };
    });
    base.note_count = base.days.filter((day) => day.has_text).length;
    return base;
};
const block = {
    title: 'Algebra', day_index: 0, start_time: '09:00', end_time: '10:00',
    color: '#22c55e', book_name: 'Workbook', page_count: 12, test_count: 8,
    description: 'Review chapter 2', link_url: 'https://example.test/lesson',
};
const draft = { id: 41, name: 'Weekly review', blocks: [block], week_start_date: '2026-08-01' };
const liveEvent = {
    item_id: 91, item_type: 'consultant_event', title: block.title,
    start_datetime: `${WEEK} 09:00:00`, end_datetime: `${WEEK} 10:00:00`,
    color: block.color, book_name: block.book_name, page_count: block.page_count,
    test_count: block.test_count, description: block.description, link_url: block.link_url,
    is_completed: true, completion_timestamp: `${WEEK} 10:05:00`,
    comments: [{ comment_text: 'Student progress' }], student_id: 7,
};
const personalEvent = { ...liveEvent, item_id: 92, item_type: 'student_personal_block', title: 'Personal study' };
const schedule = (week = WEEK, events = [liveEvent, personalEvent]) => ({ week_start_date: week, events });
const response = (body, ok = true) => ({ ok, json: async () => structuredClone(body) });
const settle = async () => { await nextTurn(); };

function deferred() {
    let resolve;
    const promise = new Promise((done) => { resolve = done; });
    return { promise, resolve };
}

async function setup(t, { initial = schedule(), initialResponse, notes, suggestions, studentId, studentName, tenantId } = {}) {
    const dom = new JSDOM(fixture, { url: 'https://tenant.test', runScripts: 'dangerously' });
    const { window } = dom;
    // jsdom's localStorage is per-window, but the clipboard deliberately
    // outlives a page -- so every window shares one store. It is cleared once
    // per TEST (not per setup), because opening a second student's planner is
    // exactly what a cross-student test has to observe.
    const store = storageFor(t.name);
    Object.defineProperty(window, 'localStorage', { configurable: true, value: store });
    // jsdom has no layout-backed innerText; production uses it for escaped text and labels.
    Object.defineProperty(window.HTMLElement.prototype, 'innerText', {
        configurable: true,
        get() { return this.textContent; },
        set(value) { this.textContent = value; },
    });
    const get = (id) => window.document.getElementById(id);
    const root = get('schedule-app');
    const urls = {
        urlItems: ITEMS, urlStore: ITEMS, urlUpdateTemplate: `${ITEMS}/__ITEM__`,
        urlDestroyTemplate: `${ITEMS}/__ITEM__`, urlCommentsTemplate: `${ITEMS}/__ITEM__/comments`,
        urlDrafts: DRAFTS, urlDraftUpdateTemplate: `${DRAFTS}/__DRAFT__`,
        urlDraftApplyTemplate: `${DRAFTS}/__DRAFT__/apply`,
        urlNotes: NOTES, urlNotesUpdate: NOTES,
        urlSuggestions: SUGGESTIONS,
        urlCommentStoreTemplate: `${ITEMS}/__ITEM__/comments`,
    };
    for (const [key, value] of Object.entries(urls)) {
        assert.ok(key in root.dataset, `Blade must supply ${key}`);
        root.dataset[key] = value;
    }
    // The planner is one page per student; a test that navigates between two
    // students re-runs setup() with the second student's identity, so both of
    // these must be set explicitly rather than inherited from the fixture.
    root.dataset.studentId = studentId || '7';
    root.dataset.studentName = studentName || 'سارا';
    root.dataset.staffId = '1';
    root.dataset.tenantId = tenantId || '1';
    root.dataset.csrf = 'test-csrf';
    window.sapienstechRouter = {};

    const requests = [];
    const expected = [];
    const unexpected = [];
    const alerts = [];
    const confirms = [];
    const prompts = [];
    const confirmAnswers = [];
    const promptAnswers = [];
    const timers = new Map();
    let timerId = 0;
    let cleanup;
    const globals = {
        window, document: window.document,
        fetch: async (url, options = {}) => {
            const request = {
                url: String(url), method: options.method || 'GET', headers: options.headers,
                body: options.body ? JSON.parse(options.body) : undefined,
            };
            requests.push(request);
            const next = expected.shift();
            if (!next || next.url !== request.url || next.method !== request.method) {
                // The page loads notes + title suggestions alongside the week.
                // A test that is not about them should still see a real answer
                // rather than a thrown error, so those two GETs are answered
                // from a default payload here and only genuinely surprising
                // calls reach the guard below.
                if (next) expected.unshift(next);
                if (request.method === 'GET' && request.url.startsWith(NOTES)) {
                    const week = new URL(request.url, 'https://tenant.test').searchParams.get('week_start_date') || WEEK;
                    return response(notes ?? emptyNotes(week));
                }
                if (request.method === 'GET' && request.url === SUGGESTIONS) {
                    return response(suggestions ?? { suggestions: [], scope: 'tenant' });
                }
                // Opening a block also loads its thread; empty by default.
                if (request.method === 'GET' && /\/fixture\/items\/\d+\/comments$/.test(request.url)) {
                    return response([]);
                }
                // A save refreshes the week it is already on.
                if (request.method === 'GET' && request.url.startsWith(`${ITEMS}?week_start_date=`)) {
                    const week = new URL(request.url, 'https://tenant.test').searchParams.get('week_start_date');
                    return response(schedule(week || WEEK));
                }
                unexpected.push(request);
                throw new Error(`Unexpected request: ${request.method} ${request.url}`);
            }
            if (next.result instanceof Error) throw next.result;
            return next.result;
        },
        localStorage: window.localStorage,
        alert: (message) => alerts.push(message),
        confirm: (message) => { confirms.push(message); return confirmAnswers.shift() ?? true; },
        prompt: (message) => { prompts.push(message); return promptAnswers.length ? promptAnswers.shift() : 'Snapshot'; },
        setTimeout: (callback) => { timers.set(++timerId, callback); return timerId; },
        clearTimeout: (id) => timers.delete(id),
    };
    const originals = new Map(Object.keys(globals).map((key) => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    Object.assign(globalThis, globals);
    Object.assign(window, { alert: globals.alert, confirm: globals.confirm, prompt: globals.prompt });
    const errors = [];
    const relaxed = { unexpected: false };
    window.addEventListener('error', (event) => { errors.push(event.error); event.preventDefault(); });
    // Cleanup must run while THIS window is still installed: the page module
    // closes over the globals it was called with, and a test may have opened a
    // second student's page. So the restore happens inside the same hook,
    // after the page's own teardown has had its say.
    t.after(() => {
        try {
            cleanup?.();
            timers.clear();
            window.close();
            if (!relaxed.unexpected) {
                assert.deepEqual(unexpected, [], 'No unplanned API calls, including live item/comment mutations');
            }
            assert.equal(expected.length, 0, 'All expected requests were made');
            assert.deepEqual(errors, [], 'No uncaught DOM handler errors');
        } finally {
            for (const [key, descriptor] of originals) {
                if (descriptor) Object.defineProperty(globalThis, key, descriptor);
                else delete globalThis[key];
            }
        }
    });
    const expect = (method, url, body, ok = true) => expected.push({ method, url, result: response(body, ok) });
    expected.push({ method: 'GET', url: ITEMS, result: initialResponse || response(initial) });
    const { default: init } = await import('../../resources/js/features/consultant-schedule.js');
    assert.equal(window.ScheduleApp, undefined, 'Router presence prevents automatic initialization');
    cleanup = init();
    assert.equal(typeof cleanup, 'function');
    await settle();
    const click = (id) => get(id).click();
    const input = (id, value) => {
        get(id).value = value;
        get(id).dispatchEvent(new window.Event('input', { bubbles: true }));
    };
    const runTimers = () => {
        const pending = [...timers.values()];
        timers.clear();
        pending.forEach((callback) => callback());
    };
    // jsdom reports every rect as 0x0; the drag code reads rect.top, so give
    // each visible column a real stacked rectangle before a drag is driven.
    const pinColumnRects = () => {
        [...window.document.querySelectorAll('.day-column')].forEach((col, index) => {
            col.getBoundingClientRect = () => ({
                top: 0, left: index * 140, right: (index + 1) * 140, bottom: 1440,
                width: 140, height: 1440, x: index * 140, y: 0,
            });
        });
    };
    const cards = () => [...window.document.querySelectorAll('.event-card')];
    const rows = () => [...get('draft-list').children];
    const openList = async (drafts = [draft]) => {
        expect('GET', DRAFTS, { drafts });
        click('add-event-button');
        await settle();
    };
    const enter = async () => { await openList(); rows()[0].querySelector('button').click(); };
    const unload = () => {
        const event = new window.Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        return event.defaultPrevented;
    };
    return {
        window, get, click, input, cards, rows, openList, enter, unload, runTimers,
        expect, requests, alerts, confirms, prompts, confirmAnswers, promptAnswers,
        urlNotes: NOTES, urlSuggestions: SUGGESTIONS,
        relaxUnexpected() { relaxed.unexpected = true; },
        cardFor(title) { return cards().find((card) => card.textContent.includes(title)); },
        /**
         * Drags on a day column: jsdom has no layout engine, so each column is
         * given a concrete rect first and the drag runs through real mouse
         * events (mousedown on the column, mousemove/mouseup on the window).
         */
        drag(dayIndex, fromY, toY) {
            pinColumnRects();
            const col = window.document.querySelector(`.day-col-grid-${dayIndex}`);
            assert.ok(col, `day column ${dayIndex} exists`);
            const rect = col.getBoundingClientRect();
            col.dispatchEvent(new window.MouseEvent('mousedown', {
                bubbles: true, clientX: rect.left + 10, clientY: rect.top + fromY,
            }));
            window.dispatchEvent(new window.MouseEvent('mousemove', {
                clientX: rect.left + 10, clientY: rect.top + toY,
            }));
            window.dispatchEvent(new window.MouseEvent('mouseup', {
                clientX: rect.left + 10, clientY: rect.top + toY,
            }));
            return col;
        },
        // Presses Ctrl/Cmd + key on the document, the way the real page sees it.
        hotkey(key, target, init = {}) {
            // Resolve against this harness's own window: a test that opened a
            // second student's page must not reach into the first one.
            target = target || window.document.body;
            const event = new window.KeyboardEvent('keydown', {
                key, ctrlKey: true, bubbles: true, cancelable: true, ...init,
            });
            (target || window.document).dispatchEvent(event);
            return event;
        },
        hold(method, url, promise) { expected.push({ method, url, result: promise }); },
        reject(method, url, error) { expected.push({ method, url, result: error }); },
        /** The persisted clipboard entry: what crosses to another student. */
        /** The persisted clipboard entry: what crosses to another student. */
        clipboardStorage(tenant = '1', staff = '1') {
            return window.localStorage.getItem(`sapienstech.schedule.clipboard.${tenant}.${staff}`);
        },
        /** Arms the clipboard on the first live block, exactly as its copy button does. */
        copyFirstBlock() {
            const card = cards()[0];
            card.querySelector('[data-card-action="copy"]').click();
            return card;
        },
    };
}

const hidden = (element) => element.classList.contains('hidden');

// A minimal Storage standing in for the browser store the clipboard persists
// into, one per test so a test can read exactly what crossed over.
const storageFor = (() => {
    const byTest = new Map();

    const make = () => {
        const data = new Map();
        return {
            getItem: (key) => (data.has(String(key)) ? data.get(String(key)) : null),
            setItem: (key, value) => { data.set(String(key), String(value)); },
            removeItem: (key) => { data.delete(String(key)); },
            clear: () => { data.clear(); },
            key: (index) => [...data.keys()][index] ?? null,
            get length() { return data.size; },
        };
    };

    return (testName) => {
        if (!byTest.has(testName)) byTest.set(testName, make());
        return byTest.get(testName);
    };
})();

test('plus opens the draft list, not the event modal; multiple names render as text', async (t) => {
    const h = await setup(t);
    const names = ['<b>Literal draft</b> & notes', 'Second weekly plan'];
    await h.openList(names.map((name, index) => ({ ...draft, id: index + 1, name, blocks: index ? [] : [block] })));
    assert.equal(hidden(h.get('draft-panel')), false);
    assert.equal(h.get('add-event-button').getAttribute('aria-expanded'), 'true');
    assert.equal(hidden(h.get('event-modal')), true);
    assert.equal(h.rows().length, 2);
    h.rows().forEach((row, index) => {
        assert.equal(row.querySelector('span').textContent, `${names[index]} — ${(index ? 0 : 1).toLocaleString('fa-IR')} بلوک`);
        assert.equal(row.querySelectorAll('button').length, 2);
        assert.equal(row.querySelector('b'), null);
    });
    h.click('add-event-button');
    assert.equal(hidden(h.get('draft-panel')), true);
    assert.equal(h.get('add-event-button').getAttribute('aria-expanded'), 'false');
    // The week GET is now followed by the two side-loads: the notes API and
    // the tenant's title suggestions.
    assert.deepEqual(
        h.requests.map(({ method, url }) => [method, url]),
        [['GET', ITEMS], ['GET', SUGGESTIONS], ['GET', `${NOTES}?week_start_date=${WEEK}`], ['GET', DRAFTS]],
    );
});

test('draft list displays loading, empty and HTTP load error states and can retry', async (t) => {
    const h = await setup(t);
    const pending = deferred();
    h.hold('GET', DRAFTS, pending.promise);
    h.click('add-event-button');
    assert.match(h.get('draft-list').textContent, /در حال بارگذاری/);
    pending.resolve(response({ drafts: [] }));
    await settle();
    assert.match(h.get('draft-list').textContent, /هنوز پیش‌نویسی ذخیره نشده/);
    h.click('add-event-button');
    h.expect('GET', DRAFTS, {}, false);
    h.click('add-event-button');
    await settle();
    assert.match(h.get('draft-list').textContent, /خطا در بارگذاری پیش‌نویس/);
    assert.equal(h.get('draft-list').querySelector('button'), null);
    h.click('add-event-button');
    await h.openList();
    assert.equal(h.rows().length, 1);
});

test('saving a live snapshot includes only consultant blocks, without IDs or student progress', async (t) => {
    const h = await setup(t);
    assert.equal(h.cards().length, 2);
    h.promptAnswers.push('  Snapshot  ');
    h.expect('POST', DRAFTS, { success: true, draft: { ...draft, name: 'Snapshot' } });
    h.click('save-draft-button');
    await settle();
    const saved = h.requests.at(-1);
    assert.deepEqual(saved.body, { name: 'Snapshot', week_start_date: WEEK, blocks: [block] });
    assert.equal(saved.headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.equal(saved.headers['Content-Type'], 'application/json');
    assert.equal(h.prompts.length, 1);
    assert.equal(hidden(h.get('draft-editor')), false);
    assert.equal(h.get('draft-name').value, 'Snapshot');
    assert.equal(h.unload(), false);
    assert.equal(h.cards().length, 1);
    assert.equal(h.cards()[0].querySelector('[data-lucide="check-circle-2"]'), null);
});

test('entering a draft and editing, creating and deleting blocks never calls live APIs', async (t) => {
    const h = await setup(t);
    await h.enter();
    assert.equal(hidden(h.get('draft-panel')), true);
    assert.equal(hidden(h.get('draft-editor')), false);
    assert.equal(h.get('prev-week-btn').disabled, true);
    assert.equal(h.get('next-week-btn').disabled, true);
    h.cards()[0].click();
    assert.equal(h.get('title').value, block.title);
    assert.equal(hidden(h.get('status-container')), true);
    assert.equal(hidden(h.get('comments-section')), true);
    h.input('title', 'Edited algebra');
    h.window.document.querySelector('[onclick="ScheduleApp.saveEvent()"]') .click();
    await settle();
    h.runTimers();
    assert.equal(hidden(h.get('event-modal')), true);
    assert.match(h.cards()[0].textContent, /Edited algebra/);
    assert.match(h.get('draft-status').textContent, /ذخیره نشده/);
    assert.equal(h.unload(), true);

    h.drag(1, 660, 720);
    assert.equal(hidden(h.get('event-modal')), false);
    assert.equal(h.get('day_index').value, '1');
    assert.equal(h.get('start_time').value, '11:00');
    assert.equal(h.get('end_time').value, '12:00');
    h.input('title', 'New draft block');
    await h.window.ScheduleApp.saveEvent();
    h.runTimers();
    assert.equal(h.cards().length, 2);
    const added = h.cards().find((card) => card.textContent.includes('New draft block'));
    assert.ok(added);
    added.click();
    h.click('btn-delete');
    await settle();
    h.runTimers();
    assert.equal(h.cards().length, 1);
    assert.match(h.cards()[0].textContent, /Edited algebra/);
    assert.equal(h.confirms.length, 1);
    assert.deepEqual(h.requests.map(({ method, url }) => [method, url]), [
        ['GET', ITEMS],
        ['GET', SUGGESTIONS],
        ['GET', `${NOTES}?week_start_date=${WEEK}`],
        ['GET', DRAFTS],
    ]);
});

test('new draft starts empty and local; saving updates an existing draft while copy creates a new one', async (t) => {
    const h = await setup(t);
    await h.openList();
    h.click('new-draft-button');
    assert.equal(h.cards().length, 0);
    assert.equal(h.get('draft-name').value, '');
    assert.equal(h.unload(), true);
    h.input('draft-name', 'Empty plan');
    h.expect('POST', DRAFTS, { success: true, draft: { ...draft, name: 'Empty plan', blocks: [] } });
    h.click('save-draft-button');
    await settle();
    assert.deepEqual(h.requests.at(-1).body, { name: 'Empty plan', week_start_date: WEEK, blocks: [] });
    assert.equal(h.unload(), false);

    h.input('draft-name', 'Renamed');
    h.expect('PUT', `${DRAFTS}/41`, { success: true, draft: { ...draft, name: 'Renamed', blocks: [] } });
    h.click('save-draft-button');
    await settle();
    assert.equal(h.requests.at(-1).body.name, 'Renamed');
    assert.equal(h.unload(), false);
    h.expect('POST', DRAFTS, { success: true, draft: { ...draft, id: 42, name: 'Renamed', blocks: [] } });
    h.click('copy-draft-button');
    await settle();
    assert.deepEqual(h.requests.at(-1).body, { name: 'Renamed', week_start_date: WEEK, blocks: [] });
    h.input('draft-name', 'Copy renamed');
    h.expect('PUT', `${DRAFTS}/42`, { success: true, draft: { ...draft, id: 42, name: 'Copy renamed', blocks: [] } });
    h.click('save-draft-button');
    await settle();
    assert.equal(h.prompts.length, 0);
});

for (const failure of ['validation', 'network']) {
    test(`${failure} save failure retains unsaved draft name and blocks for retry`, async (t) => {
        const h = await setup(t);
        await h.enter();
        h.cards()[0].click();
        h.input('title', 'Unsaved changes');
        await h.window.ScheduleApp.saveEvent();
        h.runTimers();
        h.input('draft-name', 'Still local');
        if (failure === 'validation') h.expect('PUT', `${DRAFTS}/41`, { errors: { name: ['Name rejected'] } }, false);
        else h.reject('PUT', `${DRAFTS}/41`, new Error('Network unavailable'));
        h.click('save-draft-button');
        await settle();
        const payload = h.requests.at(-1).body;
        assert.deepEqual(h.alerts, [failure === 'validation' ? 'Name rejected' : 'Network unavailable']);
        assert.equal(hidden(h.get('draft-editor')), false);
        assert.equal(h.get('draft-name').value, 'Still local');
        assert.match(h.cards()[0].textContent, /Unsaved changes/);
        assert.match(h.get('draft-status').textContent, /ذخیره نشده/);
        assert.equal(h.unload(), true);
        assert.equal(h.get('save-draft-button').disabled, false);
        h.expect('PUT', `${DRAFTS}/41`, { success: true, draft: { ...draft, name: payload.name, blocks: payload.blocks } });
        h.click('save-draft-button');
        await settle();
        assert.deepEqual(h.requests.at(-1).body, payload);
        assert.equal(h.unload(), false);
    });
}

test('apply uses the selected target week, waits for success, then refreshes that week', async (t) => {
    const h = await setup(t);
    h.expect('GET', `${ITEMS}?week_start_date=${TARGET_WEEK}`, schedule(TARGET_WEEK));
    h.click('prev-week-btn');
    await settle();
    await h.openList();
    const applying = deferred();
    const refreshing = deferred();
    h.hold('POST', `${DRAFTS}/41/apply`, applying.promise);
    const apply = h.rows()[0].querySelectorAll('button')[1];
    apply.click();
    assert.deepEqual(h.requests.at(-1).body, { week_start_date: TARGET_WEEK });
    assert.equal(h.confirms.length, 1);
    assert.ok(h.confirms[0].includes(h.get('week-date-display').innerText));
    const count = h.requests.length;
    apply.dispatchEvent(new h.window.MouseEvent('click'));
    h.click('prev-week-btn');
    assert.equal(h.requests.length, count, 'No duplicate apply or early refresh');
    h.hold('GET', `${ITEMS}?week_start_date=${TARGET_WEEK}`, refreshing.promise);
    applying.resolve(response({ success: true }));
    await settle();
    assert.equal(h.requests.at(-1).url, `${ITEMS}?week_start_date=${TARGET_WEEK}`);
    assert.equal(h.get('save-draft-button').disabled, true);
    refreshing.resolve(response(schedule(TARGET_WEEK, [{ ...liveEvent, title: 'Applied block' }])));
    await settle();
    assert.match(h.cards()[0].textContent, /Applied block/);
    assert.equal(hidden(h.get('draft-panel')), true);
    assert.equal(hidden(h.get('draft-editor')), true);
    assert.equal(h.get('add-event-button').getAttribute('aria-expanded'), 'false');
    assert.equal(h.get('save-draft-button').disabled, false);
});

test('pending draft save blocks duplicate save/copy, edits, exit and week navigation', async (t) => {
    const h = await setup(t);
    await h.enter();
    h.input('draft-name', 'Pending');
    const saving = deferred();
    h.hold('PUT', `${DRAFTS}/41`, saving.promise);
    h.click('save-draft-button');
    const count = h.requests.length;
    for (const id of ['save-draft-button', 'copy-draft-button', 'exit-draft-button', 'new-draft-button', 'prev-week-btn', 'next-week-btn']) {
        assert.equal(h.get(id).disabled, true, id);
        // Dispatch bypasses native disabled click suppression to exercise handler guards too.
        h.get(id).dispatchEvent(new h.window.MouseEvent('click'));
    }
    assert.equal(h.get('draft-name').disabled, true);
    h.cards()[0].click();
    await h.window.ScheduleApp.saveEvent();
    await h.window.ScheduleApp.deleteEvent();
    assert.equal(hidden(h.get('event-modal')), true);
    assert.equal(h.requests.length, count);
    assert.equal(h.confirms.length, 0);
    saving.resolve(response({ success: true, draft: { ...draft, name: 'Pending' } }));
    await settle();
    assert.equal(h.get('save-draft-button').disabled, false);
    assert.equal(h.get('draft-name').disabled, false);
    assert.equal(h.unload(), false);
});

test('canceling dirty exit preserves local work; confirmed exit reloads the live schedule', async (t) => {
    const h = await setup(t);
    await h.enter();
    h.input('draft-name', 'Do not lose this');
    h.confirmAnswers.push(false);
    const count = h.requests.length;
    h.click('exit-draft-button');
    await settle();
    assert.equal(h.requests.length, count);
    assert.equal(h.confirms.length, 1);
    assert.match(h.confirms[0], /ذخیره نشده/);
    assert.equal(hidden(h.get('draft-editor')), false);
    assert.equal(h.get('draft-name').value, 'Do not lose this');
    assert.equal(h.cards().length, 1);
    assert.equal(h.unload(), true);
    h.confirmAnswers.push(true);
    h.expect('GET', `${ITEMS}?week_start_date=${WEEK}`, schedule());
    h.click('exit-draft-button');
    await settle();
    assert.equal(hidden(h.get('draft-editor')), true);
    assert.equal(h.cards().length, 2);
    assert.equal(h.unload(), false);
    assert.equal(h.get('prev-week-btn').disabled, false);
});

const GET_WEEK = `${ITEMS}?week_start_date=${WEEK}`;
const GET_TARGET = `${ITEMS}?week_start_date=${TARGET_WEEK}`;

// ---------------------------------------------------------------------------
// New behaviour: colour tags, copy/paste, title suggestions, weekly notes.
// ---------------------------------------------------------------------------

test('the colour picker renders eleven tags and marks exactly the chosen one', async (t) => {
    const h = await setup(t);
    const swatches = () => [...h.window.document.querySelectorAll('#color-picker button')];
    assert.equal(swatches().length, 11);

    // The reported bug: choosing a colour marked nothing at all.
    const marked = () => swatches().filter((b) => b.classList.contains('selected'));
    assert.deepEqual(marked().map((b) => b.dataset.color), ['blue']);
    assert.equal(marked()[0].getAttribute('aria-checked'), 'true');

    const teal = swatches().find((b) => b.dataset.color === 'teal');
    teal.click();
    assert.deepEqual(marked().map((b) => b.dataset.color), ['teal']);
    assert.equal(h.get('color_theme').value, 'teal');
    assert.equal(teal.getAttribute('aria-checked'), 'true');
    assert.equal(swatches().find((b) => b.dataset.color === 'blue').getAttribute('aria-checked'), 'false');

    // Opening an existing block re-marks its own colour, never the default.
    h.cardFor(block.title).click();
    assert.equal(h.get('color_theme').value, 'green');
    assert.deepEqual(marked().map((b) => b.dataset.color), ['green']);
});

test('a new tag is persisted as the canonical hex, never as a var() expression', async (t) => {
    const h = await setup(t);
    // 11:00-12:00 on Sunday(1), the way a user drags it out.
    h.drag(1, 660, 720);
    h.input('title', 'Chemistry');
    h.window.document.querySelector('#color-picker button[data-color="lime"]').click();
    h.expect('POST', ITEMS, { success: true, event: { ...liveEvent, title: 'Chemistry' } });
    await h.window.ScheduleApp.saveEvent();
    await settle();
    const saved = h.requests.find((r) => r.method === 'POST');
    assert.equal(saved.body.color, '#a3e635');
    assert.equal(saved.body.color.includes('var('), false);
    assert.equal(saved.body.day_index, 1);
});

test('Ctrl+C arms the clipboard and Ctrl+V pre-fills a new block on the active day', async (t) => {
    const h = await setup(t);
    const source = h.cardFor(block.title);

    // Ctrl+C with no block open copies the active day's first block, which is
    // the documented "duplicate this one" shortcut -- and it starts from a
    // closed dialog, so the paste below opens a genuinely fresh one.
    assert.equal(hidden(h.get('event-modal')), true, 'starts closed');
    const copying = h.hotkey('c');
    assert.equal(copying.defaultPrevented, true);
    assert.ok(source.classList.contains('event-card--copied'), 'source is marked as copied');

    // Paste opens the normal dialog, pre-filled and pre-placed, with NO call.
    const count = h.requests.length;
    h.hotkey('v');
    assert.equal(h.requests.length, count, 'a paste never touches the API by itself');
    assert.equal(h.get('title').value, block.title);
    assert.equal(h.get('book_name').value, block.book_name);
    assert.equal(h.get('page_count').value, String(block.page_count));
    assert.equal(h.get('test_count').value, String(block.test_count));
    assert.equal(h.get('color_theme').value, 'green');
    assert.equal(h.get('event_id').value, '', 'a paste is always a NEW block');
    // Same duration as the source (09:00-10:00), dropped on the active day.
    assert.equal(h.get('day_index').value, '0');
    assert.equal(timeDiff(h.get('start_time').value, h.get('end_time').value), 60);

    // Confirming creates a second, separate block; the original is untouched.
    h.expect('POST', ITEMS, { success: true, event: { ...liveEvent, item_id: 99 } });
    await h.window.ScheduleApp.saveEvent();
    await settle();
    const created = h.requests.filter((r) => r.method === 'POST').at(-1);
    assert.equal(created.body.title, block.title);
    assert.equal(created.body.color, '#22c55e');
    assert.equal(h.requests.filter((r) => r.method === 'DELETE').length, 0);
});

test('Ctrl+C is ignored while typing, and Escape clears an armed clipboard', async (t) => {
    const h = await setup(t);
    h.cardFor(block.title).click();
    const field = h.get('title');
    const inField = h.hotkey('c', field);
    assert.equal(inField.defaultPrevented, false, 'the browser keeps its own copy inside inputs');
    assert.equal(h.cardFor(block.title).classList.contains('event-card--copied'), false);
    h.click('event-modal');

    h.hotkey('c');
    assert.ok(h.cardFor(block.title).classList.contains('event-card--copied'));
    h.window.document.dispatchEvent(new h.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    assert.equal(h.cardFor(block.title).classList.contains('event-card--copied'), false);
});

test('title suggestions come from the tenant pool and land in the datalist', async (t) => {
    const h = await setup(t, {
        suggestions: {
            suggestions: [
                { title: 'مطالعه ریاضی دهم', usage_count: 7, last_used_at: '2026-09-01 10:00:00' },
                { title: 'حل تست فیزیک', usage_count: 1, last_used_at: '2026-08-01 10:00:00' },
            ],
            scope: 'tenant',
        },
    });

    const options = [...h.get('title-suggestions').querySelectorAll('option')];
    assert.deepEqual(options.map((o) => o.value), ['مطالعه ریاضی دهم', 'حل تست فیزیک']);
    // An often-reused title says so; a once-used title stays plain.
    assert.match(options[0].label, /۷/);
    assert.equal(options[1].label, '');
    assert.equal(h.get('title').getAttribute('list'), 'title-suggestions');
});

test('a suggestions failure never blocks the dialog', async (t) => {
    const h = await setup(t);
    // Every week load re-fires the suggestions GET; make the next one fail and
    // assert the page survives it -- the dialog must still open and the datalist
    // must simply be empty rather than the whole screen sulking.
    h.reject('GET', SUGGESTIONS, new Error('offline'));
    // The refresh itself is answered by the default item stub; the point of the
    // test is what happens to the suggestions GET beside it.
    h.window.ScheduleApp.refreshSchedule();
    await settle();
    await settle();
    h.drag(2, 600, 660);
    assert.equal(hidden(h.get('event-modal')), false);
    assert.equal(h.get('title-suggestions').querySelectorAll('option').length, 0);
});

test('weekly notes load, render as text, and save only what the user typed', async (t) => {
    const texts = { 0: 'شنبه: مرور فصل ۲', 3: 'سه‌شنبه: آزمون آزمایشی' };
    const h = await setup(t, { notes: weekNotes(WEEK, texts) });
    await settle();
    await settle();

    const cards = [...h.get('week-notes-list').children];
    assert.equal(cards.length, 2, 'only days with text render a card');
    assert.match(cards[0].textContent, /شنبه: مرور فصل ۲/);
    assert.equal(hidden(h.get('week-notes-empty')), true);
    assert.match(h.get('week-notes-range').textContent, /۲/);

    // Entering edit mode builds all seven fields; rendered as text, never HTML.
    h.click('week-notes-edit');
    const fields = [...h.get('week-notes-fields').querySelectorAll('textarea')];
    assert.equal(fields.length, 7);
    assert.equal(fields[0].value, texts[0]);
    assert.equal(fields[3].value, texts[3]);
    assert.equal(fields[1].value, '');

    // Editing one day and clearing another sends the whole week.
    fields[1].value = '  یکشنبه: تمرین جدید  ';
    fields[3].value = '';
    fields[1].dispatchEvent(new h.window.Event('input', { bubbles: true }));
    assert.match(h.get('week-notes-status').textContent, /ذخیره نشده/);

    h.expect('PUT', NOTES, {
        success: true,
        ...weekNotes(TARGET_WEEK, { 0: texts[0], 1: 'یکشنبه: تمرین جدید' }),
    });
    h.click('week-notes-save');
    await settle();

    const saved = h.requests.find((r) => r.method === 'PUT' && r.url === NOTES);
    assert.equal(saved.body.week_start_date, WEEK);
    assert.equal(saved.body.notes[1], 'یکشنبه: تمرین جدید', 'text is trimmed before sending');
    assert.equal(saved.body.notes[3], '', 'an emptied day is an explicit deletion');
    assert.equal(Object.keys(saved.body.notes).length, 7);

    // Back to the read view, showing the saved state.
    assert.equal(hidden(h.get('week-notes-form')), true);
    assert.match(h.get('week-notes-list').textContent, /یکشنبه: تمرین جدید/);
});

test('a notes save failure keeps the text on screen for retry', async (t) => {
    const h = await setup(t);
    h.click('week-notes-edit');
    const field = h.get('week-notes-fields').querySelector('textarea');
    field.value = 'برنامه هفته';
    field.dispatchEvent(new h.window.Event('input', { bubbles: true }));
    h.expect('PUT', NOTES, { errors: { notes: ['Rejected'] } }, false);
    h.click('week-notes-save');
    await settle();
    assert.equal(hidden(h.get('week-notes-form')), false, 'the form stays open');
    assert.match(h.get('week-notes-status').textContent, /Rejected/);
    assert.equal(field.value, 'برنامه هفته');
    assert.equal(h.unload(), false);
});

test('the block dialog shows the thread and posts a consultant reply', async (t) => {
    const h = await setup(t);
    h.cardFor(block.title).click();
    await settle();

    assert.equal(hidden(h.get('comments-section')), false, 'the thread section is shown');
    assert.equal(hidden(h.get('consultant-comment-block')), false, 'the composer is shown');
    assert.equal(hidden(h.get('status-container')), false);
    assert.match(h.get('completion-status').textContent, /انجام شده/);

    // Posting a reply goes to the comment endpoint and re-reads the thread.
    h.get('consultant-comment-text').value = 'آفرین، ادامه بده';
    h.expect('POST', `${ITEMS}/91/comments`, {
        success: true, comment: { username: 'مشاور', comment_text: 'آفرین، ادامه بده', created_at: '2026-09-12 09:00', author: 'consultant' },
    });
    h.expect('GET', `${ITEMS}/91/comments`, [
        { username: 'دانش‌آموز', comment_text: 'مریض بودم', created_at: '2026-09-12 08:00', author: 'student' },
        { username: 'مشاور', comment_text: 'آفرین، ادامه بده', created_at: '2026-09-12 09:00', author: 'consultant' },
    ]);
    h.click('consultant-comment-submit');
    await settle();

    const posted = h.requests.find((r) => r.method === 'POST');
    assert.deepEqual(posted.body, { comment_text: 'آفرین، ادامه بده' });
    assert.equal(h.get('consultant-comment-text').value, '');
    assert.match(h.get('comments-list').textContent, /مریض بودم/);
    assert.equal(h.get('comments-list').querySelectorAll('.comment-item').length, 2);
    assert.match(h.get('comments-count').textContent, /۲/);
});

test('an empty comment is never posted', async (t) => {
    const h = await setup(t);
    h.cardFor(block.title).click();
    await settle();
    await settle();
    await settle();
    assert.equal(Boolean(h.get('consultant-comment-form')), true, 'the composer exists');
    const count = h.requests.length;
    h.get('consultant-comment-text').value = '   ';
    h.get('consultant-comment-form').dispatchEvent(new h.window.Event('submit', { bubbles: true, cancelable: true }));
    await settle();
    assert.equal(h.requests.length, count);
    assert.equal(h.requests.some((r) => r.method === 'POST'), false);
});

/** Minutes between two HH:MM strings. */
function timeDiff(from, to) {
    const [fh, fm] = from.split(':').map(Number);
    const [th, tm] = to.split(':').map(Number);
    return (th * 60 + tm) - (fh * 60 + fm);
}

/* ------------------------------------------------------------------------
 * Cross-student copy/paste + a suggestion that also picks its colour.
 *
 * Copying a block is only useful if the copy can be used, and a consultant
 * who has just built a good block for one student wants it for the next one.
 * The clipboard therefore belongs to the CONSULTANT and outlives the page;
 * but only the block's content travels, never the item id -- an id belongs to
 * the student it came from.
 * ---------------------------------------------------------------------- */

test('copying a block persists it for the consultant, not for the page', async (t) => {
    const h = await setup(t);
    assert.equal(h.clipboardStorage(), null, 'nothing copied yet');

    const card = h.copyFirstBlock();
    assert.ok(card, 'the live block has a copy button');

    const stored = JSON.parse(h.clipboardStorage());
    assert.equal(stored.title, liveEvent.title);
    assert.equal(stored.sourceLabel, 'سارا', 'it records which student it came from');
    assert.equal('id' in stored, false, 'no item id is ever carried across students');
});

test('a block copied on one student can be pasted on the next student plan', async (t) => {
    const h = await setup(t);
    h.copyFirstBlock();

    // Another student planner is a brand-new page; only the storage carries over.
    const next = await setup(t, { studentId: '9', studentName: 'علی' });
    assert.ok(next.clipboardStorage(), 'the stored clipboard is shared, not per student');

    const modal = next.get('event-modal');
    assert.equal(hidden(modal), true, 'the next planner starts with the modal closed');

    next.hotkey('v');
    assert.equal(hidden(modal), false, 'paste opens the new-block dialog');
    assert.equal(next.get('title').value, liveEvent.title, 'the copied content arrives');
    assert.equal(next.get('event_id').value, '', 'a cross-student paste is a NEW block');
    assert.equal(next.get('book_name').value, liveEvent.book_name);
});

test('a cross-student paste says where the block came from', async (t) => {
    const h = await setup(t);
    h.copyFirstBlock();

    const next = await setup(t, { studentId: '9', studentName: 'علی' });
    next.hotkey('v');

    const hint = next.get('clipboard-hint');
    assert.equal(hidden(hint), false, 'the dialog explains the crossover');
    assert.match(hint.textContent, /سارا/, 'it names the student the block came from');
    assert.equal(hint.dataset.clipboardState, 'cross-student');
});

test('a same-student paste stays quiet', async (t) => {
    const h = await setup(t);
    h.copyFirstBlock();
    h.hotkey('v');

    assert.equal(hidden(h.get('clipboard-hint')), true, 'no crossing means no notice');
    assert.equal(h.get('clipboard-hint').dataset.clipboardState, '');
});

test('a suggestion also selects the colour the consultant uses on that block', async (t) => {
    const h = await setup(t, {
        suggestions: {
            scope: 'tenant',
            suggestions: [{ title: 'مطالعه ریاضی دهم', usage_count: 4, color: '#22c55e', last_used_at: null }],
        },
    });

    h.drag(1, 660, 720);
    assert.equal(h.get('color_theme').value, 'blue', 'the dialog opens on its default');

    h.input('title', 'مطالعه ریاضی دهم');

    assert.equal(h.get('color_theme').value, 'green', 'the habitual colour is adopted');
    const marked = [...h.window.document.querySelectorAll('#color-picker button.selected')];
    assert.equal(marked.length, 1, 'exactly one swatch is marked');
    assert.equal(marked[0].dataset.color, 'green');
    assert.equal(marked[0].getAttribute('aria-checked'), 'true');
});

test('an unknown title never overrides a colour the user chose', async (t) => {
    const h = await setup(t, {
        suggestions: {
            scope: 'tenant',
            suggestions: [{ title: 'مطالعه ریاضی دهم', usage_count: 4, color: '#22c55e', last_used_at: null }],
        },
    });

    h.drag(1, 660, 720);
    h.window.document.querySelector('#color-picker button[data-color="purple"]').click();
    h.input('title', 'یک بلوک تازه');

    assert.equal(h.get('color_theme').value, 'purple', 'an unlisted title changes nothing');
});



/* ------------------------------------------------------------------------
 * Regressions found by the front-end critic pass.
 * ---------------------------------------------------------------------- */

test('editing an existing block never shows a stale cross-student hint', async (t) => {
    const h = await setup(t);
    h.copyFirstBlock();
    // Paste it elsewhere so the hint is genuinely raised at least once...
    const other = await setup(t, { studentId: '9', studentName: 'علی' });
    other.hotkey('v');
    assert.equal(hidden(other.get('clipboard-hint')), false, 'raised for the paste');

    // ...then a plain click on a real block must reset the dialog cleanly.
    other.get('event-modal').classList.add('hidden');
    other.cardFor(liveEvent.title).click();
    assert.equal(hidden(other.get('clipboard-hint')), true,
        'editing a real block must not claim it was copied from another student');
});

test('opening a fresh new-block dialog shows no stale hint', async (t) => {
    const h = await setup(t);
    h.hotkey('v');
    assert.equal(hidden(h.get('clipboard-hint')), true, 'a same-student paste shows nothing');

    h.click('event-modal'); // close
    h.hotkey('v');
    assert.equal(hidden(h.get('clipboard-hint')), true);
});

test('the clipboard is scoped to the tenant, so academies never share blocks', async (t) => {
    const h = await setup(t);
    h.copyFirstBlock();
    assert.ok(h.clipboardStorage('1', '1'), 'stored under tenant 1');
    assert.equal(h.clipboardStorage('2', '1'), null, 'nothing is stored under tenant 2');

    // The same consultant in another academy starts with an empty clipboard.
    const other = await setup(t, { tenantId: '2' });
    assert.equal(other.clipboardStorage('2', '1'), null);
    other.hotkey('v');
    assert.equal(hidden(other.get('event-modal')), true,
        'a block from another academy must not be pasteable here');
});

test('a completed paste consumes the clipboard instead of lingering', async (t) => {
    const h = await setup(t);
    h.hotkey('c');
    assert.ok(h.clipboardStorage(), 'armed after copying');

    // Paste it as a NEW block and let the save succeed.
    h.expect('POST', ITEMS, { success: true, event: { ...liveEvent, item_id: 99 } });
    h.hotkey('v');
    h.window.ScheduleApp.saveEvent();
    await settle();

    assert.equal(h.clipboardStorage(), null, 'the paste is spent, so nothing is left armed');
    assert.equal(h.cardFor(liveEvent.title).classList.contains('event-card--copied'), false);
});

