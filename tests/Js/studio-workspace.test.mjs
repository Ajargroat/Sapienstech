import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import initWorkspace from '../../resources/js/features/studio-workspace.js';
import initLists from '../../resources/js/features/studio-lists.js';

function setup() {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero" aria-label="Hero"></button><button data-studio-tab="colors"></button><button data-studio-tab="blocks"></button>
        <button data-workspace-tool="select"></button><button data-workspace-tool="interact"></button>
        <button data-workspace-insert="button"></button><button data-workspace-action="undo"></button>
        <input type="checkbox" data-studio-interact>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form><div data-studio-group="hero"><div data-studio-field="public.landing.hero.title_line1"><label class="studio-field-label">Title</label><input name="hero" value="Initial"></div></div>
        <div data-block-editor><button type="button" data-block-insert="button"></button><button type="button" data-block-undo disabled></button></div></form></div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    const editor = initWorkspace(form);
    const frame = () => {
        const canvas = new JSDOM('<!doctype html><html><head></head><body><template data-studio-section-marker="hero"></template><section><h1 style="color:rgb(10,20,30);font-size:32px">Hero title</h1><a href="#contact">Link</a></section></body></html>');
        form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: canvas.window.document } } }));
        return canvas;
    };
    return { dom, form, editor, frame };
}

test('canvas selection shows computed metrics and opens only existing schema controls', () => {
    const { form, frame } = setup();
    let opened = 0;
    document.querySelector('[data-studio-tab="hero"]').addEventListener('click', () => opened++);
    const canvas = frame();
    const click = new canvas.window.MouseEvent('click', { bubbles: true, cancelable: true });
    canvas.window.document.querySelector('h1').dispatchEvent(click);
    assert.equal(click.defaultPrevented, true);
    assert.equal(document.querySelector('[data-object-title]').textContent, 'Hero title');
    assert.match(document.querySelector('[data-object-metrics]').textContent, /32px/);
    assert.match(document.querySelector('[data-object-metrics]').textContent, /rgb\(10, 20, 30\)/);
    assert.equal(opened, 1);
    document.querySelector('[data-object-controls] button').click();
    assert.equal(form.querySelector('[data-studio-field]').classList.contains('is-object-field'), true);
    assert.equal(form.querySelector('input').value, 'Initial');
    assert.equal(document.querySelectorAll('[data-page-layers] button').length, 1);
});

test('interaction tool releases navigation and selection survives frame replacement', () => {
    const { frame } = setup();
    const first = frame();
    first.window.document.querySelector('h1').click();
    const next = frame();
    assert.equal(next.window.document.querySelector('h1').classList.contains('studio-inspected-object'), true);
    document.querySelector('[data-workspace-tool="interact"]').click();
    assert.equal(next.window.document.documentElement.classList.contains('studio-canvas-editing'), false);
    const event = new next.window.MouseEvent('click', { bubbles: true, cancelable: true });
    next.window.document.querySelector('a').dispatchEvent(event);
    assert.equal(event.defaultPrevented, false);
    document.querySelector('[data-workspace-tool="select"]').click();
    assert.equal(next.window.document.documentElement.classList.contains('studio-canvas-editing'), true);
});

test('toolbar proxies respect native disabled state and do not submit the form', () => {
    const { form } = setup();
    let inserted = 0;
    let submitted = 0;
    form.addEventListener('submit', () => submitted++);
    form.querySelector('[data-block-insert]').addEventListener('click', () => inserted++);
    assert.equal(document.querySelector('[data-workspace-action]').disabled, true);
    document.querySelector('[data-workspace-insert]').click();
    assert.equal(inserted, 1);
    assert.equal(submitted, 0);
    form.querySelector('[data-block-insert]').disabled = true;
    form.dispatchEvent(new CustomEvent('studio:block-state'));
    assert.equal(document.querySelector('[data-workspace-insert]').disabled, true);
    document.querySelector('[data-workspace-insert]').click();
    assert.equal(inserted, 1);
});

