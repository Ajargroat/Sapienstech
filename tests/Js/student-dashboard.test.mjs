import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { setImmediate as nextTurn } from 'node:timers/promises';
import { JSDOM } from 'jsdom';

const blade = readFileSync(new URL('../../resources/views/student/dashboard.blade.php', import.meta.url), 'utf8');
const ITEM = 7;
const COMPLETION = `/fixture/items/${ITEM}/completion`;
const COMMENTS = `/fixture/items/${ITEM}/comments`;

/*
 * The student portal's writable pop-up, driven the way the browser drives it.
 *
 * The failure this suite exists for is not a logic bug in a handler: the page
 * carried no CSRF token, so every POST came back 419 and the script swallowed
 * it. These tests therefore assert the *transport* as hard as the behaviour —
 * the header must carry the token the server rendered, on every write.
 */

/**
 * A minimal stand-in for the dashboard markup: one calendar card and the
 * shared dialog, with the shapes the real Blade renders. Notably it has NO
 * csrf <meta> — no student layout renders one, which is exactly why the token
 * has to come off the dialog.
 */
function fixture({ csrf = 'server-token', notes = '' } = {}) {
    return `<!doctype html><html dir="rtl"><body>
    <div class="sched-box" data-sched-item="sched-tpl-${ITEM}" data-sched-title="ریاضی" data-sched-id="${ITEM}" data-sched-completed="0">
        <span class="sched-box-time"><span>09:00</span></span>
    </div>
    <template id="sched-tpl-${ITEM}">
        <div class="exam-field">
            <button type="button" class="sched-modal-value sched-done-toggle" data-student-done-toggle aria-pressed="false">
                <i class="fas fa-circle" aria-hidden="true"></i>
                <span class="sched-done-label">انجام‌نشده</span>
            </button>
            <p class="sched-done-status" data-student-done-status hidden></p>
        </div>
        <div class="exam-field exam-field--wide">
            <div class="comment-composer" data-student-comment-composer>
                <div class="comment-composer-row">
                    <textarea class="draft-field w-full" rows="2" maxlength="2000" data-student-comment-text></textarea>
                    <button type="button" class="draft-btn draft-btn--primary" data-student-comment-submit><span>ثبت</span></button>
                </div>
                <p class="comment-composer-status" data-student-comment-status hidden></p>
            </div>
            <div class="comment-thread" data-student-comment-thread>
                <p class="comment-item comment-item--empty" data-student-comment-empty>هنوز یادداشتی ثبت نکرده‌اید.</p>
            </div>
        </div>
    </template>
    <dialog id="schedule-detail-modal" class="exam-modal" data-csrf="${csrf}"
        data-url-completion-template="/student/schedule/items/__ITEM__/completion"
        data-url-comments-template="/student/schedule/items/__ITEM__/comments">
        <div class="exam-modal-head"><h3 id="sched-modal-title"><span></span></h3>
        <button type="button" data-sched-close>×</button></div>
        <div class="sched-modal-body"></div>
    </dialog>${notes}
    </body></html>`;
}

const response = (body, status = 200) => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => structuredClone(body),
});

const settle = async () => { for (let i = 0; i < 3; i += 1) await nextTurn(); };

