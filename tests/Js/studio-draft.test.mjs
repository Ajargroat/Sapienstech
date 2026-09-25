import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import initDraft from '../../resources/js/features/studio-draft.js';

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

// The draft module talks to four JSON endpoints and replays a restore as a
// real form POST. fetch/confirm/submit are all stubbed so the contract —
// what gets stored, what gets replayed, and that scope is always preview —
// can be asserted directly.
function setup({ draft = null, storeStatus = 201 } = {}) {
    const dom = new JSDOM(`<div>
        <form id="studio-form" action="http://tenant.test/studio">
            <input type="hidden" name="_token" value="live-token">
            <input type="text" name="tenant[name]" value="Sapien">
            <input type="text" name="tenant[empty]" value="">
            <input type="checkbox" name="public[nav][enabled]" value="1" checked>
            <input type="checkbox" name="landing[sections][]" value="hero" checked>
            <input type="checkbox" name="landing[sections][]" value="cta" checked>
            <input type="checkbox" name="landing[sections][]" value="blog" unchecked>
            <input type="text" name="theme[colors][primary]" value="#aabbcc">
            <textarea name="public[canvas][nodes]">{"public.landing.hero":{"props":{}}}</textarea>
        </form>
        <section data-draft-panel
                 data-draft-show="http://tenant.test/studio/draft"
                 data-draft-store="http://tenant.test/studio/draft"
                 data-draft-update="http://tenant.test/studio/draft"
                 data-draft-destroy="http://tenant.test/studio/draft">
            <div data-draft-body></div>
        </section>
    </div>`, { url: 'http://tenant.test' });

    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history', 'FormData', 'HTMLFormElement']) {
        globalThis[key] = dom.window[key];
    }

    let confirms = 0;
    globalThis.confirm = () => { confirms += 1; return true; };

    const calls = [];
    let current = draft;
    globalThis.fetch = async (url, options = {}) => {
        const method = options.method || 'GET';
        const body = options.body ? JSON.parse(options.body) : null;
        calls.push({ url, method, body });
        if (method === 'POST' && storeStatus !== 201) {
            return {
                ok: false,
                status: storeStatus,
                json: async () => ({ message: 'You can keep only one draft.' }),
            };
        }
        if (method === 'POST') {
            current = { ...body, updated_at: '2026-09-25T10:00:00Z' };
            return { ok: true, status: 201, json: async () => ({ draft: current }) };
        }
        if (method === 'PUT') {
            current = { ...current, ...body, updated_at: '2026-09-25T11:00:00Z' };
            return { ok: true, status: 200, json: async () => ({ draft: current }) };
        }
        if (method === 'DELETE') {
            current = null;
            return { ok: true, status: 200, json: async () => ({ success: true }) };
        }
        return { ok: true, status: 200, json: async () => ({ draft: current }) };
    };

    const form = document.querySelector('form');
    const body = document.querySelector('[data-draft-body]');
    initDraft(form);

    const buttons = (label) => [...body.querySelectorAll('button')]
        .find((button) => button.textContent === label);
    const submitted = { form: null };
    dom.window.HTMLFormElement.prototype.submit = function capture() { submitted.form = this; };

    return { dom, form, body, calls, submitted, buttons, confirms: () => confirms };
}

test('creating a checkpoint snapshots the form exactly like a browser submit', async () => {
    const { body, calls, buttons } = setup();

    await tick();
    assert.ok(buttons('Save draft'), 'empty state offers create');

    // An unnamed checkpoint never reaches the network.
    buttons('Save draft').click();
    buttons('Save').click();
    await tick();
    assert.equal(calls.length, 1, 'only the boot GET has run');
    assert.ok(body.textContent.includes('Enter a draft name.'));

    body.querySelector('input[type="text"]').value = 'بازطراحی هدر';
    buttons('Save').click();
    await tick();

    const post = calls.find((call) => call.method === 'POST');
    assert.ok(post, 'the checkpoint was posted');
    assert.equal(post.body.name, 'بازطراحی هدر');
    const payload = post.body.payload;
    assert.deepEqual(payload['tenant[name]'], ['Sapien']);
    assert.deepEqual(payload['theme[colors][primary]'], ['#aabbcc']);
    assert.deepEqual(payload['public[nav][enabled]'], ['1'], 'checked boxes are captured');
    assert.deepEqual(payload['landing[sections][]'], ['hero', 'cta'], 'unchecked boxes are not; DOM order holds');
    assert.deepEqual(payload['public[canvas][nodes]'], ['{"public.landing.hero":{"props":{}}}']);
    assert.equal('_token' in payload, false, 'the live CSRF never travels inside the snapshot');
    assert.equal('tenant[empty]' in payload, false, 'empty values are absent, exactly like ConvertEmptyStringsToNull');
    assert.ok(body.textContent.includes('بازطراحی هدر'), 'the card shows the stored name');
});

