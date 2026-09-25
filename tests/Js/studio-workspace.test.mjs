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
    // The adopted list is scoped to the selected row: its field wrapper says
    // so, and the note tells the user the siblings are hidden (the CSS does
    // the actual hiding — jsdom does not apply stylesheets).
    const field = panel().querySelector('[data-studio-field]');
    assert.equal(field.classList.contains('is-context-scoped'), true);
    assert.match(panel().querySelector('[data-context-note]').textContent, /siblings stay hidden/);
    // Closing the panel unscopes the field so the form is whole again.
    panel().querySelector('[data-context-clear]').click();
    assert.equal(field.classList.contains('is-context-scoped'), false);
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

// The overrides list is a real schema list inside the form; the context panel
// writes rows into it lazily, so select-only must not create anything and the
// first control edit must materialize exactly one path-addressed row.
// Node-local values ride the canvas textarea (the same blob the server
// cleans through cleanNodes): selection materializes nothing, the first edit
// writes, and the frame's editor stylesheet updates without a reload.
function setupNodeProps() {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero"></button><button data-studio-tab="blocks"></button>
        <button data-workspace-tool="select"></button><button data-workspace-tool="interact"></button>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <span data-studio-dirty hidden><span data-studio-dirty-text></span></span>
        <form>
        <section data-context-panel hidden><strong data-context-title></strong><p data-context-note></p><nav data-context-crumb hidden></nav><div data-context-fields></div><button type="button" data-context-clear></button></section>
        <textarea name="public[canvas][nodes]" data-studio-canvas-nodes></textarea>
        <div data-studio-group="hero"><div data-studio-field="public.landing.hero.title_line1"><label class="studio-field-label">Title</label><input name="hero" value="Initial"></div></div>
        </form></div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    initWorkspace(form);
    const canvas = new JSDOM('<!doctype html><html><head></head><body><section><h1 data-studio-path="public.landing.hero.title_line1">Hero title</h1></section></body></html>');
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: canvas.window.document } } }));
    canvas.window.document.querySelector('h1').click();
    const nodes = () => {
        try { return JSON.parse(form.querySelector('[data-studio-canvas-nodes]').value || '{}'); } catch { return {}; }
    };
    const cap = (id) => document.querySelector(`[data-cap="${id}"]`);
    return { form, canvas, nodes, cap };
}

test('node props are written lazily: selection creates nothing, the first edit does', () => {
    const { canvas, nodes, cap } = setupNodeProps();

    // Select-only: no node, no textarea content — creating one on mere
    // selection would fill the stored layer with empty path-only entries.
    assert.equal(Object.keys(nodes()).length, 0);
    assert.equal(canvas.window.document.getElementById('studio-editor-node-styles'), null);

    // The capability box opens by itself and offers only modules that apply:
    // an h1 carries text (typography) but is not focusable (no outline), and
    // a block display offers no flex alignment controls.
    const box = document.querySelector('.studio-context-caps');
    assert.ok(box, 'the capability box replaces the old override-row style box');
    assert.equal(box.open, true);
    assert.ok(cap('background'));
    assert.ok(cap('typography'));
    assert.equal(cap('outline'), null);
    assert.ok(cap('layout').querySelector('[aria-label="Display"]'));
    assert.equal(cap('layout').querySelector('[aria-label="Justify content"]'), null);

    // First edit: the node materializes with exactly that key, and the frame
    // already paints it — no reload.
    const background = cap('background').querySelector('input[type="color"]');
    background.value = '#112233';
    background.dispatchEvent(new Event('input', { bubbles: true }));
    const path = 'public.landing.hero.title_line1';
    assert.deepEqual(nodes()[path].props, { background: '#112233' });
    assert.equal(
        canvas.window.document.getElementById('studio-editor-node-styles').textContent,
        '[data-studio-path="public.landing.hero.title_line1"]{background:#112233;}',
    );
    // The unsaved-changes indicator sees canvas edits too: writeNodes fires
    // an input event exactly like a typed field would.
    assert.equal(document.querySelector('[data-studio-dirty]').hidden, false);

    // A second property reuses the same node instead of spawning another.
    const radius = cap('radius').querySelector('input[type="range"]');
    radius.value = '12';
    radius.dispatchEvent(new Event('input', { bubbles: true }));
    assert.equal(Object.keys(nodes()).length, 1);
    assert.equal(nodes()[path].props.radius, 12);

    // Reset-to-inherit removes the key so the layer below shows through.
    cap('background').querySelector('.studio-context-style-clear').click();
    assert.equal(nodes()[path].props.background, undefined);
    assert.equal(Object.keys(nodes()[path].props).length, 1);
    assert.equal(
        canvas.window.document.getElementById('studio-editor-node-styles').textContent.includes('background'),
        false,
    );
});

