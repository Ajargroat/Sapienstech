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

async function setup(t, { initial = schedule(), initialResponse } = {}) {
    const dom = new JSDOM(fixture, { url: 'https://tenant.test', runScripts: 'dangerously' });
    const { window } = dom;
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
    };
    for (const [key, value] of Object.entries(urls)) {
        assert.ok(key in root.dataset, `Blade must supply ${key}`);
        root.dataset[key] = value;
    }
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
                unexpected.push(request);
                throw new Error(`Unexpected request: ${request.method} ${request.url}`);
            }
            if (next.result instanceof Error) throw next.result;
            return next.result;
        },
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
    window.addEventListener('error', (event) => { errors.push(event.error); event.preventDefault(); });
    t.after(() => {
        try {
            cleanup?.();
            timers.clear();
            window.close();
            assert.deepEqual(unexpected, [], 'No unplanned API calls, including live item/comment mutations');
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
        hold(method, url, promise) { expected.push({ method, url, result: promise }); },
        reject(method, url, error) { expected.push({ method, url, result: error }); },
    };
}

const hidden = (element) => element.classList.contains('hidden');

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
    assert.equal(h.requests.length, 2);
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

    const col = h.window.document.querySelector('.day-col-grid-1');
    col.dispatchEvent(new h.window.MouseEvent('mousedown', { bubbles: true, clientY: 660 }));
    h.window.dispatchEvent(new h.window.MouseEvent('mousemove', { clientY: 720 }));
    h.window.dispatchEvent(new h.window.MouseEvent('mouseup'));
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
    assert.deepEqual(h.requests.map(({ method, url }) => [method, url]), [['GET', ITEMS], ['GET', DRAFTS]]);
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
