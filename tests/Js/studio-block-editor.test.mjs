import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import initBlockEditor from '../../resources/js/features/studio-block-editor.js';

function setup() {
    const row = `<div data-list-row><div class="studio-list-row-head"><button type="button" class="list-move-up">Up</button></div><div class="studio-list-fields">
        <input type="hidden" name="items[__KEY__][id]" value="">
        <div data-list-type><input type="radio" name="items[__KEY__][type]" value="heading" checked><input type="radio" name="items[__KEY__][type]" value="button"></div>
        <input name="items[__KEY__][title]" value="First"><textarea name="items[__KEY__][text]"></textarea>
        <input name="items[__KEY__][href]"><input name="items[__KEY__][src]">
        ${['background', 'color', 'padding', 'radius', 'width'].map((key) => `<input name="items[__KEY__][${key}]"><span></span>`).join('')}
        <input type="checkbox" name="items[__KEY__][visible]" value="1" checked></div></div>`;
    const dom = new JSDOM(`<button data-studio-tab="blocks"></button><input type="checkbox" data-studio-interact>
        <form><div data-sections><input type="checkbox" value="blocks"></div><div data-block-editor data-max="24">
        <button type="button" data-block-insert="button">Button</button>
        ${['undo', 'redo', 'duplicate', 'reset-style'].map((key) => `<button type="button" data-block-${key}></button>`).join('')}
        <div data-block-layers></div><div data-block-status></div>
        <div data-list-rows>${row.replaceAll('__KEY__', '0')}${row.replaceAll('__KEY__', '1').replace('value="First"', 'value="Second"')}</div>
        <template data-list-template>${row}</template><button type="button" data-list-add></button></div></form>`, { url: 'http://tenant.test' });
    for (const key of ['document', 'Event', 'CustomEvent', 'FormData']) globalThis[key] = dom.window[key];
    const form = document.querySelector('form');
    const wrap = document.querySelector('[data-block-editor]');
    wrap.studioList = {
        wireRow() {},
        refresh(notify) {
            if (notify) form.dispatchEvent(new Event('input', { bubbles: true }));
            wrap.dispatchEvent(new CustomEvent('studio:list-changed', { detail: { notify } }));
        },
    };
    const editor = initBlockEditor(form, wrap);
    const rows = () => [...wrap.querySelectorAll('[data-list-rows] [data-list-row]')];
    const input = (row, key) => row.querySelector(`[name$="[${key}]"]`);
    return { dom, form, wrap, editor, rows, input };
}

test('selection isolates the inspector and undo restores the submitted style', () => {
    const { form, wrap, rows, input } = setup();
    wrap.querySelectorAll('[data-layer-id]')[1].click();
    assert.equal(rows()[0].hidden, true);
    assert.equal(rows()[1].hidden, false);
    input(rows()[1], 'padding').value = '24';
    input(rows()[1], 'padding').dispatchEvent(new Event('change', { bubbles: true }));
    assert.equal(new FormData(form).get('items[1][padding]'), '24');
    wrap.querySelector('[data-block-undo]').click();
    assert.equal(input(rows()[1], 'padding').value, '');
    assert.equal(rows()[1].hidden, false);
    const paddingValues = [...new FormData(form)].filter(([name]) => name.endsWith('[padding]')).map(([, value]) => value);
    assert.deepEqual(paddingValues, ['', '']);
    wrap.querySelector('[data-block-redo]').click();
    assert.equal(input(rows()[1], 'padding').value, '24');
});

test('insert and duplicate use distinct stable IDs and are single undo steps', () => {
    const { form, wrap, rows, input } = setup();
    wrap.querySelector('[data-block-insert]').click();
    assert.equal(form.querySelector('[data-sections] input').checked, true);
    assert.equal(rows().length, 3);
    assert.equal(input(rows()[2], 'title').value, 'دکمه جدید');
    assert.equal(new Set(rows().map((row) => input(row, 'id').value)).size, 3);
    wrap.querySelector('[data-block-undo]').click();
    assert.equal(rows().length, 2);
    assert.equal(form.querySelector('[data-sections] input').checked, false);
    wrap.querySelector('[data-block-redo]').click();
    wrap.querySelector('[data-block-duplicate]').click();
    assert.equal(rows().length, 4);
    assert.equal(new Set(rows().map((row) => input(row, 'id').value)).size, 4);
    wrap.querySelector('[data-block-undo]').click();
    assert.equal(rows().length, 3);
});

