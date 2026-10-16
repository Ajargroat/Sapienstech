// Student dashboard: render Jalali dates and Persian weekday names over the
// server's Gregorian fallback text, and wire the read-only schedule-details
// dialog. Same pinned-UTC Intl approach as consultant-exams.js — stored
// datetimes are wall-clock, so formatting them as UTC can never shift the day.

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

    initScheduleDialog(region);
    initWeekNotes(region);

    return revealStartButtons(region);
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
function initScheduleDialog(region = document) {
    const dialog = document.getElementById('schedule-detail-modal');
    if (!dialog || dialog.dataset.wired) return;
    dialog.dataset.wired = '1';

    const title = dialog.querySelector('#sched-modal-title span');
    const body = dialog.querySelector('.sched-modal-body');

    (region || document).querySelectorAll('[data-sched-item]').forEach((box) => {
        box.addEventListener('click', () => {
            const tpl = document.getElementById(box.dataset.schedItem);
            if (!tpl) return;

            body.replaceChildren(tpl.content.cloneNode(true));
            if (title) title.textContent = box.dataset.schedTitle || '';
            init(dialog); // rewrite the freshly cloned Jalali date placeholders
            wireStudentActions(dialog, box);
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

function initWeekNotes() {
    const section = document.getElementById('student-week-notes');
    if (!section || section.dataset.wired) return;
    section.dataset.wired = '1';

    const url = section.dataset.urlNotes;
    if (!url) return;

    const list = section.querySelector('#student-week-notes-list');
    const empty = section.querySelector('#student-week-notes-empty');
    const weekStart = section.dataset.weekStart || '';
    const query = weekStart ? `?week_start_date=${encodeURIComponent(weekStart)}` : '';

    fetch(`${url}${query}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    })
        .then((res) => (res.ok ? res.json() : Promise.reject(new Error(String(res.status)))))
        .then((data) => {
            const days = (data?.days || []).filter((day) => day.has_text);

            if (!days.length) {
                if (empty) empty.hidden = false;
            } else {
                days.forEach((day) => list?.appendChild(buildWeekNote(day)));
            }

            section.hidden = false;
        })
        .catch(() => {
            // Passive read: there is nothing the student could act on, and an
            // error strip would be noise on an otherwise healthy page.
        });
}

function buildWeekNote(day) {
    const wrap = document.createElement('div');
    wrap.className = 'sched-day';

    const head = document.createElement('div');
    head.className = 'sched-day-head';
    head.textContent = day.label || '';
    wrap.appendChild(head);

    const text = document.createElement('p');
    text.className = 'student-week-notes-text';
    text.textContent = day.text; // textContent, never innerHTML: a note is data
    wrap.appendChild(text);

    return wrap;
}


// ---------------------------------------------------------------------------
// Writable pop-up: mark-done + the student's own comment thread
// ---------------------------------------------------------------------------
// The dialog is no longer read-only. The student marks a block done and writes
// down WHY it was or was not done ("مریض بودم"), and the consultant reads that
// back on their side. Both calls go to the URLs declared on the dialog as
// data-url-*-template, so no route is hardcoded here and a tenant prefix is
// never guessed.

/**
 * Binds the writable half of the pop-up against the freshly cloned body. `box`
 * is the calendar card the dialog was opened from: it carries the item id and
 * it is what the calendar itself must repaint, so the dialog and the grid can
 * never disagree about whether a block is done.
 */
function wireStudentActions(dialog, box) {
    const itemId = box.dataset.schedId;
    if (!itemId) return;

    const urlFor = (template) => (template || '').replace('__ITEM__', itemId);

    wireCompletion(dialog, box, urlFor);
    wireComments(dialog, urlFor);
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

function wireComments(dialog, urlFor) {
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

    loadComments(thread, url);

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
            appendComment(thread, value, true);
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

async function loadComments(thread, url) {
    if (!thread || !url) return;

    try {
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => null);
        const comments = Array.isArray(data) ? data : (data?.comments || []);

        thread.replaceChildren();
        if (!comments.length) {
            thread.appendChild(emptyComment());
            return;
        }
        comments.forEach((c) => {
            thread.appendChild(buildComment(c.comment_text, c.author === 'student'));
        });
    } catch {
        thread.replaceChildren();
        thread.appendChild(emptyComment());
    }
}

const emptyComment = () => {
    const p = document.createElement('p');
    p.className = 'comment-item comment-item--empty';
    p.dataset.studentCommentEmpty = '';
    p.textContent = 'هنوز یادداشتی ثبت نکرده‌اید.';
    return p;
};

function buildComment(text, isStudent) {
    const wrap = document.createElement('div');
    wrap.className = 'comment-item' + (isStudent ? ' comment-item--student' : '');

    const body = document.createElement('p');
    body.className = 'comment-item-text';
    body.textContent = text; // textContent, never innerHTML: a comment is data
    wrap.appendChild(body);

    const meta = document.createElement('span');
    meta.className = 'comment-item-meta';
    meta.textContent = isStudent ? 'شما' : 'مشاور';
    wrap.appendChild(meta);

    return wrap;
}

// A freshly posted comment is appended locally rather than re-fetching the
// thread: the student just wrote it, so it is already the newest truth.
function appendComment(thread, text) {
    if (!thread) return;
    thread.querySelector('[data-student-comment-empty]')?.remove();
    thread.appendChild(buildComment(text, true));
}

// The router calls init() on every render of this page; this fallback only
// boots the page when app.js never made it (so a JS error can't leave
// Gregorian dates behind).
if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
    else init();
}
