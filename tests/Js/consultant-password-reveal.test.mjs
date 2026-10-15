import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { setImmediate as nextTurn } from 'node:timers/promises';
import { JSDOM } from 'jsdom';

const PASSWORD = 'Ab3xY9zQ7mN2';
const USERNAME = 'mehdi@example.test';
const PARTIAL = 'resources/views/consultant/students/partials/generated-password-dialog.blade.php';

/*
 * The new-student password pop-up, driven the way the browser drives it.
 *
 * The password exists in exactly one response and is never stored in plain
 * text, so the whole point of this dialog is that it CANNOT be dismissed by
 * accident before the consultant has it: it opens itself, Escape is refused,
 * and closing before a copy demands a confirmation. These tests pin that down
 * — plus the `@vite`/hook contract in the Blade partial, which is what the
 * real page router relies on to even find the bundle.
 */

/** The shapes the Blade partial renders, so the hooks are read as the browser reads them. */
function fixture() {
    return `<!doctype html><html dir="rtl"><body>
    <dialog id="generated-password-dialog" class="exam-modal" aria-labelledby="generated-password-title">
        <div class="exam-modal-head">
            <h3 id="generated-password-title"><i class="fas fa-key" aria-hidden="true"></i> رمز عبور ساخته‌شده</h3>
            <p>این رمز تنها همین یک‌بار نمایش داده می‌شود…</p>
        </div>
        <div class="exam-modal-body">
            <div class="exam-field exam-field--wide">
                <label for="generated-password-username">نام کاربری (ایمیل)</label>
                <input id="generated-password-username" type="text" dir="ltr" readonly autocomplete="off" value="${USERNAME}">
            </div>
            <div class="exam-field exam-field--wide">
                <label for="generated-password-code">رمز عبور</label>
                <input id="generated-password-code" type="text" dir="ltr" readonly autocomplete="off" value="${PASSWORD}">
            </div>
        </div>
        <div class="exam-modal-foot">
            <button type="button" class="primary-button" data-generated-password-copy>
                <i class="fas fa-copy" aria-hidden="true"></i>
                <span data-generated-password-copy-label>کپی رمز</span>
            </button>
            <button type="button" class="secondary-button" data-generated-password-close>بستن</button>
        </div>
    </dialog>
    </body></html>`;
}

const settle = async () => { for (let i = 0; i < 3; i += 1) await nextTurn(); };

async function setup(t, { markup = fixture(), clipboard = 'stub', confirm = () => true, execCommand = () => false } = {}) {
    const dom = new JSDOM(markup, { url: 'https://tenant.test', runScripts: 'dangerously' });
    const { window } = dom;

    // Set from inside the page before the import: the module must NOT self-init
    // here, so each test drives init() itself and observes that first step.
    window.sapienstechRouter = {};

    const writes = [];
    if (clipboard !== null) {
        // null models an insecure origin, where navigator.clipboard does not exist.
        Object.defineProperty(window.navigator, 'clipboard', {
            configurable: true,
            value: clipboard === 'stub' ? { writeText: async (text) => { writes.push(text); } } : clipboard,
        });
    }

    const confirms = [];
    window.confirm = (message) => { confirms.push(message); return confirm(message); };
    window.document.execCommand = execCommand;

    // Only `window` and `document` are wired as globals: the module reads the
    // clipboard through `window.navigator` explicitly (Node's own global
    // navigator has no clipboard), and `window` must BE the JSDOM window.
    const globals = { window, document: window.document };
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

    const { default: init } = await import('../../resources/js/features/consultant-password-reveal.js');
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

    return {
        window,
        doc,
        writes,
        confirms,
        q: (selector) => doc.querySelector(selector),
        dialog: () => doc.querySelector('#generated-password-dialog'),
    };
}

/* -------------------------------------------------------------- opening --- */

test('the dialog opens itself and focuses the copy button', async (t) => {
    const { doc, q, dialog } = await setup(t);

    assert.equal(dialog().open, true, 'a rendered password must be visible, not left inside a closed dialog');
    assert.equal(q('#generated-password-code').value, PASSWORD);
    assert.equal(q('#generated-password-username').value, USERNAME);
    assert.equal(doc.activeElement, q('[data-generated-password-copy]'));
});

test('Escape is refused — the only copy of the password cannot be dropped unseen', async (t) => {
    const { window, dialog } = await setup(t);

    const cancel = new window.Event('cancel', { cancelable: true });
    dialog().dispatchEvent(cancel);
    await settle();

    assert.equal(cancel.defaultPrevented, true);
    assert.equal(dialog().open, true);
});

/* --------------------------------------------------------------- copying --- */