test('the breadcrumb walks the path chain and a display change reveals flex controls', () => {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero"></button>
        <button data-workspace-tool="select"></button>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form>
        <section data-context-panel hidden><strong data-context-title></strong><p data-context-note></p><nav data-context-crumb hidden></nav><div data-context-fields></div><button type="button" data-context-clear></button></section>
        <textarea name="public[canvas][nodes]" data-studio-canvas-nodes></textarea>
        </form></div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    initWorkspace(form);
    const canvas = new JSDOM(`<!doctype html><html><head></head><body>
        <template data-studio-section-marker="hero"></template>
        <section>
            <div data-studio-path="public.landing.hero">
                <span data-studio-path="public.landing.hero.buttons">
                    <a data-studio-path="public.landing.hero.buttons.0" href="#">Start</a>
                </span>
            </div>
        </section></body></html>`);
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: canvas.window.document } } }));
    const link = canvas.window.document.querySelector('a[data-studio-path]');
    link.dispatchEvent(new canvas.window.MouseEvent('click', { bubbles: true, cancelable: true }));

    // Breadcrumb: the path chain of the selection, root first.
    const crumbs = () => [...document.querySelectorAll('[data-context-crumb] .studio-context-crumb-item')];
    assert.deepEqual(crumbs().map((button) => button.textContent), ['hero', 'buttons', 'buttons.0']);
    assert.equal(document.querySelector('[data-context-crumb]').hidden, false);

    // Clicking an ancestor crumb selects it (the chain, not the leaf).
    crumbs()[0].click();
    assert.equal(
        canvas.window.document.querySelector('[data-studio-path="public.landing.hero"]')
            .classList.contains('studio-inspected-object'),
        true,
    );

    // Flex-only controls are gated on the computed display — and changing
    // display to flex rebuilds the box in place so they appear at once.
    const layout = document.querySelector('[data-cap="layout"]');
    assert.equal(layout.querySelector('[aria-label="Justify content"]'), null);
    const display = layout.querySelector('[aria-label="Display"]');
    display.value = 'flex';
    display.dispatchEvent(new Event('change', { bubbles: true }));
    assert.ok(document.querySelector('[data-cap="layout"] [aria-label="Justify content"]'));
    assert.equal(
        canvas.window.document.getElementById('studio-editor-node-styles').textContent,
        '[data-studio-path="public.landing.hero"]{display:flex;}',
    );
});

// Direct manipulation: handles live in the frame, every write goes through
// setNodeProps → the live stylesheet, and the route follows the layout model.
// jsdom has no layout, so rects are stubbed per element.
function setupDirect(options = {}) {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero"></button>
        <button data-workspace-tool="select"></button>
        <button data-workspace-tool="interact"></button>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <span data-studio-dirty hidden><span data-studio-dirty-text></span></span>
        <form>
        <section data-context-panel hidden><strong data-context-title></strong><p data-context-note></p><nav data-context-crumb hidden></nav><div data-context-fields></div><button type="button" data-context-clear></button></section>
        <textarea name="public[canvas][nodes]" data-studio-canvas-nodes>${options.nodes || ''}</textarea>
        </form></div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    initWorkspace(form);
    const canvas = new JSDOM(`<!doctype html><html><head></head><body>
        <template data-studio-section-marker="hero"></template>
        <section>
            <div data-studio-path="public.landing.hero" style="position:relative;display:flex">
                <h1 data-studio-path="public.landing.hero.title_line1">Title</h1>
                <span data-studio-path="public.landing.hero.buttons"><a data-studio-path="public.landing.hero.buttons.0" href="#">Start</a></span>
            </div>
        </section></body></html>`);
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: canvas.window.document } } }));
    const frame = canvas.window.document;
    const stub = (selector, rect) => {
        const element = frame.querySelector(selector);
        element.getBoundingClientRect = () => ({
            ...rect, right: rect.left + rect.width, bottom: rect.top + rect.height,
            x: rect.left, y: rect.top,
        });
        return element;
    };
    stub('section', { left: 0, top: 0, width: 440, height: 240 });
    stub('[data-studio-path="public.landing.hero"]', { left: 10, top: 20, width: 400, height: 80 });
    stub('[data-studio-path="public.landing.hero.title_line1"]', { left: 20, top: 30, width: 100, height: 50 });
    stub('[data-studio-path="public.landing.hero.buttons"]', { left: 200, top: 20, width: 60, height: 40 });
    stub('[data-studio-path="public.landing.hero.buttons.0"]', { left: 210, top: 30, width: 40, height: 20 });
    const pointer = (element, type, x, y) => element.dispatchEvent(new canvas.window.MouseEvent(type, {
        bubbles: true, cancelable: true, clientX: x, clientY: y, button: 0,
    }));
    const nodes = () => {
        try { return JSON.parse(form.querySelector('[data-studio-canvas-nodes]').value || '{}'); } catch { return {}; }
    };
    const layer = () => frame.getElementById('studio-handle-layer');
    const style = () => frame.getElementById('studio-editor-node-styles')?.textContent || '';
    return { form, canvas, frame, pointer, nodes, layer, style, stub };
}

test('direct manipulation routes drags by layout model and never converts to absolute', () => {
    const { frame, pointer, nodes, layer, style } = setupDirect();
    const hero = frame.querySelector('[data-studio-path="public.landing.hero"]');
    const heading = frame.querySelector('[data-studio-path="public.landing.hero.title_line1"]');
    const link = frame.querySelector('[data-studio-path="public.landing.hero.buttons.0"]');

    // Selecting shows the handle layer (rect stubbed, not locked).
    pointer(hero, 'click', 50, 40);
    assert.equal(layer().hidden, false);
    assert.equal(layer().querySelector('.studio-handle-badge').textContent, '400×80');

    // Positioned node (position:relative): a drag writes top/left — already
    // relative, so no conversion happens.
    pointer(hero, 'pointerdown', 100, 100);
    pointer(hero, 'pointermove', 90, 80); // dx -10, dy -20 → left 0, top 0
    assert.deepEqual(nodes()['public.landing.hero'].props, { left: 0, top: 0 });
    assert.ok(style().includes('left:0px;') && style().includes('top:0px;'));
    // Aligned to the parent's top-left corner: both guides light up.
    assert.equal(layer().querySelector('.studio-guide-v').hidden, false);
    assert.equal(layer().querySelector('.studio-guide-h').hidden, false);
    pointer(hero, 'pointerup', 90, 80);
    assert.equal(layer().querySelector('.studio-guide-v').hidden, true);

    // Static child of a flex container: the drag assigns order to the whole
    // set (a single node's order cannot land it among implicit zeros).
    pointer(heading, 'click', 40, 40);
    pointer(heading, 'pointerdown', 10, 10);
    pointer(heading, 'pointermove', 240, 10); // past the buttons' center → index 1
    assert.equal(nodes()['public.landing.hero.title_line1'].props.order, 1);
    assert.equal(nodes()['public.landing.hero.buttons'].props.order, 0);
    assert.ok(style().includes('order:1;'));
    pointer(heading, 'pointerup', 240, 10);

    // Resize: the SE handle writes width (%) and height (px). The rear handles
    // are hidden on a static node — its top/left edges are pinned by flow.
    assert.equal(layer().querySelector('.studio-handle--nw').hidden, true);
    assert.equal(layer().querySelector('.studio-handle--se').hidden, false);
    const se = layer().querySelector('.studio-handle--se');
    pointer(se, 'pointerdown', 0, 0);
    pointer(se, 'pointermove', 50, 30); // 100×50 → 150×80 of a 400px parent
    pointer(se, 'pointerup', 50, 30);
    const headingProps = nodes()['public.landing.hero.title_line1'].props;
    assert.equal(headingProps.width, 38);
    assert.equal(headingProps.height, '80px');
    assert.ok(style().includes('inline-size:38%;') && style().includes('block-size:80px;'));

    // Flow child that no list owns: nothing would persist, so the drag never
    // starts — and nothing about position changes hands.
    pointer(link, 'click', 210, 40);
    pointer(link, 'pointerdown', 210, 40);
    pointer(link, 'pointermove', 210, 140);
    pointer(link, 'pointerup', 210, 140);
    assert.equal(nodes()['public.landing.hero.buttons.0'], undefined);
    assert.equal(layer().hidden, false, 'the selection itself survives the refused drag');
});