test('an existing checkpoint updates, renames, inspects and deletes', async () => {
    const { body, calls, buttons } = setup({
        draft: {
            name: 'نام اول',
            payload: { 'tenant[name]': ['Old'], 'theme[colors][primary]': ['#010203'] },
            updated_at: '2026-09-25T09:00:00Z',
        },
    });

    await tick();
    assert.ok(body.textContent.includes('نام اول'), 'the stored draft paints on boot');
    assert.ok(buttons('Restore'));

    // Inspect: keys render as text, never markup.
    buttons('Details').click();
    const items = [...body.querySelectorAll('[data-draft-inspect] li')].map((li) => li.textContent);
    assert.ok(items.some((text) => text.includes('tenant[name]')));
    assert.ok(items.some((text) => text.includes('theme[colors][primary]')));

    // Overwrite the checkpoint with the current form (payload only).
    buttons('Update').click();
    await tick();
    const putPayload = calls.find((call) => call.method === 'PUT');
    assert.equal('name' in putPayload.body, false, 'an overwrite renames nothing');
    assert.deepEqual(putPayload.body.payload['tenant[name]'], ['Sapien']);

    // Rename keeps the checkpoint (name only).
    buttons('Rename').click();
    body.querySelector('input[type="text"]').value = 'نام تازه';
    buttons('Save').click();
    await tick();
    const puts = calls.filter((call) => call.method === 'PUT');
    assert.equal('payload' in puts[puts.length - 1].body, false, 'a rename stores nothing else');
    assert.equal(puts[puts.length - 1].body.name, 'نام تازه');
    assert.ok(body.textContent.includes('نام تازه'));

    // Delete empties the panel.
    buttons('Delete').click();
    await tick();
    assert.equal(calls.some((call) => call.method === 'DELETE'), true);
    assert.ok(buttons('Save draft'), 'back to the empty state');
});

test('restore replays the payload as a preview save and never as a publish', async () => {
    const { dom, form, submitted, buttons } = setup({
        draft: {
            name: 'برای بازگردانی',
            payload: {
                'tenant[name]': ['Restored'],
                'theme[colors][primary]': ['#010203'],
                // Hostile leftovers a crafted checkpoint could carry — the
                // client must skip them even though the server strips them.
                '_token': ['stale-token'],
                'scope': ['everyone'],
            },
            updated_at: '2026-09-25T09:00:00Z',
        },
    });
    await tick();

    // Declining the confirmation navigates nothing.
    let confirms = 0;
    globalThis.confirm = () => { confirms += 1; return false; };
    buttons('Restore').click();
    assert.equal(submitted.form, null, 'a declined restore submits nothing');
    assert.equal(confirms, 1);

    globalThis.confirm = () => { confirms += 1; return true; };
    buttons('Restore').click();
    assert.equal(confirms, 2);

    const replay = submitted.form;
    assert.ok(replay, 'the restore builds a real form');
    assert.equal(replay.tagName, 'FORM');
    assert.equal(replay.getAttribute('method').toUpperCase(), 'POST');
    assert.equal(replay.action, form.action, 'posts to the ordinary save endpoint');

    const values = {};
    for (const input of replay.querySelectorAll('input')) {
        (values[input.name] ??= []).push(input.value);
    }
    assert.deepEqual(values['_token'], ['live-token'], 'the LIVE csrf rides the replay');
    assert.deepEqual(values['scope'], ['preview'], 'working layer only');
    assert.equal(values['scope'].length, 1, 'the crafted scope can never double up');
    assert.deepEqual(values['tenant[name]'], ['Restored']);
    assert.deepEqual(values['theme[colors][primary]'], ['#010203']);
    assert.equal('public[canvas][nodes]' in values, false, 'absent stays absent');
    assert.ok(!Object.values(values).flat().includes('stale-token'), 'a captured csrf never replays');
    assert.ok(!Object.values(values).flat().includes('everyone'), 'a stored publish scope never replays');
});
