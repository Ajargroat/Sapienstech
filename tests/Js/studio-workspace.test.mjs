import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import initWorkspace from '../../resources/js/features/studio-workspace.js';

function setup() {
    const dom = new JSDOM(`<div id="studio-split">
        <button data-studio-tab="hero" aria-label="Hero"></button><button data-studio-tab="colors"></button><button data-studio-tab="blocks"></button>
        <button data-workspace-tool="select"></button><button data-workspace-tool="interact"></button>
        <button data-workspace-insert="button"></button><button data-workspace-action="undo"></button>
        <input type="checkbox" data-studio-interact>
        <section data-object-inspector><h2 data-object-title></h2><p data-object-scope></p><dl data-object-metrics></dl><div data-object-controls></div><ul data-page-layers></ul></section>
        <form><div data-studio-group="hero"><div data-studio-field="public.landing.hero.title_line1"><label class="studio-field-label">Title</label><input name="hero" value="Initial"></div></div>
        <div data-block-editor><button type="button" data-block-insert="button"></button><button type="button" data-block-undo disabled></button></div></form></div>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent']) globalThis[key] = dom.window[key];
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