test('lock, hide and interact mode keep the handles out of the way', () => {
    const { frame, pointer, layer } = setupDirect();
    const hero = frame.querySelector('[data-studio-path="public.landing.hero"]');
    pointer(hero, 'click', 50, 40);
    assert.equal(layer().hidden, false);

    const flag = (name) => document
        .querySelector('[data-leaf-path="public.landing.hero"]')
        .closest('li').querySelector(`[data-flag="${name}"]`);

    // Locking the selected node pulls the handles: canvas manipulation is
    // exactly what lock forbids (the tree keeps the way back).
    flag('locked').click();
    assert.equal(layer().hidden, true);
    flag('locked').click();
    assert.equal(layer().hidden, false);

    // Same for hide — a display:none node has nothing to grab.
    flag('hidden').click();
    assert.equal(layer().hidden, true);
    flag('hidden').click();
    assert.equal(layer().hidden, false);

    // Interact mode releases the canvas entirely.
    document.querySelector('[data-workspace-tool="interact"]').click();
    assert.equal(layer().hidden, true);
    document.querySelector('[data-workspace-tool="select"]').click();
    assert.equal(layer().hidden, false);
});

// Tokens: a prop may store `{token}` instead of a literal and emit var();
// detach materializes the token's effective value; the origin chip names where
// a value comes from; promote writes the token's GLOBAL value through its own
// schema field (the scope machinery of every other theme edit) before binding.
function setupTokens() {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero"></button>
        <button data-workspace-tool="select"></button>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form data-studio-tokens='{"colors.primary":"c-primary","colors.background":"c-background","colors.text":"c-text","shape.radius_md":"radius-md"}'>
        <section data-context-panel hidden><strong data-context-title></strong><p data-context-note></p><nav data-context-crumb hidden></nav><div data-context-fields></div><button type="button" data-context-clear></button></section>
        <textarea name="public[canvas][nodes]" data-studio-canvas-nodes></textarea>
        <div data-studio-group="theme"><div data-studio-field="theme.colors.primary">
            <input type="color" name="theme[colors][primary]" value="#000000" data-color-pick>
            <input type="text" class="settings-input studio-color-text" value="#000000" data-color-text readonly>
        </div></div>
        </form></div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    initWorkspace(form);
    const canvas = new JSDOM('<!doctype html><html><head></head><body><section><h1 data-studio-path="public.landing.hero.title_line1">Title</h1></section></body></html>');
    // jsdom resolves no stylesheet values; a flat stub makes the origin
    // logic deterministic (self and parent report the same colour → inherited).
    canvas.window.getComputedStyle = () => ({
        display: 'block', position: 'static', top: 'auto', left: 'auto',
        color: 'rgb(17, 34, 51)', padding: '', 'border-radius': '',
        getPropertyValue(property) { return this[property] ?? ''; },
    });
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: canvas.window.document } } }));
    canvas.window.document.querySelector('h1').click();
    const nodes = () => {
        try { return JSON.parse(form.querySelector('[data-studio-canvas-nodes]').value || '{}'); } catch { return {}; }
    };
    const cap = (id) => document.querySelector(`[data-cap="${id}"]`);
    const style = () => canvas.window.document.getElementById('studio-editor-node-styles')?.textContent || '';
    const origin = (capId, aria) => cap(capId).querySelector(`[aria-label="${aria}"]`)
        .closest('.studio-context-style').querySelector('.studio-context-origin');
    return { form, canvas, nodes, cap, style, origin };
}

