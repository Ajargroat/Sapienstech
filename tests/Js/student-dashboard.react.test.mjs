/*
 * Parity pin for the student dashboard React island (PLAN react-01).
 *
 * The dashboard used to build these two lists with appendChild/replaceChildren.
 * resources/js/features/student-dashboard.js now renders them with React, and
 * this suite freezes the DOM contract the old builders produced: the same
 * element tree, the same semantic classes, and the same text/labels — so a
 * later refactor cannot silently change what the student sees.
 *
 * It also pins the "no literal styling" rule: an island emits token-driven
 * classes only. A style attribute or a hex colour in this markup means someone
 * bypassed the tenant token sheet (AGENTS.md §7.2).
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

const dom = new JSDOM('<!doctype html><html dir="rtl"><body></body></html>', {
    url: 'https://tenant.test',
});
const { window } = dom;

// Production ordering: page-router.js defines this before any body bundle
// runs, which is how a feature entry knows not to self-initialize.
window.sapienstechRouter = { shared: true };

const globals = {
    window,
    document: window.document,
    HTMLElement: window.HTMLElement,
    Node: window.Node,
    Element: window.Element,
    Event: window.Event,
    Intl,
};
Object.assign(globalThis, globals);

const { default: init, StudentComments, WeekNotes } = await import(
    '../../resources/js/features/student-dashboard.js'
);
const { mountIsland } = await import('../../resources/js/react/mount.js');

const EMPTY = 'هنوز یادداشتی ثبت نکرده‌اید.';

const render = (Component, props) => renderToStaticMarkup(createElement(Component, props));

const assertTokenClassesOnly = (markup) => {
    assert.doesNotMatch(markup, /\sstyle=/, 'an island must not carry inline styling');
    assert.doesNotMatch(markup, /#[0-9a-f]{3,8}\b/i, 'an island must not carry a literal colour');
};

/* ---------------------------------------------------- router contract --- */

test('the entry keeps the page-router init(region) + cleanup contract', () => {
    assert.equal(typeof init, 'function', 'default export is still the page initializer');

    const cleanup = init(window.document);
    assert.equal(typeof cleanup, 'function', 'init() returns the router teardown');
    cleanup();
});

/* ---------------------------------------------------------- week notes --- */

test('week-note markup is identical to the legacy appendChild builder', () => {
    const html = render(WeekNotes, {
        days: [
            { day_index: 0, label: 'شنبه', text: 'صفحه ۱۰ تا ۲۰', has_text: true },
            { day_index: 2, label: 'دوشنبه', text: 'مرور فصل', has_text: true },
        ],
    });

    assert.equal(
        html,
        '<div class="sched-day">'
        + '<div class="sched-day-head">شنبه</div>'
        + '<p class="student-week-notes-text">صفحه ۱۰ تا ۲۰</p>'
        + '</div>'
        + '<div class="sched-day">'
        + '<div class="sched-day-head">دوشنبه</div>'
        + '<p class="student-week-notes-text">مرور فصل</p>'
        + '</div>',
    );
    assertTokenClassesOnly(html);
});

test('a note is rendered as text, never as markup', () => {
    const container = window.document.createElement('div');
    window.document.body.appendChild(container);

    const island = mountIsland(container, WeekNotes, {
        days: [{ day_index: 1, label: 'یکشنبه', text: '<b>نه</b> HTML', has_text: true }],
    });

    assert.equal(container.querySelector('.sched-day-head').textContent, 'یکشنبه');
    assert.equal(
        container.querySelector('.student-week-notes-text').textContent,
        '<b>نه</b> HTML',
    );
    assert.equal(container.querySelector('.student-week-notes-text b'), null);

    island.unmount();
    container.remove();
});

/* ----------------------------------------------------------- comments --- */

test('the loading state renders nothing and leaves the skeleton alone', () => {
    assert.equal(render(StudentComments, { comments: null }), '');
});

test('the empty thread is the same muted line the old builder appended', () => {
    const html = render(StudentComments, { comments: [] });

    assert.equal(
        html,
        `<p class="comment-item comment-item--empty" data-student-comment-empty="">${EMPTY}</p>`,
    );
    assertTokenClassesOnly(html);
});

test('comment markup keeps the author labels and the student modifier', () => {
    const html = render(StudentComments, {
        comments: [
            { comment_text: 'دقت کن', author: 'consultant' },
            { comment_text: 'چشم', author: 'student' },
        ],
    });

    assert.equal(
        html,
        '<div class="comment-item">'
        + '<p class="comment-item-text">دقت کن</p>'
        + '<span class="comment-item-meta">مشاور</span>'
        + '</div>'
        + '<div class="comment-item comment-item--student">'
        + '<p class="comment-item-text">چشم</p>'
        + '<span class="comment-item-meta">شما</span>'
        + '</div>',
    );
    assertTokenClassesOnly(html);
});

test('the mounted thread transitions loading → empty → list without extra chrome', () => {
    const container = window.document.createElement('div');
    window.document.body.appendChild(container);

    const island = mountIsland(container, StudentComments, { comments: null });
    assert.equal(container.querySelectorAll('.comment-item').length, 0);

    island.update({ comments: [] });
    assert.equal(container.querySelectorAll('.comment-item--empty').length, 1);
    assert.equal(container.querySelector('[data-student-comment-empty]').textContent, EMPTY);

    island.update({
        comments: [
            { comment_text: 'دقت کن', author: 'consultant' },
            { comment_text: 'چشم', author: 'student' },
        ],
    });

    const items = container.querySelectorAll('.comment-item');
    assert.equal(items.length, 2);
    assert.equal(items[0].querySelector('.comment-item-meta').textContent, 'مشاور');
    assert.equal(items[1].classList.contains('comment-item--student'), true);
    assert.equal(items[1].querySelector('.comment-item-meta').textContent, 'شما');
    assert.equal(container.querySelector('[data-student-comment-empty]'), null);
    assertTokenClassesOnly(container.innerHTML);

    island.unmount();
    assert.equal(container.childNodes.length, 0, 'unmount releases the island DOM');
    container.remove();
});
