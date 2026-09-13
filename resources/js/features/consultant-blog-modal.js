// Blog editor dialog — create/edit posts without leaving the list.
//
// The «+» and pencil links fetch the same routes that serve the full page,
// but with Accept: application/json; the controller answers with the bare
// form fragment which we drop into the dialog slot. Submitting is a JSON
// POST as well: a 422 is painted back onto the fields (typed values and an
// already-picked file survive — a page round-trip could never promise that),
// and a successful save closes the dialog and re-renders the list through
// the router, which also surfaces the flashed message.
//
// Progressive enhancement stays intact: the links keep their hrefs and the
// form its native POST, so without JS the full form-page renders exactly
// as before (the router skips these links via data-router="off").

import { enhanceBlogEditors } from './blog-editor';

let booted = false;
let openToken = 0; // last-opened wins when clicks race each other

const dialog = () => document.getElementById('blog-form-modal');

export default function init() {
    if (booted) return;
    booted = true;

    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[data-blog-form]');
        if (!link) return;

        // The router leaves these links alone (data-router="off"), so we
        // also have to stop the browser from following the href.
        event.preventDefault();
        openForm(link);
    });
}

async function openForm(link) {
    const el = dialog();
    if (!el) {
        window.location.assign(link.href);
        return;
    }

    const slot = el.querySelector('[data-blog-form-slot]');
    el.querySelector('[data-blog-modal-title]').textContent =
        link.dataset.formTitle || 'نوشتهٔ جدید';

    const url = new URL(link.href);
    url.searchParams.set('fragment', '1');

    slot.innerHTML = '<p class="blog-form-modal-loading">در حال بارگذاری فرم…</p>';
    el.showModal();
    bindDialog(el);

    const token = ++openToken;
    try {
        const response = await fetch(url.href, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error(String(response.status));

        const html = await response.text();
        if (token !== openToken) return; // a newer open already owns the slot

        slot.innerHTML = html;
        enhanceBlogEditors(slot); // the injected fragment carries the rich editor
        slot.querySelector('input[name="title"]')?.focus();
    } catch (error) {
        if (token !== openToken) return;
        console.error('[blog-modal]', error);
        el.close();
        window.location.assign(link.href); // full page is the fallback
    }
}

/** Backdrop/Esc/cancel and submit wiring: bound once per dialog element. */
function bindDialog(el) {
    if (el.dataset.bound) return;
    el.dataset.bound = '1';

    el.addEventListener('click', (event) => {
        if (event.target === el) {
            el.close(); // click on the backdrop area
            return;
        }
        if (event.target.closest?.('[data-blog-form-close]')) {
            el.close();
            return;
        }
        if (event.target.closest?.('a[data-blog-form-cancel]')) {
            event.preventDefault(); // its href is the no-JS fallback
            el.close();
        }
    });

    el.addEventListener('submit', onSubmit);
    el.addEventListener('input', (event) => clearFieldError(event.target));
}

async function onSubmit(event) {
    const form = event.target;
    if (!form.classList.contains('blog-form')) return;

    event.preventDefault();
    clearErrors(form);

    const buttons = [...form.querySelectorAll('button[type="submit"]')];
    buttons.forEach((button) => { button.disabled = true; });

    try {
        // Always a real POST; @method('PATCH') rides inside the FormData.
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (response.status === 422) {
            const payload = await response.json().catch(() => null);
            showErrors(form, payload?.errors ?? {});
            return;
        }
        if (!response.ok) throw new Error(String(response.status));

        closeAndRefresh();
    } catch (error) {
        console.error('[blog-modal]', error);
        // Network/server failure: leave the dialog world and let the full
        // page (which still works headless) pick the flow back up.
        window.location.assign(dialog()?.dataset.indexUrl ?? form.action);
    } finally {
        buttons.forEach((button) => { button.disabled = false; });
    }
}

function closeAndRefresh() {
    const el = dialog();
    el?.close();

    const indexUrl = el?.dataset.indexUrl || '/consultant/blog';
    if (window.sapienstechRouter?.refresh) {
        // refresh() drops the cached copy and swaps the re-fetched list in,
        // so the flashed «ذخیره شد» strip shows and drag handles rebind.
        window.sapienstechRouter.refresh(indexUrl);
    } else {
        window.location.assign(indexUrl);
    }
}

/* ------------------------------------------------------------- error UI */

function showErrors(form, errors) {
    let firstBad = null;

    for (const [name, messages] of Object.entries(errors)) {
        const field = form.querySelector(`[name="${CSS.escape(name)}"]`);
        if (!field) continue;

        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');

        const box = field.closest('.settings-field') || field.parentElement;
        if (box) {
            const note = document.createElement('span');
            note.className = 'settings-error';
            note.dataset.blogError = name;
            note.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            box.appendChild(note);
        }

        firstBad = firstBad || field;
    }

    if (firstBad) {
        firstBad.focus?.();
        firstBad.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
    }
}

function clearFieldError(target) {
    if (!(target instanceof HTMLElement) || !target.matches('[name]')) return;

    target.classList.remove('is-invalid');
    target.removeAttribute('aria-invalid');
    target.closest('.settings-field')
        ?.querySelectorAll('[data-blog-error]')
        .forEach((note) => note.remove());
}

function clearErrors(form) {
    form.querySelectorAll('.is-invalid').forEach((field) => {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('[data-blog-error]').forEach((note) => note.remove());
}

if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}