test('token references bind, detach and promote through the schema field', () => {
    const { form, canvas, nodes, cap, style, origin } = setupTokens();
    const path = 'public.landing.hero.title_line1';
    const change = (element) => element.dispatchEvent(new Event('change', { bubbles: true }));

    // Fresh selection with no local value: same computed colour as the parent
    // (the stub), so the chip says the value is inherited.
    assert.equal(origin('typography', 'Text color').dataset.kind, 'inherited');

    // Bind to a token: the prop stores the reference and the live stylesheet
    // emits var() — the literal control locks while the token owns the value.
    const bindColor = () => cap('typography').querySelector('select[aria-label="Text color — value source"]');
    bindColor().value = 'colors.primary';
    change(bindColor());
    assert.deepEqual(nodes()[path].props.color, { token: 'colors.primary' });
    assert.ok(style().includes('color:var(--c-primary);'));
    assert.equal(origin('typography', 'Text color').dataset.kind, 'token');
    assert.equal(cap('typography').querySelector('input[type="color"]').disabled, true);

    // Detach: the token's CURRENT effective value becomes the local literal —
    // explicit, never a silent swap to a stale guess.
    const unbindColor = () => cap('typography').querySelector('select[aria-label="Text color — value source"]');
    unbindColor().value = '';
    change(unbindColor());
    assert.equal(nodes()[path].props.color, '#112233');
    assert.ok(style().includes('color:#112233;'));
    assert.equal(origin('typography', 'Text color').dataset.kind, 'local');

    // Promote ("create a token"): the local background value is written into
    // the token's GLOBAL value through its schema field, then the node binds.
    const backgroundPicker = () => cap('background').querySelector('input[type="color"]');
    backgroundPicker().value = '#112233';
    backgroundPicker().dispatchEvent(new Event('input', { bubbles: true }));
    assert.equal(origin('background', 'Color').dataset.kind, 'local');
    cap('background').querySelector('.studio-context-promote').click();
    const target = cap('background').querySelector('select[aria-label="Color — target token"]');
    assert.ok(target, 'the promote step asks for a target token');
    target.value = 'colors.primary';
    change(target);
    const confirm = [...cap('background').querySelectorAll('.studio-context-promote')]
        .find((button) => button.textContent === 'Save');
    confirm.click();

    const themeInput = form.querySelector('[name="theme[colors][primary]"]');
    assert.equal(themeInput.value, '#112233');
    assert.equal(form.querySelector('[data-color-text]').value, '#112233');
    assert.deepEqual(nodes()[path].props.background, { token: 'colors.primary' });
    assert.ok(style().includes('background:var(--c-primary);'));
    assert.equal(origin('background', 'Color').dataset.kind, 'token');
});

// State & breakpoint layers: the switcher routes writes into the node's
// `states`/`breakpoints` maps, mirrors the status-bar device buttons in both
// directions, and resets per layer. theme-studio.js owns the real device
// chrome (not loaded here), so the fixture stubs the two attributes it
// maintains — aria-pressed on the device buttons.
function setupLayers() {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero"></button>
        <button data-workspace-tool="select"></button>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form data-studio-tokens='{"colors.primary":"c-primary","shape.radius_md":"radius-md"}'>
        <section data-context-panel hidden><strong data-context-title></strong><p data-context-note></p><nav data-context-crumb hidden></nav><div data-context-fields></div><button type="button" data-context-clear></button></section>
        <textarea name="public[canvas][nodes]" data-studio-canvas-nodes></textarea>
        <div data-studio-group="theme"><div data-studio-field="theme.colors.primary">
            <input type="color" name="theme[colors][primary]" value="#000000" data-color-pick>
            <input type="text" class="settings-input studio-color-text" value="#000000" data-color-text readonly>
        </div></div>
        </form>
        <footer>
        <button data-studio-preview-device="desktop" aria-pressed="true">desktop</button>
        <button data-studio-preview-device="tablet" aria-pressed="false">tablet</button>
        <button data-studio-preview-device="mobile" aria-pressed="false">mobile</button>
        </footer>
        </div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    document.querySelectorAll('[data-studio-preview-device]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-studio-preview-device]').forEach((other) => {
                other.setAttribute('aria-pressed', String(other === button));
            });
        });
    });
    const form = document.querySelector('form');
    initWorkspace(form);
    const canvas = new JSDOM('<!doctype html><html><head></head><body><section><h1 data-studio-path="public.landing.hero.title_line1">Title</h1></section></body></html>');
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: canvas.window.document } } }));
    canvas.window.document.querySelector('h1').click();
    const nodes = () => {
        try { return JSON.parse(form.querySelector('[data-studio-canvas-nodes]').value || '{}'); } catch { return {}; }
    };
    const cap = (id) => document.querySelector(`[data-cap="${id}"]`);
    const style = () => canvas.window.document.getElementById('studio-editor-node-styles')?.textContent || '';
    const origin = (capId, aria) => cap(capId).querySelector(`[aria-label="${aria}"]`)
        .closest('.studio-context-style').querySelector('.studio-context-origin');
    const state = () => document.querySelector('select[aria-label="Edit state"]');
    const size = () => document.querySelector('select[aria-label="Edit width"]');
    const pressed = (device) => document.querySelector(`[data-studio-preview-device="${device}"]`)
        .getAttribute('aria-pressed') === 'true';
    return { form, canvas, nodes, cap, style, origin, state, size, pressed };
}

test('state and breakpoint layers route writes and mirror the device switcher', () => {
    const { nodes, cap, style, origin, state, size, pressed } = setupLayers();
    const path = 'public.landing.hero.title_line1';
    const P = `[data-studio-path="${path}"]`;
    const change = (element) => element.dispatchEvent(new Event('change', { bubbles: true }));
    const radius = (value) => {
        const bar = cap('radius').querySelector('input[type="range"]');
        bar.value = String(value);
        bar.dispatchEvent(new Event('input', { bubbles: true }));
    };

    // Base is the default layer: a plain edit lands in props as before.
    radius(6);
    assert.deepEqual(nodes()[path].props, { radius: 6 });

    // Picking a width layer CLICKS the status-bar device — the preview and
    // the override being written are the same width by construction.
    size().value = 'mobile';
    change(size());
    assert.equal(pressed('mobile'), true);
    assert.equal(pressed('desktop'), false);
    radius(9);
    assert.deepEqual(nodes()[path].breakpoints.mobile, { radius: 9 });
    assert.equal(nodes()[path].props.radius, 6, 'the base layer is untouched');
    assert.ok(style().includes(`${P}{border-radius:6px;}`));
    assert.ok(style().includes(`@media (max-width:640px){${P}{border-radius:9px;}}`));

    // One layer at a time: entering a state clears the width layer (the
    // stored model has no combined row to put a hybrid value in).
    state().value = 'hover';
    change(state());
    assert.equal(size().value, '');
    const background = cap('background').querySelector('input[type="color"]');
    background.value = '#223344';
    background.dispatchEvent(new Event('input', { bubbles: true }));
    assert.deepEqual(nodes()[path].states.hover, { background: '#223344' });
    assert.equal(nodes()[path].props.background, undefined);
    assert.ok(style().includes(`${P}:hover{background:#223344;}`));
    assert.equal(origin('background', 'Color').dataset.kind, 'state');

    // The other half of the mirror: clicking a device in the status bar
    // enters that width layer here and leaves the state layer.
    document.querySelector('[data-studio-preview-device="tablet"]').click();
    assert.equal(size().value, 'tablet');
    assert.equal(state().value, '');
    assert.equal(pressed('tablet'), true);

    // Per-context reset: back to mobile (which holds values), wipe that row
    // only — base props and the hover row survive.
    size().value = 'mobile';
    change(size());
    document.querySelector('.studio-context-layers .studio-context-layer-clear').click();
    assert.equal(nodes()[path].breakpoints.mobile, undefined);
    assert.equal(nodes()[path].props.radius, 6);
    assert.deepEqual(nodes()[path].states.hover, { background: '#223344' });
    assert.equal(style().includes('@media'), false);
    assert.ok(style().includes(`${P}:hover{background:#223344;}`));
});