test('hidden preview twins and cross-origin frames cannot replace the active inspector', () => {
    const { form, frame } = setup();
    const canvas = frame();
    canvas.window.document.querySelector('h1').click();
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { hasAttribute: () => true } } }));
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { get contentDocument() { throw new Error('cross origin'); } } } }));
    assert.equal(document.querySelector('[data-object-title]').textContent, 'Hero title');
});

// A second fixture whose canvas carries schema paths, plus a context panel and
// two schema controls (a leaf text field and a list repeater).
function setupPaths(hash = '') {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero" aria-label="Hero"></button><button data-studio-tab="blocks"></button>
        <button data-workspace-tool="select"></button><button data-workspace-tool="interact"></button>
        <input type="checkbox" data-studio-interact>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form>
        <section data-context-panel hidden><strong data-context-title></strong><p data-context-note></p><div data-context-fields></div><button type="button" data-context-clear></button></section>
        <div data-studio-group="hero"><div data-studio-field="public.landing.hero.title_line1"><label class="studio-field-label">Title</label><input name="hero" value="Initial"></div>
        <div data-studio-field="public.landing.hero.buttons" data-live="reload"><label class="studio-field-label">Buttons</label>
            <div data-studio-list data-max="4"><div data-list-rows><div data-list-row><input name="hero[buttons][0][label]" value="Start"></div></div></div></div></div>
        </form></div>`, { url: `http://tenant.test/${hash}` });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    const editor = initWorkspace(form);
    const canvas = () => new JSDOM('<!doctype html><html><head></head><body><template data-studio-section-marker="hero"></template><section><h1 data-studio-path="public.landing.hero.title_line1">Hero title</h1><a data-studio-path="public.landing.hero.buttons.0" href="#">Start</a></section></body></html>');
    const frame = (doc) => {
        const next = doc || canvas();
        form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: next.window.document } } }));
        return next;
    };
    return { dom, form, editor, canvas, frame, panel: () => document.querySelector('[data-context-panel]') };
}

test('selecting a path adopts its schema control into the context panel', () => {
    const { form, frame, panel } = setupPaths();
    const canvas = frame();
    canvas.window.document.querySelector('h1').click();
    assert.equal(panel().hidden, false);
    assert.equal(document.querySelector('[data-context-title]').textContent, 'Hero title');
    // The real control (one input per name) moved into the panel.
    assert.equal(panel().querySelectorAll('[name="hero"]').length, 1);
    assert.equal(form.querySelectorAll('[name="hero"]').length, 1);
    assert.equal(document.querySelector('[data-context-fields] input').value, 'Initial');
    // Closing puts the control back exactly where it came from.
    document.querySelector('[data-context-clear]').click();
    assert.equal(panel().hidden, true);
    assert.equal(panel().querySelectorAll('[data-studio-field]').length, 0);
    assert.equal(form.querySelector('[data-studio-field="public.landing.hero.title_line1"] input').value, 'Initial');
});

test('a list row path opens the repeater that owns it and marks the row', () => {
    const { frame, panel } = setupPaths();
    const canvas = frame();
    canvas.window.document.querySelector('a').click();
    assert.equal(panel().hidden, false);
    const list = panel().querySelector('[data-studio-list]');
    assert.equal(Boolean(list), true);
    assert.equal(panel().querySelector('[data-list-row]').classList.contains('is-context-row'), true);
    assert.equal(panel().querySelector('[name="hero[buttons][0][label]"]').value, 'Start');
});

test('path selection is restored after the preview frame is replaced', () => {
    const { frame } = setupPaths();
    frame().window.document.querySelector('h1').click();
    const next = frame();
    assert.equal(next.window.document.querySelector('h1').classList.contains('studio-inspected-object'), true);
    assert.equal(document.querySelector('[data-object-title]').textContent, 'Hero title');
});

