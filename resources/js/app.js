import './theme';
import './landing';
import { onPageRender } from './page-router';

// Closing any open filter surface on an outside click is delegated once for
// the whole shell, so it keeps working across router swaps without stacking
// listeners. Unchecking the toggle inputs collapses every CSS-driven popover
// (the dashboard modal and the simple status menus alike).
document.addEventListener('click', (event) => {
    if (event.target.closest?.('.filter-wrap')) return;
    document.querySelectorAll('.filter-toggle-input:checked').forEach((input) => {
        input.checked = false;
    });
});

/*
 * Topnav notification bell (consultant + student shells): a lightweight
 * dropdown toggled from #topnav-notif-btn, closing on outside click or
 * Escape. Delegated at document level so it survives page-router swaps.
 */
document.addEventListener('click', (event) => {
    const btn = event.target.closest?.('#topnav-notif-btn');
    const panel = document.getElementById('topnav-notif-panel');
    if (!panel) return;

    if (btn) {
        panel.hidden = !panel.hidden;
        return;
    }

    if (!event.target.closest?.('.topnav-notif')) {
        panel.hidden = true;
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const panel = document.getElementById('topnav-notif-panel');
    if (panel) panel.hidden = true;
});

/*
 * Upload tiles (consultant/partials/upload-tile): the native file input is
 * a transparent layer over the dashed box, so echo the picked file's name
 * into the tile's text line and mark the box as filled. Delegated at
 * document level: tiles appear on settings-studio pages and inside the
 * blog dialog's injected fragments alike, none of which re-run page bundles.
 */
document.addEventListener('change', (event) => {
    const input = event.target.closest?.('.settings-upload-input');
    if (!input) return;

    const tile = input.closest('.settings-upload');
    const label = tile?.querySelector('[data-upload-name]');
    if (!label) return;

    const file = input.files?.[0];
    label.textContent = file ? file.name : (label.dataset.default ?? '');
    tile.classList.toggle('has-file', Boolean(file));
});

/*
 * Success flashes are transient notifications, not permanent page
 * furniture: they fade out and remove themselves a few seconds after
 * appearing. Error flashes stay put — role="alert" means the user has to
 * act on them. Armed once at load (covers native-navigation pages like the
 * student portal) and through the router (covers swapped-in panel pages).
 */
const dismissFlash = (flash) => {
    if (flash.dataset.flashArmed) return;
    flash.dataset.flashArmed = '1';
    setTimeout(() => {
        flash.classList.add('is-leaving');
        setTimeout(() => flash.remove(), 500);
    }, 4000);
};

const armFlashDismissal = (root) =>
    root.querySelectorAll('.settings-flash--success').forEach(dismissFlash);

armFlashDismissal(document);
onPageRender(armFlashDismissal);

/*
 * The filter surfaces' forms mirror each other.
 *
 * Every panel form (and the dashboard search box) carries the whole filter
 * stack, so applying on one tab never drops filters set on another and a
 * bulk assignment POST re-derives exactly the set the UI displayed. Modal
 * DOM survives the router's partial swaps, so server-rendered hidden values
 * go stale as soon as anything is clicked; refresh every mirror from the
 * live control state right before submit. Delegated once for the whole
 * shell, like the outside-click dismissal above.
 *
 * Each panel's filter fields are rendered as themed dropdowns whose hidden
 * input (marked with `data-filter-value`) IS the live control; radio chips
 * and native selects (still used on settings pages) work too, so the same
 * sync handles every current and future surface.
 */
const FILTER_FIELDS = [
    'search', 'grade', 'gender', 'major', 'sort',
    'exam_status', 'exam_lesson', 'exam_type',
    'report_source', 'report_status',
    'schedule_day', 'schedule_done',
];

// Multi-value fields: their live controls are checkboxes named "field[]"
// inside the owning panel form; every other filter surface rebuilds its
// hidden "field[]" mirrors from the live state right before submit.
const MULTI_FILTER_FIELDS = [
    'grade', 'gender', 'major', 'exam_status', 'exam_lesson',
    'report_source', 'report_status', 'schedule_day',
];

const isLiveFilterControl = (name) =>
    `[data-filter-value][name="${name}"]` +
    `, input:not([type="hidden"])[name="${name}"]` +
    `, select[name="${name}"]`;

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches?.('form')) return;

    /*
     * Required themed pickers (the bulk-creation test/day dropdowns): the
     * value lives in a hidden input, and hidden inputs are barred from native
     * constraint validation, so the browser's "please select" never fires.
     * Block the submit here — this capture listener runs before the page
     * router's own submit handler — and flag the offending trigger.
     */
    for (const holder of form.querySelectorAll('[data-filter-value][required]')) {
        if (holder.value !== '') continue;
        event.preventDefault();
        const wrap = holder.closest('.filter-select');
        wrap?.classList.add('is-invalid');
        wrap?.querySelector('[data-filter-select-trigger]')?.focus();
        return;
    }

    if (!form.matches('form[data-filter-sync]')) return;

    const scope = form.closest('[data-filter-popover]') ?? document;
    const query = new URLSearchParams(window.location.search);

    form.querySelectorAll('input[type="hidden"]').forEach((input) => {
        if (!FILTER_FIELDS.includes(input.name)) return;
        // The dropdown's own value-holder is a live control, not a mirror.
        if (input.hasAttribute('data-filter-value')) return;
        // Another real control in this form owns the field (dropdown holder,
        // checked radio, or select). Skip mirroring.
        if (form.querySelector(isLiveFilterControl(input.name))) return;

        if (input.name === 'search') {
            const searchInput = document.querySelector('.search-reveal-input');
            input.value = searchInput
                ? searchInput.value.trim()
                : (query.get('search') ?? '');
            return;
        }

        const live = scope.querySelector(isLiveFilterControl(input.name));
        input.value = live ? live.value : (query.get(input.name) ?? '');
    });

    // Multi-value fields: rebuild this form's hidden "field[]" mirrors from
    // the live checkbox state. The owning panel's own boxes serialize
    // natively and are skipped.
    MULTI_FILTER_FIELDS.forEach((field) => {
        const name = `${field}[]`;
        if (form.querySelector(`input[type="checkbox"][name="${CSS.escape(name)}"]`)) return;

        form
            .querySelectorAll(`input[type="hidden"][name="${CSS.escape(name)}"]`)
            .forEach((mirror) => mirror.remove());

        scope
            .querySelectorAll(`input[type="checkbox"][name="${CSS.escape(name)}"]:checked`)
            .forEach((box) => {
                const mirror = document.createElement('input');
                mirror.type = 'hidden';
                mirror.name = name;
                mirror.value = box.value;
                form.appendChild(mirror);
            });
    });
}, true); // capture: must run before the page-router serializes the form