test('token references bind and detach inside a state layer through the schema field', () => {
    const { form, nodes, cap, style, origin, state } = setupLayers();
    const path = 'public.landing.hero.title_line1';
    const change = (element) => element.dispatchEvent(new Event('change', { bubbles: true }));

    state().value = 'hover';
    change(state());

    // Bind: the reference stores under states.hover and the live stylesheet
    // paints the pseudo-class rule with var() — the same shape the server
    // emits on save.
    const bindColor = () => cap('typography').querySelector('select[aria-label="Text color — value source"]');
    bindColor().value = 'colors.primary';
    change(bindColor());
    assert.deepEqual(nodes()[path].states.hover.color, { token: 'colors.primary' });
    assert.deepEqual(nodes()[path].props, {}, 'the base layer holds no value');
    assert.ok(style().includes(':hover{color:var(--c-primary);}'));
    assert.equal(origin('typography', 'Text color').dataset.kind, 'token');
    assert.equal(cap('typography').querySelector('input[type="color"]').disabled, true);

    // Detach inside a layer reads the token's SCHEMA FIELD, not the computed
    // style: hover is not active on demand, so computed still reports the
    // base value and a computed-based materialize would copy the wrong one.
    form.querySelector('[name="theme[colors][primary]"]').value = '#556677';
    const unbindColor = () => cap('typography').querySelector('select[aria-label="Text color — value source"]');
    unbindColor().value = '';
    change(unbindColor());
    assert.equal(nodes()[path].states.hover.color, '#556677');
    assert.ok(style().includes(':hover{color:#556677;}'));
    assert.equal(origin('typography', 'Text color').dataset.kind, 'state');
    assert.equal(cap('typography').querySelector('input[type="color"]').disabled, false);
});

test('layer tree lists sections with their named elements and mirrors selection', () => {
    const { frame } = setupPaths();
    const canvas = frame();
    const buttons = () => [...document.querySelectorAll('[data-page-layers] button')];
    // One section button + one leaf per data-studio-path element.
    assert.equal(buttons().length, 3);
    assert.equal(buttons()[0].textContent, 'Hero');
    assert.equal(buttons()[1].textContent, 'Hero title');
    assert.equal(buttons()[2].textContent, 'Start');

    // Clicking a leaf selects that element in the canvas.
    buttons()[2].click();
    assert.equal(canvas.window.document.querySelector('a').classList.contains('studio-inspected-object'), true);
    assert.match(decodeURIComponent(globalThis.location.hash), /buttons\.0/);

    // The tree mirrors the current selection.
    assert.equal(buttons()[2].getAttribute('aria-current'), 'true');
    assert.equal(buttons()[1].getAttribute('aria-current'), 'false');
});

// A nested canvas: a path element may contain other path elements, so the
// tree must nest by DOM ancestry instead of flattening to two levels.
function setupNested() {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero" aria-label="Hero"></button>
        <button data-workspace-tool="select"></button>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form></form></div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    initWorkspace(form);
    const canvas = new JSDOM(`<!doctype html><html><head></head><body>
        <template data-studio-section-marker="hero"></template>
        <section>
            <div data-studio-path="public.landing.hero">
                <h1 data-studio-path="public.landing.hero.title_line1">Title</h1>
                <span data-studio-path="public.landing.hero.buttons">
                    <a data-studio-path="public.landing.hero.buttons.0">Start</a>
                </span>
            </div>
        </section></body></html>`);
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: canvas.window.document } } }));
    return { form, canvas };
}

