// Profile "Edit Profile" hub section (both portals), Telegram-style:
// tapping a row expands its inline editor under the label (one row open at
// a time); collapsing a row without saving restores the original value, so
// a closed editor never leaks a half-typed edit into the next "ذخیره".
// The three text rows share one PATCH form (the endpoint expects the full
// field set), so any row's save submits every field — the untouched ones
// still carry their current values. The photo row is different: picking a
// file uploads immediately (same as tapping the camera badge over the
// header avatar, which just labels the same hidden input).

const ROW_SELECTOR = '.profile-edit-row';
const OPEN_CLASS = 'is-editing';

export default function init() {
    const root = document.querySelector('.profile-edit-fields');
    if (!root || root.dataset.profileEditBound) return;
    root.dataset.profileEditBound = '1';

    // Remember each field's value as rendered (also the old() input after a
    // failed save, so cancelling an error-opened row is sane).
    editableFields(root).forEach((field) => {
        field.dataset.original = field.value;
    });

    root.addEventListener('click', (event) => {
        const head = event.target.closest('.profile-edit-row-head');
        if (head) {
            const row = head.closest(ROW_SELECTOR);
            row.classList.contains(OPEN_CLASS) ? closeRow(row) : openRow(root, row);
            return;
        }

        const cancel = event.target.closest('[data-edit-cancel]');
        if (cancel) closeRow(cancel.closest(ROW_SELECTOR));
    });

    root.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        const row = event.target.closest?.(ROW_SELECTOR);
        if (!row?.classList.contains(OPEN_CLASS)) return;

        closeRow(row);
        row.querySelector('.profile-edit-row-head')?.focus();
    });

    // Photo: choose file -> upload right away.
    root.addEventListener('change', (event) => {
        const file = event.target;
        if (!file.matches?.('input[type=file][name=avatar]') || !file.files.length) return;

        file.closest('form')?.requestSubmit();
    });
}

function openRow(root, row) {
    root.querySelectorAll(`${ROW_SELECTOR}.${OPEN_CLASS}`)
        .forEach((open) => { if (open !== row) closeRow(open); });

    row.classList.add(OPEN_CLASS);
    row.querySelector('.profile-edit-row-head')?.setAttribute('aria-expanded', 'true');

    const field = row.querySelector('input:not([type=file]), textarea');
    field?.focus();
    field?.select();
}

function closeRow(row) {
    restoreRow(row);
    row.classList.remove(OPEN_CLASS);
    row.querySelector('.profile-edit-row-head')?.setAttribute('aria-expanded', 'false');
}

function restoreRow(row) {
    row.querySelectorAll('input, textarea').forEach((field) => {
        if (field.type === 'file') {
            field.value = ''; // a never-uploaded pick must not linger
            return;
        }
        if (typeof field.dataset.original === 'string') field.value = field.dataset.original;
    });
}

function editableFields(root) {
    return [...root.querySelectorAll('input, textarea')].filter((field) => field.type !== 'file');
}

if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}