/*
 * Themed dropdowns (.filter-select) — one component, three option kinds:
 *
 *   button[data-value]  the dashboard filter panels' listbox; a JS-set
 *                       hidden input (data-filter-value) is the control.
 *   label > input[type=radio]  studio's select/archetype/list-type groups:
 *                       the radios stay the real form controls (theme-studio
 *                       keeps listening for bubbled change events and reads
 *                       `input:checked`), the dropdown only folds the option
 *                       list into a themed panel.
 *   a[href]             the bulk picker's filter links: navigation proceeds
 *                       natively; we only mirror the label and close.
 *
 * Wrappers marked [data-select-multi] (studio's sections control) keep the
 * panel open while boxes are ticked and show a checked-count instead of a
 * single label.
 *
 * The list is fixed-positioned (via openFilterSelect's inline styles) so it
 * escapes every ancestor's clip — the modal card, the studio rail, the
 * picker; [data-select-auto-width] panels keep their natural content width.
 * Every event that could invalidate the anchor — outside clicks, tab change,
 * viewport resize, Escape — closes it. Delegated once for the whole shell,
 * mirroring the pattern used for the popover itself and the topnav dropdown.
 */
const faDigits = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const closeFilterSelect = (wrap) => {
    if (!wrap.classList.contains('is-open')) return;
    wrap.classList.remove('is-open');
    wrap.querySelector('[data-filter-select-trigger]')?.setAttribute('aria-expanded', 'false');
};