test('the layer tree nests path elements under their path ancestors', () => {
    const { canvas } = setupNested();
    const button = (path) => document.querySelector(`[data-page-layers] [data-leaf-path="${path}"]`);
    const liOf = (path) => button(path).closest('li');
    // How many tree rows the button itself sits inside (its own row counts as 1).
    const depth = (path) => {
        let n = 0;
        for (let node = button(path); node; node = node.parentElement) {
            if (node.matches('.studio-layer')) n++;
        }
        return n;
    };

    for (const path of [
        'public.landing.hero', 'public.landing.hero.title_line1',
        'public.landing.hero.buttons', 'public.landing.hero.buttons.0',
    ]) assert.ok(button(path), `${path} missing from the tree`);

    // Siblings share a depth; a child sits one level deeper.
    assert.equal(depth('public.landing.hero.title_line1'), depth('public.landing.hero.buttons'));
    assert.ok(depth('public.landing.hero.buttons.0') > depth('public.landing.hero.buttons'));
    assert.equal(liOf('public.landing.hero.buttons.0').parentElement.closest('li'), liOf('public.landing.hero.buttons'));

    // Every row draws one dashed guide per ancestor level; the deepest guide
    // carries the connector tick. The section row (depth 0) draws none.
    const guides = (path) => [...liOf(path).children].filter((node) => node.classList.contains('studio-layer-guide'));
    assert.equal(guides('public.landing.hero').length, 1);
    assert.equal(guides('public.landing.hero')[0].classList.contains('is-connect'), true);
    assert.equal(guides('public.landing.hero.buttons.0').length, 3);
    assert.equal(guides('public.landing.hero.buttons.0')[2].classList.contains('is-connect'), true);
    const sectionRow = liOf('public.landing.hero').parentElement.closest('li');
    const ownGuides = [...sectionRow.children].filter((node) => node.classList.contains('studio-layer-guide'));
    assert.equal(ownGuides.length, 0, 'section rows sit at depth zero');

    // The container and the section both collapse: only rows with children
    // carry a toggle.
    const toggle = (path) => liOf(path).querySelector('.studio-layer-toggle');
    assert.ok(toggle('public.landing.hero'), 'a path row with children needs a toggle');
    assert.equal(toggle('public.landing.hero.title_line1'), null, 'a leaf row must not');
    const branch = toggle('public.landing.hero');
    branch.click();
    assert.equal(branch.getAttribute('aria-expanded'), 'false');
    assert.equal(liOf('public.landing.hero').classList.contains('is-collapsed'), true);
    branch.click();
    assert.equal(branch.getAttribute('aria-expanded'), 'true');
    assert.equal(liOf('public.landing.hero').classList.contains('is-collapsed'), false);

    // Selecting the deepest node still selects it in the canvas.
    button('public.landing.hero.buttons.0').click();
    assert.equal(canvas.window.document.querySelector('a').classList.contains('studio-inspected-object'), true);
    assert.equal(button('public.landing.hero.buttons.0').getAttribute('aria-current'), 'true');
});

// Lock & visibility: flags ride the form's canvas textarea (the same blob the
// server cleans), the tree toggles them, a canvas click stops at a locked
// subtree, and hide mirrors into the frame as display:none while the row stays
// put as the way back.
function setupLocks() {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero" aria-label="Hero"></button>
        <button data-workspace-tool="select"></button>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form><textarea data-studio-canvas-nodes>{
"public.landing.hero":{"props":{},"states":{},"breakpoints":{},"flags":{}},
"public.landing.hero.buttons":{"props":{},"states":{},"breakpoints":{},"flags":{}}
}</textarea></form></div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    initWorkspace(form);
    const canvas = new JSDOM(`<!doctype html><html><head></head><body>
        <template data-studio-section-marker="hero"></template>
        <section>
            <div data-studio-path="public.landing.hero">
                <h1 data-studio-path="public.landing.hero.title_line1">Title</h1>
                <span data-studio-path="public.landing.hero.buttons">
                    <a data-studio-path="public.landing.hero.buttons.0">Start</a>
                </span>
            </div>
        </section></body></html>`);
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: canvas.window.document } } }));
    const nodes = () => {
        try { return JSON.parse(form.querySelector('[data-studio-canvas-nodes]').value || '{}'); } catch { return {}; }
    };
    const leaf = (path) => document.querySelector(`[data-leaf-path="${path}"]`);
    const flagOf = (path, flag) => leaf(path).closest('li').querySelector(`[data-flag="${flag}"]`);
    return { form, canvas, nodes, leaf, flagOf };
}

test('lock and hide flags ride the canvas document and the tree restores hide', () => {
    const { canvas, nodes, leaf, flagOf } = setupLocks();

    // Nothing hidden yet: no mirror stylesheet in the frame.
    assert.equal(canvas.window.document.getElementById('studio-editor-node-styles'), null);

    // Hide: the flag lands in the transport JSON and mirrors into the frame.
    flagOf('public.landing.hero.buttons', 'hidden').click();
    assert.equal(nodes()['public.landing.hero.buttons'].flags.hidden, true);
    assert.equal(
        canvas.window.document.getElementById('studio-editor-node-styles').textContent,
        '[data-studio-path="public.landing.hero.buttons"]{display:none;}',
    );
    const row = leaf('public.landing.hero.buttons').closest('li');
    assert.equal(row.classList.contains('is-hidden'), true);
    assert.equal(flagOf('public.landing.hero.buttons', 'hidden').getAttribute('aria-pressed'), 'true');

    // The row never leaves: visibility is restorable from the tree even though
    // the element is out of the canvas flow. An emptied node is forgotten, the
    // same sparse-diff rule the server applies.
    flagOf('public.landing.hero.buttons', 'hidden').click();
    assert.equal(nodes()['public.landing.hero.buttons'], undefined);
    assert.equal(canvas.window.document.getElementById('studio-editor-node-styles'), null);
    assert.ok(leaf('public.landing.hero.buttons'), 'the row must stay to restore visibility');
    assert.equal(row.classList.contains('is-hidden'), false);

    // Lock is editor-only: the flag rides the document, but no stylesheet is
    // touched — the public page never learns about it.
    flagOf('public.landing.hero.title_line1', 'locked').click();
    assert.equal(nodes()['public.landing.hero.title_line1'].flags.locked, true);
    assert.equal(leaf('public.landing.hero.title_line1').closest('li').classList.contains('is-locked'), true);
    assert.equal(flagOf('public.landing.hero.title_line1', 'locked').getAttribute('aria-pressed'), 'true');
    assert.equal(canvas.window.document.getElementById('studio-editor-node-styles'), null);
});