async function setup(t, { csrf = 'server-token', thread = [], handler, notes = '' } = {}) {
    const dom = new JSDOM(fixture({ csrf, notes }), { url: 'https://tenant.test', runScripts: 'dangerously' });
    const { window } = dom;
    const requests = [];

    // Every request is recorded with its headers, so a test can assert the
    // CSRF header rather than only observing the side effect.
    const fetchStub = async (url, options = {}) => {
        const request = {
            url: String(url),
            method: options.method || 'GET',
            headers: options.headers || {},
            body: options.body ? JSON.parse(options.body) : undefined,
        };
        requests.push(request);
        if (handler) return handler(request, response);
        if (request.method === 'GET') return response(thread);
        return response({ success: true, is_completed: true, comment: { comment_text: 'x', author: 'student' } }, 201);
    };
    // The module under test is evaluated against these globals, so the JSDOM
    // window has to BE the global window while it runs.
    window.fetch = fetchStub;
    const globals = {
        window,
        document: window.document,
        fetch: fetchStub,
        Intl,
        KeyboardEvent: window.KeyboardEvent,
        HTMLDialogElement: window.HTMLDialogElement,
    };
    const originals = new Map(
        Object.keys(globals).map((key) => [key, Object.getOwnPropertyDescriptor(globalThis, key)])
    );
    Object.assign(globalThis, globals);

    Object.defineProperty(window.HTMLDialogElement.prototype, 'showModal', {
        configurable: true, value() { this.open = true; },
    });
    Object.defineProperty(window.HTMLDialogElement.prototype, 'close', {
        configurable: true, value() { this.open = false; },
    });

    const { default: init } = await import('../../resources/js/features/student-dashboard.js');
    init(window.document);
    await settle();

    const doc = window.document;
    t.after(() => {
        window.close();
        for (const [key, descriptor] of originals) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    });

    const open = () => { doc.querySelector('[data-sched-item]').click(); };
    const q = (selector) => doc.querySelector(selector);
    const post = () => requests.filter((r) => r.method === 'POST');

    return { window, doc, q, open, post, requests };
}

/* --------------------------------------------------------- transport --- */

test('a done-toggle POST carries the CSRF token the page rendered', async (t) => {
    const { doc, open, q, post } = await setup(t);

    // The bug: no student layout renders a csrf <meta>, so the old reader
    // returned '' and Laravel answered 419 to every write.
    assert.equal(doc.querySelector('meta[name="csrf-token"]'), null,
        'this page deliberately has no csrf meta tag — that is the condition that broke');

    open();
    q('[data-student-done-toggle]').click();
    await settle();

    assert.equal(post().length, 1, 'exactly one completion write');
    assert.equal(post()[0].headers['X-CSRF-TOKEN'], 'server-token');
    assert.equal(post()[0].url, '/student/schedule/items/7/completion');
});

test('a comment POST carries the CSRF token the page rendered', async (t) => {
    const { open, q, post } = await setup(t);

    open();
    q('[data-student-comment-text]').value = 'مریض بودم';
    q('[data-student-comment-submit]').click();
    await settle();

    const write = post().find((r) => r.url.endsWith('/comments'));
    assert.ok(write, 'the comment was posted');
    assert.equal(write.headers['X-CSRF-TOKEN'], 'server-token');
    assert.deepEqual(write.body, { comment_text: 'مریض بودم' });
});

/* -------------------------------------------------------- completion --- */

test('marking done repaints the dialog control and the calendar card', async (t) => {
    const { doc, open, q } = await setup(t);

    open();
    q('[data-student-done-toggle]').click();
    await settle();

    assert.equal(q('.sched-done-toggle').classList.contains('is-done'), true);
    assert.equal(q('.sched-done-toggle').getAttribute('aria-pressed'), 'true');
    assert.equal(q('.sched-done-label').textContent, 'تکمیل‌شده');
    assert.equal(q('.sched-done-toggle i').getAttribute('class'), 'fas fa-check-circle');

    // One truth, two places: the card the dialog was opened from agrees.
    const box = doc.querySelector('[data-sched-item]');
    assert.equal(box.dataset.schedCompleted, '1');
    assert.equal(box.classList.contains('sched-box--done'), true);
    assert.equal(box.querySelector('.sched-box-time i.fa-check-circle') !== null, true);
});

test('unmarking removes the done marks again', async (t) => {
    const { doc, open, q, post } = await setup(t, {
        handler: (request, respond) => request.method === 'POST'
            ? respond({ success: true, is_completed: false })
            : respond([]),
    });

    open();
    q('[data-student-done-toggle]').click();
    await settle();

    assert.equal(post()[0].body.is_completed, true, 'the first click asks for done');
    assert.equal(q('.sched-done-toggle').classList.contains('is-done'), false);
    assert.equal(doc.querySelector('[data-sched-item]').dataset.schedCompleted, '0');
});