test('copying writes the password and flips the button to «کپی شد»', async (t) => {
    const { q, writes, dialog } = await setup(t);

    q('[data-generated-password-copy]').click();
    await settle();

    assert.deepEqual(writes, [PASSWORD]);
    assert.equal(dialog().dataset.copied, '1');
    assert.equal(q('[data-generated-password-copy-label]').textContent, 'کپی شد');
    assert.equal(q('[data-generated-password-copy] i').getAttribute('class'), 'fas fa-check');
});

test('a failed copy leaves the value selected and the button unchanged', async (t) => {
    const { q, dialog } = await setup(t, {
        clipboard: { writeText: async () => { throw new Error('insecure origin'); } },
        execCommand: () => false,
    });

    q('[data-generated-password-copy]').click();
    await settle();

    const code = q('#generated-password-code');
    assert.equal(code.selectionStart, 0, 'the value stays selected for a manual Ctrl+C');
    assert.equal(code.selectionEnd, PASSWORD.length);
    assert.equal(dialog().dataset.copied, undefined);
    assert.equal(q('[data-generated-password-copy-label]').textContent, 'کپی رمز');
});

test('when the clipboard API is missing, the execCommand fallback copies', async (t) => {
    const calls = [];
    const { q, dialog } = await setup(t, {
        clipboard: null, // an insecure origin has no navigator.clipboard at all
        execCommand: (command) => { calls.push(command); return true; },
    });

    q('[data-generated-password-copy]').click();
    await settle();

    assert.deepEqual(calls, ['copy']);
    assert.equal(dialog().dataset.copied, '1');
    assert.equal(q('[data-generated-password-copy-label]').textContent, 'کپی شد');
});

/* --------------------------------------------------------------- closing --- */

test('closing before a copy demands a confirmation', async (t) => {
    let allow = false;
    const { q, confirms, dialog } = await setup(t, { confirm: () => allow });

    q('[data-generated-password-close]').click();
    await settle();

    assert.equal(confirms.length, 1, 'an uncopied password must be confirmed away');
    assert.equal(dialog().open, true, 'a declined confirmation keeps the dialog up');

    allow = true;
    q('[data-generated-password-close]').click();
    await settle();

    assert.equal(confirms.length, 2);
    assert.equal(dialog().open, false, 'an accepted confirmation closes it');
});

test('closing after a copy needs no confirmation', async (t) => {
    const { q, confirms, dialog } = await setup(t);

    q('[data-generated-password-copy]').click();
    await settle();
    q('[data-generated-password-close]').click();
    await settle();

    assert.equal(confirms.length, 0);
    assert.equal(dialog().open, false);
});

/* ------------------------------------------------------------- no dialog --- */

test('a page without the dialog is left alone', async (t) => {
    const { doc, dialog } = await setup(t, { markup: '<!doctype html><html><body><p>no dialog here</p></body></html>' });

    assert.equal(dialog(), null);
    assert.equal(doc.body.textContent.includes('no dialog here'), true);
});

/* ---------------------------------------------- the Blade/registry contract --- */

test('the Blade partial carries the hooks and the bundle registration', async () => {
    const partial = readFileSync(new URL('../../' + PARTIAL, import.meta.url), 'utf8');
    const profile = readFileSync(new URL('../../resources/views/consultant/students/profile.blade.php', import.meta.url), 'utf8');
    const request = readFileSync(new URL('../../resources/views/consultant/students/request.blade.php', import.meta.url), 'utf8');
    const vite = readFileSync(new URL('../../vite.config.js', import.meta.url), 'utf8');

    // The guard is what makes the one-show flash the only render of the dialog.
    assert.ok(partial.includes("@if(session('generated_password'))"));

    for (const hook of [
        'id="generated-password-dialog"',
        'id="generated-password-username"',
        'id="generated-password-code"',
        'data-generated-password-copy',
        'data-generated-password-copy-label',
        'data-generated-password-close',
        '<noscript>',
    ]) {
        assert.ok(partial.includes(hook), `${PARTIAL} must render ${hook}`);
    }

    // The router only reads scripts inside the swapped region, so the @vite tag
    // has to ride the partial, and Vite must know the entry at build time.
    assert.ok(partial.includes("@vite(['resources/js/features/consultant-password-reveal.js'])"));
    assert.ok(vite.includes("'resources/js/features/consultant-password-reveal.js'"));

    // Both pages the password can be flashed onto include the reveal.
    const include = "@include('consultant.students.partials.generated-password-dialog')";
    assert.ok(profile.includes(include), 'the create flow redirects to the profile');
    assert.ok(request.includes(include), 'the approve flow redirects to the request page');
});