test('a canvas click stops at a locked subtree instead of selecting inside it', () => {
    const { canvas, leaf, flagOf } = setupLocks();
    const click = (element) => element.dispatchEvent(new canvas.window.MouseEvent('click', { bubbles: true, cancelable: true }));
    const hero = canvas.window.document.querySelector('[data-studio-path="public.landing.hero"]');
    const buttons = canvas.window.document.querySelector('[data-studio-path="public.landing.hero.buttons"]');
    const link = canvas.window.document.querySelector('[data-studio-path="public.landing.hero.buttons.0"]');
    const title = canvas.window.document.querySelector('[data-studio-path="public.landing.hero.title_line1"]');
    const inspected = () => canvas.window.document.querySelectorAll('.studio-inspected-object');

    // Lock the container: a click anywhere inside selects the first path
    // element above the lock, never one within it.
    flagOf('public.landing.hero.buttons', 'locked').click();
    click(link);
    assert.equal(hero.classList.contains('studio-inspected-object'), true);
    assert.equal(link.classList.contains('studio-inspected-object'), false);
    assert.equal(leaf('public.landing.hero').getAttribute('aria-current'), 'true');
    assert.equal(leaf('public.landing.hero.buttons').getAttribute('aria-current'), 'false');
    click(buttons);
    assert.equal(inspected().length, 1);
    assert.equal(hero.classList.contains('studio-inspected-object'), true);

    // Lock the ancestor too: now a lock owns the whole chain, so the click
    // clears the selection instead of falling back to the generic tag selector
    // (which would happily select the locked link anyway).
    flagOf('public.landing.hero', 'locked').click();
    click(link);
    assert.equal(inspected().length, 0);
    assert.equal(document.querySelector('[data-object-title]').textContent, 'No element selected');

    // Unlocking the ancestor restores normal selection for unlocked nodes.
    flagOf('public.landing.hero', 'locked').click();
    click(title);
    assert.equal(title.classList.contains('studio-inspected-object'), true);
    assert.equal(inspected().length, 1);
});

test('the inspector diagnostics collapse and the choice persists', () => {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero"></button>
        <section data-object-inspector><header class="studio-object-head"><h2 data-object-title></h2><button type="button" data-object-collapse aria-expanded="false"></button></header><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form><div data-studio-field="public.landing.hero.title_line1"><input name="hero" value="Initial"></div></form>
    </div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent']) globalThis[key] = dom.window[key];
    globalThis.localStorage = dom.window.localStorage;
    try {
        const inspector = document.querySelector('[data-object-inspector]');
        const button = document.querySelector('[data-object-collapse]');
        initWorkspace(document.querySelector('form'));
        // Collapsed by default: read-mostly diagnostics must not crowd out
        // the editable fields above them.
        assert.equal(inspector.classList.contains('is-collapsed'), true);
        assert.equal(button.getAttribute('aria-expanded'), 'false');
        button.click();
        assert.equal(inspector.classList.contains('is-collapsed'), false);
        assert.equal(button.getAttribute('aria-expanded'), 'true');
        assert.equal(localStorage.getItem('studio.inspector'), '1');
        button.click();
        assert.equal(inspector.classList.contains('is-collapsed'), true);
        assert.equal(localStorage.getItem('studio.inspector'), '0');
    } finally {
        delete globalThis.localStorage;
    }
});

// The save card and the top toolbar are gone; the status-bar save button
// opens a scope menu whose buttons submit the studio form via `form`.
function setupSaveMenu() {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero"></button>
        <span data-studio-dirty hidden><span data-studio-dirty-text></span></span>
        <button type="button" data-studio-save-jump hidden></button>
        <div data-studio-save-menu>
            <button type="submit" form="studio-form" name="scope" value="preview">preview</button>
            <button type="submit" form="studio-form" name="scope" value="everyone">everyone</button>
        </div>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form id="studio-form"><div data-studio-field="public.landing.hero.title_line1"><input name="hero" value="Initial"></div></form>
        <div id="outside">x</div>
    </div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    initWorkspace(form);
    return {
        form,
        menu: () => document.querySelector('[data-studio-save-menu]'),
        jump: () => document.querySelector('[data-studio-save-jump]'),
    };
}

test('the status-bar save button opens a scope menu that submits the studio form', () => {
    const { form, menu, jump } = setupSaveMenu();
    assert.equal(menu().classList.contains('is-open'), false);
    jump().click();
    assert.equal(menu().classList.contains('is-open'), true);

    // A click anywhere outside closes it without submitting anything.
    document.getElementById('outside').dispatchEvent(new Event('click', { bubbles: true }));
    assert.equal(menu().classList.contains('is-open'), false);

    jump().click();
    const submissions = [];
    form.addEventListener('submit', (event) => {
        event.preventDefault(); // jsdom would otherwise try to navigate
        submissions.push(event.submitter?.value);
    });
    const everyone = menu().querySelector('button[value="everyone"]');
    assert.equal(everyone.form, form);
    everyone.click();
    assert.deepEqual(submissions, ['everyone']);
    assert.equal(menu().classList.contains('is-open'), false);
});

test('the workspace tool buttons drive interaction mode without a checkbox', () => {
    const { frame } = setup();
    const canvas = frame();
    // Default tool is select: the canvas is in editing mode.
    assert.equal(canvas.window.document.documentElement.classList.contains('studio-canvas-editing'), true);
    assert.equal(document.querySelector('[data-workspace-tool="select"]').getAttribute('aria-pressed'), 'true');

    document.querySelector('[data-workspace-tool="interact"]').click();
    assert.equal(canvas.window.document.documentElement.classList.contains('studio-canvas-editing'), false);
    assert.equal(document.querySelector('[data-workspace-tool="interact"]').getAttribute('aria-pressed'), 'true');

    // Interactions pass through while the hand tool is active.
    const event = new canvas.window.MouseEvent('click', { bubbles: true, cancelable: true });
    canvas.window.document.querySelector('a').dispatchEvent(event);
    assert.equal(event.defaultPrevented, false);

    document.querySelector('[data-workspace-tool="select"]').click();
    assert.equal(canvas.window.document.documentElement.classList.contains('studio-canvas-editing'), true);
});