test('a failed toggle reverts and says so instead of failing silently', async (t) => {
    const { doc, open, q } = await setup(t, {
        handler: (request, respond) => request.method === 'POST'
            ? respond('Page Expired', 419)
            : respond([]),
    });

    open();
    q('[data-student-done-toggle]').click();
    await settle();

    // It must not lie about the state...
    assert.equal(q('.sched-done-toggle').classList.contains('is-done'), false,
        'a rejected write leaves the control where it was');
    assert.equal(doc.querySelector('[data-sched-item]').dataset.schedCompleted, '0');

    // ...and it must not be silent: that silence is what read as a dead button.
    const status = q('[data-student-done-status]');
    assert.equal(status.hidden, false, 'the failure is visible');
    assert.match(status.textContent, /ثبت نشد/);
    assert.equal(status.classList.contains('sched-done-status--error'), true);
    assert.equal(q('[data-student-done-toggle]').disabled, false, 'the control is usable again');
});

/* ---------------------------------------------------------- comments --- */

test('an existing thread renders with its authors', async (t) => {
    const { open, q } = await setup(t, {
        thread: [
            { comment_text: 'دقت کن', author: 'consultant' },
            { comment_text: 'چشم', author: 'student' },
        ],
    });

    open();
    await settle();

    const items = q('[data-student-comment-thread]').querySelectorAll('.comment-item');
    assert.equal(items.length, 2);
    assert.equal(items[0].textContent.includes('دقت کن'), true);
    assert.equal(items[1].classList.contains('comment-item--student'), true);
    assert.equal(q('[data-student-comment-thread] [data-student-comment-empty]'), null);
});

test('a posted comment is appended locally and the field is cleared', async (t) => {
    const { open, q } = await setup(t);

    open();
    q('[data-student-comment-text]').value = 'مریض بودم';
    q('[data-student-comment-submit]').click();
    await settle();

    const thread = q('[data-student-comment-thread]');
    assert.equal(thread.textContent.includes('مریض بودم'), true);
    assert.equal(thread.querySelector('[data-student-comment-empty]'), null);
    assert.equal(q('[data-student-comment-text]').value, '', 'the field is cleared');
    assert.match(q('[data-student-comment-status]').textContent, /ثبت شد/);
});

test('an empty comment never reaches the server', async (t) => {
    const { open, q, post } = await setup(t);

    open();
    q('[data-student-comment-text]').value = '   ';
    q('[data-student-comment-submit]').click();
    await settle();

    assert.equal(post().filter((r) => r.url.endsWith('/comments')).length, 0);
    assert.match(q('[data-student-comment-status]').textContent, /خالی/);
});

test('Enter submits a comment and Shift+Enter keeps the newline', async (t) => {
    const { window, open, q, post } = await setup(t);

    open();
    const text = q('[data-student-comment-text]');
    text.value = 'خط اول';

    text.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', shiftKey: true, bubbles: true, cancelable: true }));
    await settle();
    assert.equal(post().filter((r) => r.url.endsWith('/comments')).length, 0, 'Shift+Enter is a newline');

    text.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    await settle();
    assert.equal(post().filter((r) => r.url.endsWith('/comments')).length, 1, 'Enter submits');
});

test('a rejected comment keeps the text so nothing typed is lost', async (t) => {
    const { open, q } = await setup(t, {
        handler: (request, respond) => request.method === 'POST'
            ? respond({ error: 'nope' }, 500)
            : respond([]),
    });

    open();
    q('[data-student-comment-text]').value = 'مریض بودم';
    q('[data-student-comment-submit]').click();
    await settle();

    assert.equal(q('[data-student-comment-text]').value, 'مریض بودم');
    assert.match(q('[data-student-comment-status]').textContent, /ثبت نشد/);
    assert.equal(q('[data-student-comment-status]').classList.contains('comment-composer-status--error'), true);
});

/* ------------------------------------------------ no explanatory copy --- */

test('the modal carries no explanatory placeholder copy', async () => {
    const markup = readFileSync(new URL('../../resources/views/student/dashboard.blade.php', import.meta.url), 'utf8');

    assert.equal(/مریض بودم و نتوانستم/.test(markup), false,
        'the (مریض بودم و نتوانستم) placeholder was removed on request');
    assert.equal(/دلیل انجام یا انجام‌نشدن/.test(markup), false,
        'the explanatory label tail was removed on request');
});