const closeAllFilterSelects = () => {
    document.querySelectorAll('.filter-select.is-open').forEach(closeFilterSelect);
};

const openFilterSelect = (wrap) => {
    const trigger = wrap.querySelector('[data-filter-select-trigger]');
    const list = wrap.querySelector('[data-filter-select-list]');
    if (!trigger || !list) return;

    closeAllFilterSelects();

    // Open first: the list must be laid out for the offsetParent probe below
    // (a display:none element reports no offsetParent at all).
    wrap.classList.add('is-open');
    trigger.setAttribute('aria-expanded', 'true');

    const rect = trigger.getBoundingClientRect();
    const viewportPad = 12;
    const spaceBelow = window.innerHeight - rect.bottom - viewportPad;
    const spaceAbove = rect.top - viewportPad;
    const preferAbove = spaceBelow < 180 && spaceAbove > spaceBelow;

    // Viewport-anchored plan; the probe at the end may rebaseline it.
    let top = preferAbove ? null : rect.bottom + 4;
    let bottom = preferAbove ? window.innerHeight - rect.top + 4 : null;
    let left = Math.max(viewportPad, rect.left);
    let right = null;

    list.style.position = 'fixed';
    list.style.maxHeight = Math.max(140, (preferAbove ? spaceAbove : spaceBelow) - 8) + 'px';

    if (wrap.hasAttribute('data-select-auto-width')) {
        // Wider-than-trigger panels (link menus, section trays) grow towards
        // the inline start, anchored on the trigger's end edge.
        right = Math.max(viewportPad, window.innerWidth - rect.right);
        list.style.width = '';
        list.style.left = '';
        list.style.right = right + 'px';
        list.style.minWidth = rect.width + 'px';
        list.style.maxWidth = Math.min(360, window.innerWidth - 2 * viewportPad) + 'px';
    } else {
        list.style.left = left + 'px';
        list.style.right = '';
        list.style.width = rect.width + 'px';
    }

    list.style.top = top === null ? 'auto' : top + 'px';
    list.style.bottom = bottom === null ? 'auto' : bottom + 'px';

    /*
     * position: fixed normally anchors to the viewport, but any ancestor
     * with a transform/filter/perspective (or a running entrance animation)
     * silently becomes the containing block — then viewport-space offsets
     * land the panel at the far corner of that ancestor. offsetParent is
     * null only while the viewport really is the containing block; when it
     * points at an element, rebaseline every offset into that element's
     * border box (getBoundingClientRect is viewport-relative and unaffected
     * by the ancestor's own transform, so the math stays exact).
     */
    const block = list.offsetParent;
    if (block && block !== document.documentElement && block !== document.body) {
        const box = block.getBoundingClientRect();
        if (top !== null) list.style.top = top - box.top + 'px';
        if (bottom !== null) list.style.bottom = bottom - (window.innerHeight - box.bottom) + 'px';
        if (right !== null) list.style.right = right - (window.innerWidth - box.right) + 'px';
        else list.style.left = left - box.left + 'px';
    }
};

const setSelectDisplay = (wrap, label, isPlaceholder = false) => {
    const display = wrap.querySelector('[data-filter-select-display]');
    const trigger = wrap.querySelector('[data-filter-select-trigger]');
    if (display) display.textContent = label;
    trigger?.classList.toggle('is-placeholder', Boolean(isPlaceholder));
};

