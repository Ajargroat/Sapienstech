// Student dashboard: render Jalali dates and Persian weekday names over the
// server's Gregorian fallback text, and wire the read-only schedule-details
// dialog. Same pinned-UTC Intl approach as consultant-exams.js — stored
// datetimes are wall-clock, so formatting them as UTC can never shift the day.
//
// The two lists the old module built by hand (the week-notes days and the
// comment thread) are now React islands mounted by resources/js/react/mount.js.
// The page router contract is unchanged: `export default function init(region)`
// and an optional cleanup returned to page-router.js bindPage/stopPage.
// Server-rendered DOM stays the owner of the skeleton; React only fills the
// containers the Blade view already ships.

import { createElement } from 'react';
import { mountIsland } from '../react/mount.js';
import { EmptyState, Text } from '../react/primitives.js';

const EMPTY_COMMENT = 'هنوز یادداشتی ثبت نکرده‌اید.';

// One comment-thread island per dialog; reopening clones a fresh template body,
// so the previous root has to be released before the next one mounts.
const commentIslands = new WeakMap();

export default function init(region = document) {
    const displayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'UTC', year: 'numeric', month: '2-digit', day: '2-digit',
    });
    const weekdayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'UTC', weekday: 'long',
    });

    (region || document).querySelectorAll('time.fa-date[datetime]').forEach((el) => {
        const date = new Date(el.getAttribute('datetime'));
        if (!Number.isNaN(date.getTime())) el.textContent = displayFmt.format(date);
    });

    (region || document).querySelectorAll('[data-fa-weekday]').forEach((el) => {
        const date = new Date(el.getAttribute('data-fa-weekday'));
        if (!Number.isNaN(date.getTime())) el.textContent = weekdayFmt.format(date);
    });

    const islands = [];

    initScheduleDialog(region, islands);
    initWeekNotes(islands);

    const stopReveal = revealStartButtons(region);

    return () => {
        stopReveal();
        islands.splice(0).forEach((island) => island.unmount());
    };
}

/**
 * Cards ship their «شروع آزمون» anchor from the server, hidden until the
 * window opens; this reveals it at exactly the start instant — no polling,
 * no reload — and flips the badge with it so the card keeps reading
 * consistently. The datetime is parsed in the same pinned-UTC frame the
 * fa-date rewrites above use, and the timers are released by the teardown
 * this returns (page-router.js bindPage/stopPage) so a re-render cannot
 * leave a stale flip behind.
 */
function revealStartButtons(region) {
    const pending = [];
    const MAX_DELAY = 2147483647; // setTimeout overflows past ~24.8 days

    (region || document).querySelectorAll('.exam-run-btn[data-start-at]').forEach((btn) => {
        const start = Date.parse(btn.dataset.startAt);
        if (Number.isNaN(start)) return;

        const card = btn.closest('[data-exam-card]');

        const reveal = () => {
            btn.hidden = false;
            const badge = card?.querySelector('[data-exam-status]');
            if (badge?.classList.contains('exam-status--scheduled')) {
                badge.classList.replace('exam-status--scheduled', 'exam-status--in_progress');
                badge.textContent = card?.dataset.statusOpen || badge.textContent;
            }
        };

        // Re-armed instead of one long timeout: a window can start weeks out,
        // past the 32-bit delay limit, where a single setTimeout fires at once.
        const arm = () => {
            const wait = start - Date.now();
            if (wait <= 0) {
                reveal();
                return;
            }
            const t = setTimeout(arm, Math.min(wait, MAX_DELAY));
            pending.push(() => clearTimeout(t));
        };

        arm();
    });

    return () => pending.forEach((off) => off());
}

// Boxes are <button data-sched-item="#template-id">; details live in hidden
// server-rendered <template>s and are cloned into one shared <dialog>.
function initScheduleDialog(region = document, islands = []) {
    const dialog = document.getElementById('schedule-detail-modal');
    if (!dialog || dialog.dataset.wired) return;
    dialog.dataset.wired = '1';

    const title = dialog.querySelector('#sched-modal-title span');
    const body = dialog.querySelector('.sched-modal-body');

    (region || document).querySelectorAll('[data-sched-item]').forEach((box) => {
        box.addEventListener('click', () => {
            const tpl = document.getElementById(box.dataset.schedItem);
            if (!tpl) return;

            // The cloned body replaces the previous thread's raw nodes; release
            // its React root first so the old tree cannot keep updating.
            commentIslands.get(dialog)?.unmount();
            commentIslands.delete(dialog);

            body.replaceChildren(tpl.content.cloneNode(true));
            if (title) title.textContent = box.dataset.schedTitle || '';
            init(dialog); // rewrite the freshly cloned Jalali date placeholders
            wireStudentActions(dialog, box, islands);
            dialog.showModal();
        });
    });

    dialog.querySelector('[data-sched-close]')?.addEventListener('click', () => dialog.close());

    // Click on the backdrop (the dialog element itself, outside the box).
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) dialog.close();
    });
}