/* -------------------------------------------------------- week notes --- */

const NOTES_URL = '/student/schedule/notes';
const WEEK_START = '2026-10-17';

/** The S1 skeleton exactly as the real blade ships it. */
const notesSection = (url = NOTES_URL, weekStart = WEEK_START) => `
    <section class="panel student-panel" id="student-week-notes" hidden
        data-url-notes="${url}" data-week-start="${weekStart}">
        <header class="student-panel-head"><h2 id="student-week-notes-title">توضیحات هفته</h2></header>
        <div id="student-week-notes-list"></div>
        <p class="student-panel-note" id="student-week-notes-empty" hidden>برای این هفته توضیحی ثبت نشده</p>
    </section>`;

const day = (index, label, text) => ({
    day_index: index,
    label,
    text,
    has_text: typeof text === 'string' && text.trim() !== '',
});

const notesPayload = (days, weekStart = WEEK_START) => ({
    week_start_date: weekStart,
    days,
    note_count: days.filter((d) => d.has_text).length,
});

test('the week notes render only days with text, in payload order', async (t) => {
    const payload = notesPayload([
        day(0, 'شنبه', 'صفحه ۱۰ تا ۲۰'),
        day(1, 'یکشنبه', null),
        day(2, 'دوشنبه', '<b>نه</b> HTML'),
    ]);

    const { doc, requests } = await setup(t, {
        notes: notesSection(),
        handler: () => response(payload),
    });

    const section = doc.getElementById('student-week-notes');
    const days = section.querySelectorAll('#student-week-notes-list .sched-day');

    assert.equal(section.hidden, false);
    assert.equal(doc.getElementById('student-week-notes-empty').hidden, true);
    assert.equal(days.length, 2);
    assert.equal(days[0].querySelector('.sched-day-head').textContent, 'شنبه');
    assert.equal(days[0].querySelector('.student-week-notes-text').textContent, 'صفحه ۱۰ تا ۲۰');
    assert.equal(days[1].querySelector('.sched-day-head').textContent, 'دوشنبه');

    // A note is data: markup inside it stays text, never becomes elements.
    const body = days[1].querySelector('.student-week-notes-text');
    assert.equal(body.textContent, '<b>نه</b> HTML');
    assert.equal(body.querySelector('b'), null);

    // The fetch is pinned to the week the page rendered, as JSON.
    assert.equal(requests[0].url, `${NOTES_URL}?week_start_date=${WEEK_START}`);
    assert.equal(requests[0].headers.Accept, 'application/json');
});

test('an empty week reveals the muted line instead of a list', async (t) => {
    const payload = notesPayload([day(0, 'شنبه', null), day(1, 'یکشنبه', '')]);

    const { doc } = await setup(t, {
        notes: notesSection(),
        handler: () => response(payload),
    });

    assert.equal(doc.getElementById('student-week-notes').hidden, false);
    assert.equal(doc.getElementById('student-week-notes-empty').hidden, false);
    assert.equal(doc.querySelectorAll('#student-week-notes-list .sched-day').length, 0);
});

test('a failed notes fetch leaves the section hidden', async (t) => {
    const { doc } = await setup(t, {
        notes: notesSection(),
        handler: () => response({ message: 'nope' }, 500),
    });

    assert.equal(doc.getElementById('student-week-notes').hidden, true);
    assert.equal(doc.getElementById('student-week-notes-empty').hidden, true);
});

test('the real dashboard blade ships the week-notes skeleton with its hooks', () => {
    assert.match(blade, /id="student-week-notes"/);
    assert.match(blade, /data-url-notes="\{\{ route\('student\.schedule\.notes'\) \}\}"/);
    assert.match(blade, /data-week-start="\{\{ \$weekStart->toDateString\(\) \}\}"/);
    assert.match(blade, /id="student-week-notes-empty"[^>]*hidden/);

    // React island boundary (PLAN react-01): the section is the mount marker,
    // and the cloned template keeps its own thread marker. React only fills
    // the containers the Blade view already ships.
    assert.match(blade, /id="student-week-notes" data-react-island="student-week-notes"/);
    assert.match(blade, /data-student-comment-thread data-react-island="student-comments"/);
});
