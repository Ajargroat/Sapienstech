// Native dialogs keep focus inside the editor without moving forms out of
// the router's page region (and away from inherited tenant theme tokens).
export default function init() {
    const root = document.querySelector('[data-profile-dialogs]');
    if (!root || root.dataset.profileEditBound) return;
    root.dataset.profileEditBound = '1';

    const events = new AbortController();
    const options = { signal: events.signal };
    const resetAvatar = initAvatar(root, options);
    const dialogs = [...root.querySelectorAll('.profile-dialog')];
    let active = null;
    let opener = null;

    const close = () => {
        if (!active) return;
        const dialog = active;
        active = null;
        dialog.close();
        dialog.querySelectorAll('form').forEach((form) => form.reset());
        // Passwords and unsubmitted file selections must not survive dismissal.
        dialog.querySelectorAll('input[type=password], input[type=file]').forEach((field) => { field.value = ''; });
        opener?.focus();
    };

    const open = (dialog, trigger) => {
        if (!dialog || !root.contains(dialog)) return;
        close();
        opener = trigger;
        active = dialog;
        dialog.showModal();
    };

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-profile-open]');
        if (trigger) {
            open(document.getElementById(trigger.dataset.profileOpen), trigger);
        } else if (event.target.closest('[data-profile-close]')) {
            close();
        }
    }, options);

    dialogs.forEach((dialog) => {
        // Only dismiss a gesture that both starts and ends on the backdrop;
        // dragging a text selection outside the panel must not lose the edit.
        let backdropStart = false;
        dialog.addEventListener('pointerdown', (event) => {
            backdropStart = event.target === dialog && outside(dialog, event);
        }, options);
        dialog.addEventListener('click', (event) => {
            if (backdropStart && event.target === dialog && outside(dialog, event)) close();
            backdropStart = false;
        }, options);
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            close();
        }, options);
    });

    root.addEventListener('change', (event) => {
        const file = event.target;
        if (file.matches('input[type=file][name=avatar]') && file.files.length) file.closest('form')?.requestSubmit();
    }, options);

    // Browser validation can target another field after a failed submission.
    root.addEventListener('invalid', (event) => {
        const dialog = event.target.closest('.profile-dialog');
        if (dialog && dialog !== active) open(dialog, root.querySelector(`[data-profile-open="${dialog.id}"]`));
    }, { ...options, capture: true });

    const initial = dialogs.find((dialog) => dialog.hasAttribute('data-profile-auto-open'));
    if (initial) open(initial, root.querySelector(`[data-profile-open="${initial.id}"]`));

    return () => {
        close();
        resetAvatar();
        events.abort();
        delete root.dataset.profileEditBound;
    };
}

export function initAvatar(root, options) {
    const avatar = root.querySelector('[data-profile-avatar]');
    if (!avatar) return () => {};
    const toggle = avatar.querySelector('[data-profile-avatar-toggle]');
    const deletion = avatar.querySelector('[data-profile-avatar-delete]');
    let expanded = false;
    let pinned = false;
    let touchStart = null;
    let swiped = false;

    const setExpanded = (value) => {
        expanded = value;
        avatar.classList.toggle('is-expanded', value);
        toggle.setAttribute('aria-expanded', String(value));
        toggle.setAttribute('aria-label', value ? 'کوچک‌نمایی تصویر پروفایل' : 'بزرگ‌نمایی تصویر پروفایل');
        if (deletion) deletion.hidden = !value;
    };
    const collapse = () => {
        pinned = false;
        setExpanded(false);
    };

    avatar.addEventListener('pointerenter', (event) => {
        if (event.pointerType === 'mouse') setExpanded(true);
    }, options);
    avatar.addEventListener('pointerleave', (event) => {
        if (event.pointerType === 'mouse' && !pinned && !avatar.contains(document.activeElement)) setExpanded(false);
    }, options);
    toggle.addEventListener('click', () => {
        if (swiped) { swiped = false; return; }
        pinned = !pinned;
        setExpanded(pinned);
    }, options);
    avatar.addEventListener('focusout', (event) => {
        if (!avatar.contains(event.relatedTarget)) collapse();
    }, options);
    document.addEventListener('pointerdown', (event) => {
        if (!avatar.contains(event.target)) collapse();
    }, options);
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !expanded) return;
        if (deletion?.contains(document.activeElement)) toggle.focus();
        collapse();
    }, options);

    // Observe native scrolling without trapping the page's touch gestures.
    avatar.addEventListener('touchstart', (event) => {
        swiped = false;
        touchStart = event.touches.length === 1 && event.target.closest('[data-profile-avatar-toggle]')
            ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
    }, { ...options, passive: true });
    avatar.addEventListener('touchmove', (event) => {
        if (!touchStart || event.touches.length !== 1) return;
        const dx = event.touches[0].clientX - touchStart.x;
        const dy = event.touches[0].clientY - touchStart.y;
        if (dy < -30 && Math.abs(dy) > Math.abs(dx)) {
            pinned = true;
            swiped = true;
            setExpanded(true);
            touchStart = null;
        }
    }, { ...options, passive: true });
    avatar.addEventListener('touchcancel', () => { touchStart = null; swiped = false; }, options);
    return collapse;
}

function outside(dialog, event) {
    const rect = dialog.getBoundingClientRect();
    return event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom;
}

if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}