const syncSelectDisplayFromChecked = (wrap) => {
    const checked = wrap.querySelector('[data-filter-select-option] input:checked');
    if (!checked) return;
    setSelectDisplay(wrap, checked.closest('[data-filter-select-option]').dataset.label ?? '');
};

const syncSelectCount = (wrap) => {
    const count = wrap.querySelectorAll('input:checked, [data-section-locked]').length;
    const clearRow = wrap.querySelector('[data-filter-clear]');
    if (clearRow) clearRow.classList.toggle('is-active', count === 0);

    // A dashboard multi tray with nothing ticked filters nothing: show the
    // placeholder «همه» instead of a zero count.
    if (count === 0 && wrap.hasAttribute('data-filter-multi')) {
        setSelectDisplay(wrap, wrap.dataset.allLabel ?? 'همه', true);
        return;
    }
    setSelectDisplay(wrap, `${faDigits(count)} ${wrap.dataset.countUnit ?? 'مورد'}`);
};

document.addEventListener('click', (event) => {
    // Option click must run before the trigger-toggle branch, since options
    // live inside the same .filter-select wrapper as the trigger.
    const option = event.target.closest?.('[data-filter-select-option]');
    if (option) {
        const wrap = option.closest('.filter-select');
        if (!wrap) return;

        if (option.tagName === 'A') {
            // Bulk-picker filter link: navigation is the point; mirror the
            // label and fold the menu back while the router swaps results.
            setSelectDisplay(
                wrap,
                option.dataset.label ?? option.textContent ?? '',
                option.hasAttribute('data-placeholder'),
            );
            closeFilterSelect(wrap);
            return;
        }

        if (option.querySelector('input')) {
            // Radio/checkbox option row: the native control updates itself;
            // the bubbled 'change' handler below re-reads the display. A
            // multi tray stays open while several values are picked.
            if (!wrap.hasAttribute('data-select-multi')) {
                setSelectDisplay(wrap, option.dataset.label ?? option.textContent ?? '');
                closeFilterSelect(wrap);
            }
            return;
        }

        // Button option (dashboard listbox): hidden holder is the control.
        const value = option.dataset.value ?? '';

        if (wrap.hasAttribute('data-filter-multi')) {
            // The «همه» row of a multi tray: untick everything.
            wrap.querySelectorAll('input[type="checkbox"]').forEach((box) => {
                box.checked = false;
            });
            syncSelectCount(wrap);
            closeFilterSelect(wrap);
            return;
        }

        const holder = wrap.querySelector('[data-filter-value]');
        if (holder) {
            holder.value = value;
            wrap.classList.remove('is-invalid');
        }
        setSelectDisplay(wrap, option.dataset.label ?? option.textContent ?? '', value === '');

        wrap.querySelectorAll('[data-filter-select-option]').forEach((el) => {
            const active = el === option;
            el.classList.toggle('is-active', active);
            el.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        closeFilterSelect(wrap);
        return;
    }

    const trigger = event.target.closest?.('[data-filter-select-trigger]');
    if (trigger) {
        const wrap = trigger.closest('.filter-select');
        if (!wrap) return;
        if (wrap.classList.contains('is-open')) {
            closeFilterSelect(wrap);
        } else {
            openFilterSelect(wrap);
        }
        return;
    }

    // Clicks anywhere else inside an open panel (rows, move buttons) are the
    // tray's own business; anything outside closes every open dropdown.
    if (event.target.closest?.('.filter-select.is-open')) return;
    closeAllFilterSelects();
});

// Keyboard picks (arrows through radios, Space on checkboxes) never produce
// a click, so the display/count mirrors ride the bubbled change event too.
document.addEventListener('change', (event) => {
    const input = event.target;
    if (!input.matches?.('input[type="radio"], input[type="checkbox"]')) return;
    const wrap = input.closest('.filter-select');
    if (!wrap) return;

    if (wrap.hasAttribute('data-select-multi')) syncSelectCount(wrap);
    else if (input.type === 'radio') syncSelectDisplayFromChecked(wrap);
});

// Any scroll or resize shifts a fixed list away from its (moving) trigger,
// so close — except scrolling inside the tray itself, which must stay open.
document.addEventListener('scroll', (event) => {
    const target = event.target;
    if (target instanceof Element && target.closest('.filter-select.is-open')) return;
    closeAllFilterSelects();
}, true);
window.addEventListener('resize', closeAllFilterSelects);
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const open = document.querySelector('.filter-select.is-open');
    if (open) {
        closeFilterSelect(open);
        return;
    }
    // No dropdown open: Escape closes the filter modal (its checkbox is the
    // single source of truth for the popover state).
    document
        .querySelectorAll('.filter-toggle-input[data-filter-modal-input]:checked')
        .forEach((input) => { input.checked = false; });
});