test('plain-HTTP insertion generates valid unique UUIDs stable through edits and undo', () => {
    const descriptor = Object.getOwnPropertyDescriptor(globalThis, 'crypto');
    const crypto = globalThis.crypto;
    Object.defineProperty(globalThis, 'crypto', {
        configurable: true,
        value: { getRandomValues: crypto.getRandomValues.bind(crypto) },
    });
    try {
        const { form, wrap, rows, input } = setup();
        wrap.querySelector('[data-block-insert]').click();
        wrap.querySelector('[data-block-duplicate]').click();
        const ids = rows().map((row) => input(row, 'id').value);
        assert.equal(ids.length, 4);
        assert.equal(new Set(ids).size, 4);
        ids.forEach((id) => assert.match(id, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/));
        const padding = input(rows()[3], 'padding');
        padding.value = '24';
        padding.dispatchEvent(new Event('change', { bubbles: true }));
        assert.deepEqual(rows().map((row) => input(row, 'id').value), ids);
        wrap.querySelector('[data-block-undo]').click();
        assert.deepEqual(rows().map((row) => input(row, 'id').value), ids);
        assert.equal(input(rows()[3], 'padding').value, '');
        const submitted = [...new FormData(form)].filter(([name]) => name.endsWith('[id]')).map(([, value]) => value);
        assert.deepEqual(submitted, ids);
    } finally {
        if (descriptor) Object.defineProperty(globalThis, 'crypto', descriptor);
        else delete globalThis.crypto;
    }
});

test('dragging layers preserves object identity and undo restores order', () => {
    const { wrap, rows, input } = setup();
    const layers = wrap.querySelectorAll('[data-layer-id]');
    layers[1].dispatchEvent(new Event('dragstart', { bubbles: true }));
    layers[0].dispatchEvent(new Event('drop', { bubbles: true, cancelable: true }));
    assert.equal(input(rows()[0], 'title').value, 'Second');
    assert.notEqual(input(rows()[0], 'id').value, input(rows()[1], 'id').value);
    wrap.querySelector('[data-block-undo]').click();
    assert.equal(input(rows()[0], 'title').value, 'First');
});

test('reset style changes only the selected object and is reversible', () => {
    const { wrap, rows, input } = setup();
    input(rows()[0], 'background').value = '#123456';
    input(rows()[0], 'background').dispatchEvent(new Event('change', { bubbles: true }));
    wrap.querySelector('[data-block-reset-style]').click();
    assert.equal(input(rows()[0], 'background').value, '');
    assert.equal(input(rows()[1], 'background').value, '');
    wrap.querySelector('[data-block-undo]').click();
    assert.equal(input(rows()[0], 'background').value, '#123456');
    assert.equal(input(rows()[1], 'background').value, '');
});

test('preview selection suppresses navigation only in editing mode', () => {
    const { form, wrap, rows } = setup();
    const canvas = new JSDOM('<div data-studio-block="legacy-1"><a href="/contact">Button</a></div>');
    form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: { contentDocument: canvas.window.document } } }));
    const event = new canvas.window.MouseEvent('click', { bubbles: true, cancelable: true });
    canvas.window.document.querySelector('a').dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(rows()[1].hidden, false);
    assert.equal(wrap.querySelectorAll('[aria-pressed="true"]').length, 1);
    const toggle = document.querySelector('[data-studio-interact]');
    toggle.checked = true;
    toggle.dispatchEvent(new Event('change'));
    assert.equal(canvas.window.document.documentElement.classList.contains('studio-canvas-editing'), false);
});