// ---------------------------------------------------------------------------
// Read-only «توضیحات هفته»: the consultant writes the week's notes on their
// side, the student reads them here. The section ships hidden and is revealed
// only once the week's payload has actually rendered — a failed fetch
// degrades to the status quo (no panel), never to an empty-looking one.
// ---------------------------------------------------------------------------

function initWeekNotes(islands = []) {
    const section = document.querySelector('[data-react-island="student-week-notes"], #student-week-notes');
    if (!section || section.dataset.wired) return;
    section.dataset.wired = '1';

    const url = section.dataset.urlNotes;
    if (!url) return;

    const empty = section.querySelector('#student-week-notes-empty');
    const weekStart = section.dataset.weekStart || '';
    const query = weekStart ? `?week_start_date=${encodeURIComponent(weekStart)}` : '';

    fetch(`${url}${query}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    })
        .then((res) => (res.ok ? res.json() : Promise.reject(new Error(String(res.status)))))
        .then((data) => {
            // A router swap may have removed the section while the request was
            // in flight; never mount an island on detached DOM.
            if (!section.isConnected) return;

            const days = (data?.days || []).filter((day) => day.has_text);

            if (!days.length) {
                if (empty) empty.hidden = false;
            } else {
                const list = section.querySelector('#student-week-notes-list');
                const island = mountIsland(list, WeekNotes, { days });
                if (island) islands.push(island);
            }

            section.hidden = false;
        })
        .catch(() => {
            // Passive read: there is nothing the student could act on, and an
            // error strip would be noise on an otherwise healthy page.
        });
}

/**
 * One week's note days, in payload order. The markup is identical to the DOM
 * the old appendChild builder produced: day wrapper, muted head, note text.
 * A note is data, so React's text rendering keeps markup inside it as text.
 */
export function WeekNotes({ days }) {
    return days.map((day) => createElement(
        'div',
        { className: 'sched-day', key: String(day.day_index ?? day.label ?? '') },
        createElement(Text, { as: 'div', className: 'sched-day-head' }, day.label || ''),
        createElement(Text, { className: 'student-week-notes-text' }, day.text),
    ));
}

// ---------------------------------------------------------------------------
// Writable pop-up: mark-done + the student's own comment thread
// ---------------------------------------------------------------------------
// The dialog is no longer read-only. The student marks a block done and writes
// down WHY it was or was not done, and the consultant reads that back on their
// side. Both calls go to the URLs declared on the dialog as
// data-url-*-template, so no route is hardcoded here and a tenant prefix is
// never guessed.

/**
 * Binds the writable half of the pop-up against the freshly cloned body. `box`
 * is the calendar card the dialog was opened from: it carries the item id and
 * it is what the calendar itself must repaint, so the dialog and the grid can
 * never disagree about whether a block is done.
 */
function wireStudentActions(dialog, box, islands = []) {
    const itemId = box.dataset.schedId;
    if (!itemId) return;

    const urlFor = (template) => (template || '').replace('__ITEM__', itemId);

    wireCompletion(dialog, box, urlFor);
    wireComments(dialog, urlFor, islands);
}

/**
 * The token the portal's writes need. It lives on the <dialog> the server
 * rendered — the student layouts carry no csrf <meta>, and reading one that is
 * not there sent an empty header, which Laravel answers with 419. A missing
 * token is a broken page, not a silent empty string.
 */
function csrfToken(root) {
    return root?.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function wireCompletion(dialog, box, urlFor) {
    const toggle = dialog.querySelector('[data-student-done-toggle]');
    if (!toggle) return;

    const status = dialog.querySelector('[data-student-done-status]');

    const setStatus = (message, isError = false) => {
        if (!status) return;
        status.textContent = message || '';
        status.hidden = !message;
        status.classList.toggle('sched-done-status--error', Boolean(message) && isError);
    };

    toggle.addEventListener('click', async () => {
        if (toggle.disabled) return;

        const wasDone = toggle.classList.contains('is-done');
        const next = !wasDone;

        toggle.disabled = true;
        setStatus('');

        try {
            const res = await fetch(urlFor(dialog.dataset.urlCompletionTemplate), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(dialog),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ is_completed: next }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.error || String(res.status));

            paintCompletion(toggle, box, Boolean(data.is_completed));
        } catch {
            // A failed toggle must not lie: put the control back where it was,
            // and say so — a silent revert reads as "the button does nothing".
            paintCompletion(toggle, box, wasDone);
            setStatus('ثبت نشد؛ دوباره تلاش کنید.', true);
        } finally {
            toggle.disabled = false;
        }
    });
}

/**
 * Paints one truth (is_completed) in the two places that show it: the dialog
 * control and the calendar card. Both use the same class and icon the server
 * renders, so a later page load cannot disagree with this.
 */
function paintCompletion(toggle, box, isCompleted) {
    toggle.classList.toggle('is-done', isCompleted);
    toggle.setAttribute('aria-pressed', isCompleted ? 'true' : 'false');
    toggle.querySelector('i')?.setAttribute('class', isCompleted ? 'fas fa-check-circle' : 'fas fa-circle');

    const label = toggle.querySelector('.sched-done-label');
    if (label) label.textContent = isCompleted ? 'تکمیل‌شده' : 'انجام‌نشده';

    if (!box) return;
    box.dataset.schedCompleted = isCompleted ? '1' : '0';
    box.classList.toggle('sched-box--done', isCompleted);

    const time = box.querySelector('.sched-box-time');
    const existing = time?.querySelector('i.fa-check-circle');
    if (isCompleted && time && !existing) {
        const icon = document.createElement('i');
        icon.className = 'fas fa-check-circle';
        icon.title = 'تکمیل شد';
        time.appendChild(icon);
    } else if (!isCompleted && existing) {
        existing.remove();
    }
}

function wireComments(dialog, urlFor, islands = []) {
    const composer = dialog.querySelector('[data-student-comment-composer]');
    if (!composer) return;

    const text = composer.querySelector('[data-student-comment-text]');
    const submit = composer.querySelector('[data-student-comment-submit]');
    const status = composer.querySelector('[data-student-comment-status]');
    const thread = dialog.querySelector('[data-student-comment-thread]');
    const url = urlFor(dialog.dataset.urlCommentsTemplate);

    const setStatus = (message, isError = false) => {
        if (!status) return;
        status.textContent = message || '';
        status.hidden = !message;
        status.classList.toggle('comment-composer-status--error', Boolean(message) && isError);
    };

    // The thread is a React island: load the server's comments, then post the
    // freshly written one locally instead of re-fetching. `null` means loading
    // — the server-rendered placeholder stays until the first payload lands,
    // exactly as the old fetch-then-replace builder behaved.
    let comments = null;
    let island = null;
    let locallyPosted = false;

    if (thread && url) {
        island = mountIsland(thread, StudentComments, { comments });
        if (island) {
            islands.push(island);
            commentIslands.set(dialog, island);
        }
    }

    const setComments = (next) => {
        comments = next;
        island?.update({ comments });
    };

    loadComments(url).then((list) => {
        // Reopening the dialog re-clones the body; the old thread is detached
        // and must not publish its payload into the new one. A locally posted
        // comment is newer truth, so a late initial load cannot erase it.
        if (!thread?.isConnected || locallyPosted) return;
        setComments(list);
    });

    const send = async () => {
        const value = (text?.value || '').trim();
        // An empty comment is not a comment; the server agrees, so do not ask it.
        if (!value) {
            setStatus('متن یادداشت خالی است.', true);
            return;
        }

        submit.disabled = true;
        setStatus('در حال ثبت...');
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(dialog),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ comment_text: value }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.error || 'failed');

            if (text) text.value = '';
            setStatus('ثبت شد.');
            locallyPosted = true;
            setComments([...(comments || []), { comment_text: value, author: 'student' }]);
        } catch {
            // Keep the text on screen so nothing the student typed is lost.
            setStatus('ثبت نشد؛ دوباره تلاش کنید.', true);
        } finally {
            submit.disabled = false;
        }
    };

    submit?.addEventListener('click', send);
    // Enter submits; Shift+Enter stays a newline (the textarea's own behaviour).
    text?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });
}

async function loadComments(url) {
    if (!url) return [];

    try {
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => null);
        return Array.isArray(data) ? data : (data?.comments || []);
    } catch {
        return [];
    }
}

/**
 * The dialog's comment thread. `comments === null` is the loading state — the
 * server-rendered placeholder is left in place; an empty array renders the same
 * muted line the old builder appended; any other array renders the thread.
 */
export function StudentComments({ comments }) {
    if (!comments) return null;

    if (!comments.length) {
        return createElement(
            EmptyState,
            {
                className: 'comment-item comment-item--empty',
                'data-student-comment-empty': '',
            },
            EMPTY_COMMENT,
        );
    }

    return comments.map((comment, index) => createElement(
        'div',
        {
            key: `${comment.author || 'comment'}-${index}`,
            className: `comment-item${comment.author === 'student' ? ' comment-item--student' : ''}`,
        },
        createElement(Text, { className: 'comment-item-text' }, comment.comment_text),
        createElement(
            Text,
            { as: 'span', className: 'comment-item-meta' },
            comment.author === 'student' ? 'شما' : 'مشاور',
        ),
    ));
}

// The router calls init() on every render of this page; this fallback only
// boots the page when app.js never made it (so a JS error can't leave
// Gregorian dates behind).
if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
    else init();
}