// The list API the panel drives comes from the real initLists factory, so this
// test covers the production row operations rather than a stand-in.
function setupListOps() {
    const row = `<div data-list-row><div class="studio-list-row-head"></div><input name="items[__KEY__][label]" value="One"></div>`;
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero"></button><button data-studio-tab="blocks"></button>
        <button data-workspace-tool="select"></button><button data-workspace-tool="interact"></button>
        <input type="checkbox" data-studio-interact>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form>
        <section data-context-panel hidden><strong data-context-title></strong><p data-context-note></p><div data-context-fields></div><button type="button" data-context-clear></button></section>
        <div data-studio-group="hero"><div data-studio-field="public.landing.hero.buttons" data-live="reload">
            <div data-studio-list data-max="2"><div data-list-rows>${row.replaceAll('__KEY__', '0')}</div>
            <template data-list-template>${row}</template><button type="button" data-list-add></button></div></div></div>
        </form></div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    initLists(form);
    initWorkspace(form);
    const canvas = new JSDOM('<!doctype html><html><head></head><body><section><a data-studio-path="public.landing.hero.buttons.0" href="#">One</a></section></body></html>');
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: canvas.window.document } } }));
    canvas.window.document.querySelector('a').click();
    const rows = () => [...document.querySelectorAll('[data-list-rows] [data-list-row]')];
    const names = () => rows().map((node) => node.querySelector('input').getAttribute('name'));
    return { rows, names, form };
}

test('unsaved edits raise the pending indicator and saving clears it', () => {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero"></button><button data-workspace-tool="select"></button><button data-workspace-tool="interact"></button>
        <input type="checkbox" data-studio-interact>
        <span data-studio-dirty hidden><span data-studio-dirty-text></span></span>
        <button type="button" data-studio-save-jump hidden></button>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form><section class="studio-save-card"><button type="submit"></button></section>
        <div data-studio-field="public.landing.hero.title_line1"><input name="hero" value="Initial"></div></form></div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    initWorkspace(form);
    const banner = () => document.querySelector('[data-studio-dirty]');
    const jump = () => document.querySelector('[data-studio-save-jump]');

    // Untouched: nothing pending.
    assert.equal(banner().hidden, true);
    assert.equal(jump().hidden, true);

    const input = form.querySelector('input[name="hero"]');
    input.value = 'Changed';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    assert.equal(banner().hidden, false);
    assert.equal(jump().hidden, false);
    assert.match(document.querySelector('[data-studio-dirty-text]').textContent, /۱|1/);

    // A save re-renders the page, so the baseline moves up with it.
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    assert.equal(banner().hidden, true);
});

test('selection mirrors into the URL hash and a deep link restores it', () => {
    const { frame, canvas } = setupPaths();
    frame().window.document.querySelector('h1').click();
    assert.match(decodeURIComponent(globalThis.location.hash), /studio=public\.landing\.hero\.title_line1/);

    // A fresh frame with nothing selected honours the hash the URL carries,
    // which is what makes a reload or a shared link reopen the element.
    const reopened = frame(canvas());
    const heading = reopened.window.document.querySelector('h1');
    assert.equal(heading.classList.contains('studio-inspected-object'), true);
    assert.equal(document.querySelector('[data-object-title]').textContent, 'Hero title');
});

test('in-canvas row actions add, duplicate, move and remove list rows', () => {
    const { rows, names, form } = setupListOps();
    const bar = () => document.querySelector('[data-context-fields] .studio-context-row-actions');
    assert.equal(Boolean(bar()), true);
    let notified = 0;
    form.addEventListener('input', () => notified++);
    const action = (i) => bar().querySelectorAll('.studio-context-row-action')[i];

    // Add: a fresh row is re-keyed away from the stored index.
    action(0).click();
    assert.equal(rows().length, 2);
    assert.equal(notified > 0, true);
    assert.notEqual(names()[0], names()[1]);

    // Duplicate is refused at max, and the button reports that.
    assert.equal(action(1).disabled, true);

    // Remove puts the count back and the list stays submittable.
    action(4).click();
    assert.equal(rows().length, 1);
    assert.equal(names().length, 1);
    assert.equal(action(0).disabled, false);
});
