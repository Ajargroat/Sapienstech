import assert from 'node:assert/strict';
import { test } from 'node:test';

class Element {
    constructor(children = []) {
        this.children = children;
        this.listeners = new Map();
        this.attributes = {};
        this.classes = new Set();
        this.classList = { toggle: (name, value) => value ? this.classes.add(name) : this.classes.delete(name) };
        this.hidden = true;
    }
    addEventListener(type, handler, options = {}) {
        const listeners = this.listeners.get(type) ?? [];
        listeners.push({ handler, signal: options.signal });
        this.listeners.set(type, listeners);
    }
    fire(type, properties = {}) {
        for (const { handler, signal } of this.listeners.get(type) ?? []) {
            if (!signal?.aborted) handler({ target: this, ...properties });
        }
    }
    contains(target) { return target === this || this.children.some((child) => child.contains(target)); }
    setAttribute(name, value) { this.attributes[name] = value; }
    focus() { document.activeElement = this; }
    closest() { return this; }
}

globalThis.window = { sapienstechRouter: {} };
const { initAvatar } = await import('../../resources/js/features/profile-edit.js');

function fixture(hasPhoto = true) {
    const toggle = new Element();
    const deletion = hasPhoto ? new Element() : null;
    const avatar = new Element([toggle, ...(deletion ? [deletion] : [])]);
    avatar.querySelector = (selector) => selector === '[data-profile-avatar-toggle]' ? toggle : deletion;
    const root = { querySelector: () => avatar };
    globalThis.document = new Element([avatar]);
    const events = new AbortController();
    const reset = initAvatar(root, { signal: events.signal });
    const cleanup = () => { reset(); events.abort(); };
    return { avatar, toggle, deletion, cleanup };
}

test('mouse hover reveals deletion and leaving collapses the photo', () => {
    const { avatar, toggle, deletion, cleanup } = fixture();
    avatar.fire('pointerenter', { pointerType: 'mouse' });
    assert.equal(toggle.attributes['aria-expanded'], 'true');
    assert.equal(deletion.hidden, false);
    avatar.fire('pointerleave', { pointerType: 'mouse' });
    assert.equal(toggle.attributes['aria-expanded'], 'false');
    assert.equal(deletion.hidden, true);
    cleanup();
});

test('touch tap toggles, outside tap dismisses, and Escape returns focus from deletion', () => {
    const { avatar, toggle, deletion, cleanup } = fixture();
    avatar.fire('pointerenter', { pointerType: 'touch' });
    assert.equal(deletion.hidden, true);
    toggle.fire('click');
    assert.equal(deletion.hidden, false);
    toggle.fire('click');
    assert.equal(deletion.hidden, true);
    toggle.fire('click');
    deletion.focus();
    document.fire('keydown', { key: 'Escape' });
    assert.equal(document.activeElement, toggle);
    assert.equal(deletion.hidden, true);
    toggle.fire('click');
    document.fire('pointerdown');
    assert.equal(deletion.hidden, true);
    cleanup();
});

test('upward swipe expands without a synthetic click closing it; horizontal movement does not', () => {
    const { avatar, toggle, deletion, cleanup } = fixture();
    const touch = (type, x, y) => avatar.fire(type, { target: toggle, touches: [{ clientX: x, clientY: y }] });
    touch('touchstart', 100, 100);
    touch('touchmove', 160, 90);
    assert.equal(deletion.hidden, true);
    touch('touchstart', 100, 100);
    touch('touchmove', 102, 50);
    assert.equal(deletion.hidden, false);
    toggle.fire('click');
    assert.equal(deletion.hidden, false);
    touch('touchstart', 100, 100);
    toggle.fire('click');
    assert.equal(deletion.hidden, true);
    cleanup();
});

test('focus stays usable inside expanded photo, cleanup resets and removes handlers', () => {
    const { avatar, toggle, deletion, cleanup } = fixture();
    toggle.fire('click');
    avatar.fire('focusout', { relatedTarget: deletion });
    assert.equal(deletion.hidden, false);
    avatar.fire('focusout', { relatedTarget: null });
    assert.equal(deletion.hidden, true);
    toggle.fire('click');
    cleanup();
    assert.equal(deletion.hidden, true);
    toggle.fire('click');
    assert.equal(deletion.hidden, true);
});

test('initial-only avatars work without a delete form', () => {
    const { toggle, cleanup } = fixture(false);
    toggle.fire('click');
    assert.equal(toggle.attributes['aria-expanded'], 'true');
    cleanup();
    assert.equal(toggle.attributes['aria-expanded'], 'false');
});
