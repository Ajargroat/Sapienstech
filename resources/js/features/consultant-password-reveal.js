// The new-student password dialog.
//
// A student account is created with a random password that is never stored in
// plain text, so the redirect right after create/approve carries the only copy
// that will ever exist. This bundle opens the dialog that holds it, offers a
// one-click copy (clipboard API, with an execCommand fallback for insecure
// origins where the API is missing), and refuses an accidental Escape/dismiss
// until the password has at least been copied once.
//
// The markup and this @vite tag live inside @section('content') of the pages
// that can flash the password: the router swaps #app-content and reads scripts
// only from the swapped region.

export default function init(region = document) {
    const dialog = (region ?? document).querySelector('#generated-password-dialog');
    if (!dialog) return;

    bind(dialog);

    if (!dialog.open) dialog.showModal();
    dialog.querySelector('[data-generated-password-copy]')?.focus();
}

/** Bound once per dialog element; the router re-runs init() on every render. */
function bind(dialog) {
    if (dialog.dataset.bound) return;
    dialog.dataset.bound = '1';

    // Escape (and the browser's own dismiss) must not silently drop the only
    // copy of a password nobody has read yet.
    dialog.addEventListener('cancel', (event) => event.preventDefault());

    const code = dialog.querySelector('#generated-password-code');
    const copyButton = dialog.querySelector('[data-generated-password-copy]');
    const closeButton = dialog.querySelector('[data-generated-password-close]');

    copyButton?.addEventListener('click', () => copyPassword(dialog, code, copyButton));
    closeButton?.addEventListener('click', () => closeDialog(dialog));

    // Clicking either readonly field selects it, so Ctrl+C works even when
    // the clipboard API is unavailable.
    for (const field of dialog.querySelectorAll('input[readonly]')) {
        field.addEventListener('focus', () => field.select());
    }
}

async function copyPassword(dialog, code, button) {
    if (!code) return;

    let copied = false;
    try {
        await window.navigator.clipboard.writeText(code.value);
        copied = true;
    } catch {
        copied = legacyCopy(code);
    }

    if (copied) {
        dialog.dataset.copied = '1';
        const label = button.querySelector('[data-generated-password-copy-label]');
        if (label) label.textContent = 'کپی شد';
        button.querySelector('i')?.setAttribute('class', 'fas fa-check');
    } else {
        // The copy failed silently: at least leave the value selected so the
        // manual copy is one keystroke away.
        code.select();
    }
}

/** Insecure origins (plain http) have no navigator.clipboard at all. */
function legacyCopy(field) {
    try {
        field.select();
        return document.execCommand('copy');
    } catch {
        return false;
    }
}

function closeDialog(dialog) {
    const confirmed = dialog.dataset.copied === '1'
        || window.confirm('رمز عبور را کپی یا یادداشت کرده‌اید؟ پس از بستن دیگر نمایش داده نمی‌شود.');

    if (confirmed) dialog.close();
}

// The router calls init() on every render of these pages; this fallback only
// boots when the router never made it (so a JS error elsewhere can't leave a
// rendered password invisible inside a closed dialog).
if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
    else init();
}