test('the inspector column collapses, persists, and reopens on selection', () => {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero" aria-label="Hero"></button>
        <section data-object-inspector><header class="studio-object-head"><h2 data-object-title></h2><button type="button" data-inspector-toggle aria-expanded="true"></button></header><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form><div data-studio-group="hero"><div data-studio-field="public.landing.hero.title_line1"><input name="hero" value="Initial"></div></div></form>
    </div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    globalThis.localStorage = dom.window.localStorage;
    try {
        const root = document.getElementById('studio-split');
        const toggle = document.querySelector('[data-inspector-toggle]');
        initWorkspace(document.querySelector('form'));

        // Open by default; the toggle folds the column away and persists.
        assert.equal(root.classList.contains('is-inspector-closed'), false);
        toggle.click();
        assert.equal(root.classList.contains('is-inspector-closed'), true);
        assert.equal(toggle.getAttribute('aria-expanded'), 'false');
        assert.equal(localStorage.getItem('studio.inspector.open'), '0');

        // A rail tab click reopens a collapsed inspector.
        document.querySelector('[data-studio-tab="hero"]').click();
        assert.equal(root.classList.contains('is-inspector-closed'), false);
        assert.equal(localStorage.getItem('studio.inspector.open'), '1');
    } finally {
        delete globalThis.localStorage;
    }
});

test('the status-bar error indicator merges the server seed with live 422s', () => {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero"></button>
        <ul data-studio-error-seed hidden><li data-message="Server error one"></li></ul>
        <footer class="studio-statusbar">
            <span><button type="button" data-studio-errors-toggle hidden><span data-studio-errors-count>0</span></button></span>
        </footer>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form><div data-studio-group="hero"><div data-studio-field="public.landing.hero.title_line1"><input name="hero" value="Initial"></div></div></form>
    </div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    initWorkspace(form);
    const toggle = () => document.querySelector('[data-studio-errors-toggle]');
    const count = () => document.querySelector('[data-studio-errors-count]');

    // Seeded from the server-rendered list.
    assert.equal(toggle().hidden, false);
    assert.equal(count().textContent, '1');

    // Live 422s join in; an empty list clears only the live side.
    form.dispatchEvent(new CustomEvent('studio:errors', { detail: { errors: [
        { path: 'public.landing.hero.title_line1', message: 'Live error two' },
    ] } }));
    assert.equal(count().textContent, '2');
    form.dispatchEvent(new CustomEvent('studio:errors', { detail: { errors: [] } }));
    assert.equal(count().textContent, '1');

    // The popover lists the messages and a row jumps to its field.
    toggle().click();
    const rows = [...document.querySelectorAll('.studio-errors-popover button')];
    assert.equal(rows.length, 1);
    assert.equal(rows[0].textContent, 'Server error one');
    toggle().click();
    assert.equal(document.querySelector('.studio-errors-popover').classList.contains('is-open'), false);
});

test('the panel sashes resize by drag, collapse under the snap and reopen', () => {
    const dom = new JSDOM(`<div id="studio-split">
        <div class="studio-sash" data-studio-sash="rail"></div>
        <div class="studio-sash" data-studio-sash="inspector"></div>
        <button data-studio-tab="hero"></button>
        <section data-object-inspector><header><button type="button" data-inspector-toggle aria-expanded="true"></button></header><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form><div data-studio-group="hero"><div data-studio-field="public.landing.hero.title_line1"><input name="hero" value="Initial"></div></div></form>
    </div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'location', 'history']) globalThis[key] = dom.window[key];
    globalThis.localStorage = dom.window.localStorage;
    try {
        const root = document.getElementById('studio-split');
        root.getBoundingClientRect = () => ({ left: 0, right: 1000, top: 0, bottom: 600, width: 1000, height: 600 });
        const rail = root.querySelector('[data-studio-sash="rail"]');
        const inspector = root.querySelector('[data-studio-sash="inspector"]');
        const fire = (el, type, x) => el.dispatchEvent(new dom.window.MouseEvent(type, {
            bubbles: true, cancelable: true, clientX: x, button: 0,
        }));
        initWorkspace(document.querySelector('form'));
        const width = (name) => root.style.getPropertyValue(name);

        // Defaults land as inline CSS variables on the split.
        assert.equal(width('--rail-w'), '240px');
        assert.equal(width('--inspector-w'), '340px');

        // Drag past the snap: the rail keeps the dragged width and stores it.
        fire(rail, 'pointerdown', 300);
        fire(rail, 'pointermove', 360);
        fire(rail, 'pointerup', 360);
        assert.equal(width('--rail-w'), '360px');
        assert.equal(localStorage.getItem('studio.rail.w'), '360');

        // Release under the snap threshold: the rail folds to zero.
        fire(rail, 'pointerdown', 360);
        fire(rail, 'pointermove', 40);
        fire(rail, 'pointerup', 40);
        assert.equal(width('--rail-w'), '0px');

        // Double-click restores the default width.
        fire(rail, 'dblclick', 0);
        assert.equal(width('--rail-w'), '240px');

        // Inspector: a shallow release collapses the column the same way the
        // toggle button does (class + persisted open flag + width variable).
        fire(inspector, 'pointerdown', 980);
        fire(inspector, 'pointerup', 980);
        assert.equal(width('--inspector-w'), '0px');
        assert.equal(root.classList.contains('is-inspector-closed'), true);
        assert.equal(localStorage.getItem('studio.inspector.open'), '0');

        // Dragging back out from the collapsed edge reopens at the new width.
        fire(inspector, 'pointerdown', 990);
        fire(inspector, 'pointermove', 700);
        fire(inspector, 'pointerup', 700);
        assert.equal(width('--inspector-w'), '300px');
        assert.equal(root.classList.contains('is-inspector-closed'), false);
        assert.equal(localStorage.getItem('studio.inspector.open'), '1');
        assert.equal(localStorage.getItem('studio.inspector.w'), '300');
    } finally {
        delete globalThis.localStorage;
    }
});