/*
 * Filter modal: the four filter groups (general / exam / report-card /
 * schedule) live in tabs on the side of the dialog instead of the old
 * hover drawer's prev/next carousel. The popover opens on click only — its
 * checkbox (data-filter-modal-input) drives both the button rotation and
 * the overlay's visibility in pure CSS.
 *
 * Registered through the router so it boots on the first page and survives
 * every partial swap: the modal DOM is never swapped (only the results
 * regions are), hence the dataset guard that keeps listeners attached once.
 */
onPageRender((region) => {
    const modal = region.querySelector('[data-filter-modal]');
    if (!modal || modal.dataset.tabsBooted === '1') return;
    modal.dataset.tabsBooted = '1';

    const tabs = Array.from(modal.querySelectorAll('[data-filter-tab]'));
    const panels = Array.from(modal.querySelectorAll('.filter-page'));
    const applyBtn = modal.querySelector('[data-filter-apply]');
    const toggle = modal
        .closest('.filter-wrap')
        ?.querySelector('.filter-toggle-input[data-filter-modal-input]');
    const pageCount = panels.length;
    let current = -1;

    const activate = (index) => {
        panels.forEach((page, i) => {
            const active = i === index;
            page.classList.toggle('is-active', active);
            page.setAttribute('aria-hidden', String(!active));
            page.querySelectorAll('select, input, button, a').forEach((el) => {
                el.tabIndex = active ? 0 : -1;
            });
        });
    };

    // One shared "اعمال" button drives whichever tab's GET filter form is
    // currently visible (HTML5 form attribute on a button outside the form).
    const setApplyTarget = () => {
        if (!applyBtn) return;
        const form = panels[current]?.querySelector('form[data-filter-form]');
        applyBtn.setAttribute('form', form ? form.id : '');
        applyBtn.hidden = !form;
    };

    const setTab = (index) => {
        const target = Math.max(0, Math.min(index, pageCount - 1));
        if (target === current) return;
        // Fixed-positioned dropdowns must not float over the wrong tab.
        closeAllFilterSelects();
        current = target;
        modal.dataset.filterCurrent = current;

        tabs.forEach((tab, i) => {
            const active = i === current;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        activate(current);
        setApplyTarget();
    };

    tabs.forEach((tab) =>
        tab.addEventListener('click', () => setTab(Number(tab.dataset.filterTab)))
    );

    // Close: the ✕ button, the backdrop (a click that lands on the overlay
    // itself, not on the card inside it), or any link that navigates away.
    modal.querySelectorAll('[data-filter-close]').forEach((btn) =>
        btn.addEventListener('click', () => { if (toggle) toggle.checked = false; })
    );
    modal.addEventListener('click', (event) => {
        if (event.target !== modal || !toggle) return;
        toggle.checked = false;
    });

    setTab(Math.max(0, Math.min(
        Number(modal.dataset.filterCurrent ?? modal.dataset.filterPage ?? 0) || 0,
        pageCount - 1,
    )));
});
