// Appearance studio: color picker <-> hex text sync, per-field reset (posts
// the hidden reset form), section reorder (moves rows so the checkbox array
// submits in the new order, never across a locked row), list repeaters (add /
// delete / reorder item rows; the whole list submits as one nested array and
// the server re-indexes it), toggle label text, range bars that compose
// "<number><unit>" into their text input, a tabbed inspector rail (opening a
// group closes the rest; the active tab persists per browser), ⓘ hint bubbles
// that tap-to-pin on touch devices, and the live preview: a debounced POST to
// the studio's `live` endpoint whose token map is painted into the panel and
// the preview iframe without a reload. Structural changes hot-swap the
// frame: a hidden twin iframe loads the new page while the current one
// stays on screen, then they trade places — no white-flash reload.
// The preview emulates desktop and mobile
// viewports (device buttons), can be maximized to the full window, and while
// it is open on wide screens the page itself becomes a design studio: the
// preview is the main content and the settings dock beside it as a bar.

import initBlockEditor from './studio-block-editor.js';
import initLists from './studio-lists.js';
import initWorkspace from './studio-workspace.js';
import initDraft from './studio-draft.js';
import '../../css/features/studio-workspace.css';

export default function init(options = {}) {
    const form = document.getElementById('studio-form');
    if (!form || form.dataset.studioReady) return;
    form.dataset.studioReady = '1';

    initColors(form);
    initToggles(form);
    initReset(form);
    initSections(form);
    initLists(form, initToggles);
    form.querySelectorAll('[data-block-editor]').forEach((wrap) => initBlockEditor(form, wrap));
    initRanges(form);
    initGroups();
    initHints();
    initWorkspace(form);
    initDraft(form);
    initLive(form);
}

// Color: keep the read-only hex field in sync with the picker.
function initColors(form) {
    form.querySelectorAll('.studio-color').forEach((wrap) => {
        const picker = wrap.querySelector('[data-color-pick]');
        const text = wrap.querySelector('[data-color-text]');
        if (picker && text) {
            picker.addEventListener('input', () => { text.value = picker.value; });
        }
    });
}

// Toggle: update the on/off label.
function initToggles(form) {
    form.querySelectorAll('.studio-toggle input[type="checkbox"]').forEach((cb) => {
        const label = cb.parentElement.querySelector('span');
        cb.addEventListener('change', () => { if (label) label.textContent = cb.checked ? 'On' : 'Off'; });
    });
}

// Per-field reset: fill the hidden reset form and submit it.
function initReset(form) {
    const resetForm = document.getElementById('studio-reset-form');
    form.querySelectorAll('[data-reset-path]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!resetForm) return;
            resetForm.querySelector('[name="path"]').value = btn.dataset.resetPath;
            resetForm.submit();
        });
    });
}

// Sections: reorder rows; the checked boxes submit in DOM order. Locked
// rows (pinned by the schema) have no move buttons and act as barriers —
// an unlocked row can never be dragged across one, so the submitted order
// always matches what StudioSchema::pinSections() would enforce anyway.
function initSections(form) {
    form.querySelectorAll('[data-sections]').forEach((list) => {
        const syncOn = (row) => row.classList.toggle('is-on', row.querySelector('input[type="checkbox"]')?.checked);
        const isLocked = (row) => row?.classList.contains('is-locked');

        list.querySelectorAll('[data-section-row]').forEach((row) => {
            row.querySelector('input[type="checkbox"]')?.addEventListener('change', () => syncOn(row));

            row.querySelector('.sec-move-up')?.addEventListener('click', () => {
                const prev = row.previousElementSibling;
                if (prev && !isLocked(prev)) list.insertBefore(row, prev);
            });
            row.querySelector('.sec-move-down')?.addEventListener('click', () => {
                const next = row.nextElementSibling;
                if (next && !isLocked(next)) list.insertBefore(next, row);
            });
        });
    });
}

// Renders a slider number with exactly the step's decimals, trimmed:
// 0.05*3 must become "0.15", never "0.15000000000000001" or "0.150".
function fmtStep(value, step) {
    const decimals = String(step).includes('.') ? String(step).split('.')[1].length : 0;
    let out = Number(value).toFixed(decimals);
    if (out.includes('.')) out = out.replace(/0+$/, '').replace(/\.$/, '');
    return out === '-0' ? '0' : out;
}

// Range: the bar composes "<number><unit>" into the named text input (the
// source of truth for validation), and typing a value moves the bar back.
function initRanges(form) {
    form.querySelectorAll('[data-studio-range]').forEach((wrap) => {
        const bar = wrap.querySelector('.studio-range-bar');
        const text = wrap.querySelector('.studio-range-text');
        if (!bar || !text) return;

        const step = wrap.dataset.step || '1';
        const unit = wrap.dataset.unit || '';
        const min = parseFloat(bar.min);
        const max = parseFloat(bar.max);

        bar.addEventListener('input', () => {
            text.value = fmtStep(bar.value, step) + unit;
            // Bubble an input event so the live preview (and anything else
            // listening on the form) sees the composed value.
            text.dispatchEvent(new Event('input', { bubbles: true }));
        });

        const syncBar = () => {
            const m = String(text.value).trim().match(/^-?[0-9.]+/);
            if (!m) return;
            const n = parseFloat(m[0]);
            if (Number.isNaN(n)) return;
            bar.value = Math.min(Math.max(n, min), max);
        };
        text.addEventListener('input', syncBar);
        text.addEventListener('change', syncBar);
    });
}

// Design groups: a stack of <details> accordions — exactly one open at a
// time, so the column never turns into one long scroll. The choice survives
// reloads; a failed save arrives with the error group flagged
// data-studio-error, which outranks the remembered one. The groups' own
// summaries are the navigation (the old icon tab strip is gone), and other
// modules jump to a group by dispatching `studio:open-group`.
function initGroups() {
    // Brand's group lives in a <dialog> opened from the canvas dock; it obeys
    // the same data-studio-group name (shortcuts and error jumps still target
    // it by key) but it is not part of the accordion — only the groups inside
    // the Design column fold against each other.
    const all = Array.from(document.querySelectorAll('[data-studio-group]'));
    const modal = all.find((g) => g.hasAttribute('data-brand-modal')) ?? null;
    const groups = all.filter((g) => g !== modal);
    if (!groups.length && !modal) return;

    const main = (groups[0] ?? modal)?.closest('.studio-main');

    // The dialog is static markup inside #studio-form (its fields submit and
    // live-preview like any other group), so opening it is only a visibility
    // change. jsdom has no showModal; the attribute fallback keeps the
    // fixtures working and CSS styles both the same way.
    const openModal = () => {
        // Reached from the dock button, and from `studio:open-group` key
        // "brand" — which is how shortcuts and the failed-save jump ask for
        // any group, the dialog's key included (see the listener below).

        if (!modal) return;
        if (typeof modal.showModal === 'function') modal.showModal();
        else modal.setAttribute('open', '');
    };
    const closeModal = () => {
        if (!modal) return;
        if (typeof modal.close === 'function') modal.close();
        modal.removeAttribute('open');
    };

    document.querySelector('[data-studio-open-brand]')?.addEventListener('click', openModal);
    modal?.querySelectorAll('[data-brand-close]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });
    // Clicking the backdrop (the dialog element itself, outside its content)
    // closes it, like every other dialog in the browser.
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    // Accordion: one group open at a time, opened through its own summary —
    // the old icon tab strip is gone (anything collapsible collapses, and a
    // row of icons spent the space without saying what it was for).
    const activate = (key) => {
        groups.forEach((g) => {
            // The blade used to mark every non-active group `hidden`; that
            // attribute outlived the tab strip and, matched by
            // `.studio-workspace [hidden] { display: none !important }`, it
            // pinned the whole column shut. Membership is `open` now.
            g.hidden = false;
            g.open = g.dataset.studioGroup === key;
        });
        try { localStorage.setItem('studio.tab', key); } catch { /* private mode */ }
    };

    groups.forEach((g) => {
        g.addEventListener('toggle', () => {
            // Closing is always allowed (the group the user just folded stays
            // folded); opening one closes the rest, accordion-style.
            if (!g.open) return;
            activate(g.dataset.studioGroup);
        });

        // Scrolling the column back to its top is a courtesy for a deliberate
        // switch, so it hangs off the summary click — the only way a person
        // opens a group. It cannot hang off `toggle`, because `toggle` also
        // fires when the studio reopens a group by itself (an inspector
        // shortcut, or the re-select after a preview refresh); scrolling there
        // is what threw the panel back to the top on every edit.
        g.querySelector('summary')?.addEventListener('click', () => {
            requestAnimationFrame(() => {
                if (g.open) main?.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    });

    // Programmatic jumps (inspector shortcuts, the block editor) ask for a
    // group by key instead of clicking a tab that no longer exists.
    document.addEventListener('studio:open-group', (event) => {
        const key = event.detail?.key;
        if (key && key === modal?.dataset.studioGroup) openModal();
        else if (key && groups.some((g) => g.dataset.studioGroup === key)) activate(key);
    });

    let saved = null;
    try { saved = localStorage.getItem('studio.tab'); } catch { /* private mode */ }
    const errored = all.find((g) => g.hasAttribute('data-studio-error'));
    if (modal && (errored === modal || (saved && saved === modal.dataset.studioGroup))) {
        // The failed field is in Brand (the dialog is where the message
        // shows), or Brand was the surface the user last had open.
        openModal();
        if (errored !== modal && groups.length) activate(groups[0].dataset.studioGroup);
    } else {
        const initial = errored
            ?? groups.find((g) => g.dataset.studioGroup === saved)
            ?? groups.find((g) => g.open)
            ?? groups[0];
        if (initial) activate(initial.dataset.studioGroup);
    }
}

// ⓘ hint bubbles: CSS shows them on hover/focus; on touch there is no hover,
// so a click pins one open until the user taps elsewhere or presses Escape.
function initHints() {
    const hints = Array.from(document.querySelectorAll('[data-studio-hint]'));
    if (!hints.length) return;

    const closeAll = () => hints.forEach((b) => b.classList.remove('is-open'));

    hints.forEach((btn) => btn.addEventListener('click', (e) => {
        e.preventDefault(); // inside a <summary> it must not fold the group
        e.stopPropagation(); // or the document handler closes it at once
        const wasOpen = btn.classList.contains('is-open');
        closeAll();
        if (!wasOpen) btn.classList.add('is-open');
    }));

    document.addEventListener('click', closeAll);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAll(); });
}

// Paints the derived token map into a document as a trailing <style>.
// Shared by the panel and every preview frame: after a hot-swap the
// incoming document is fresh and must be re-painted.
const paintVars = (doc, vars, schemes) => {
    if (!doc || !doc.head) return;
    let style = doc.getElementById('studio-live-vars');
    if (!style) {
        style = doc.createElement('style');
        style.id = 'studio-live-vars';
        doc.head.appendChild(style);
    }
    const block = (map) => Object.entries(map || {})
        .map(([name, value]) => `--${name}:${value};`).join('');
    let css = `:root{${block(vars)}}`;
    for (const [scheme, schemeVars] of Object.entries(schemes || {})) {
        css += `[data-color-scheme="${scheme}"]{${block(schemeVars)}}`;
    }
    style.textContent = css;
};

// The previewed page scrolls, and in RTL its scrollbar sits on the left
// edge of the device — a stray white bar inside the mock. The frames are
// same-origin, so hide it from every frame document on each load.
//
// Hiding the bar alone breaks scrolling: the frame renders at device
// width and is scaled down with a CSS transform, and wheel input over a
// transform-scaled iframe is unreliable (the event reaches the frame but
// the native scroll often never fires — the bar was the only affordance
// that worked). So we also drive the scroll ourselves: preventDefault
// suppresses the flaky native attempt (no double-scroll where it does
// work) and the matching scroller is moved by hand — an inner scrollable
// region first, then the page itself, like native chaining.
const tameFrameScrolling = (frame) => {
    try {
        const doc = frame.contentDocument;
        const win = frame.contentWindow;
        if (!doc?.head || !win || doc.getElementById('studio-preview-no-scroll')) return;
        const style = doc.createElement('style');
        style.id = 'studio-preview-no-scroll';
        style.textContent = 'html,body{scrollbar-width:none}'
            + 'html::-webkit-scrollbar,body::-webkit-scrollbar{display:none;width:0;height:0}';
        doc.head.appendChild(style);

        doc.addEventListener('wheel', (e) => {
            if (e.ctrlKey || !(e.deltaY || e.deltaX)) return; // leave pinch-zoom alone
            if (!isTameScroll(e.target)) return; // the fallback below still covers the rest
            e.preventDefault();
            const px = (d, mode) => d * (mode === 1 ? 16 : mode === 2 ? win.innerHeight : 1);
            const dy = px(e.deltaY, e.deltaMode);
            const dx = px(e.deltaX, e.deltaMode);
            for (let n = e.target?.nodeType === 1 ? e.target : null; n && n !== doc.documentElement; n = n.parentElement) {
                const maxY = n.scrollHeight - n.clientHeight;
                const maxX = n.scrollWidth - n.clientWidth;
                if (maxY <= 0 && maxX <= 0) continue;
                const beforeY = n.scrollTop;
                const beforeX = n.scrollLeft;
                if (dy && maxY > 0) n.scrollTop = Math.max(0, Math.min(maxY, beforeY + dy));
                if (dx && maxX > 0) n.scrollLeft = Math.max(0, Math.min(maxX, beforeX + dx));
                if (n.scrollTop !== beforeY || n.scrollLeft !== beforeX) return;
            }
            win.scrollBy(dx, dy);
        }, { passive: false });
    } catch { /* cross-origin */ }
};

// A wheel that the preview cannot consume belongs to the studio chrome: the
// design column scrolls behind the same cursor position, so swallowing the
// event would freeze it. The test is geometric, not a node list — an element
// consumes the wheel when it, or any ancestor up to <html>, has overflow to
// travel through. That reads the same in either direction (no LTR/RTL
// assumption) and it goes false on its own when the previewed page is short,
// which is exactly when the studio should take the wheel back.
const isTameScroll = (node) => {
    for (let n = node?.nodeType === 1 ? node : null; n; n = n.parentElement) {
        if (n.scrollHeight - n.clientHeight > 0 || n.scrollWidth - n.clientWidth > 0) return true;
    }
    return false;
};

// Preview chrome: a desktop/mobile device switch plus a maximize button.
// The controls live in the bottom status bar (outside the pane) since the
// restructure, so they are queried at document scope. The iframe always
// renders at the *device's* CSS width (1440 or 390 px) and is scaled down
// to fit the pane — otherwise a narrow pane would silently be a mobile-only
// preview, which is exactly what made the old fixed-width frame useless for
// theme work.
function initPreviewChrome(pane, frame) {
    const stage = pane.querySelector('[data-studio-preview-stage]');
    const deviceBtns = Array.from(document.querySelectorAll('[data-studio-preview-device]'));
    const maxBtn = document.querySelector('[data-studio-preview-max]');
    const split = document.getElementById('studio-split');
    if (!stage || !deviceBtns.length) return;

    const DEVICES = { desktop: { w: 1440, h: 900 }, tablet: { w: 768, h: 1024 }, mobile: { w: 390, h: 844 } };
    const zoom = document.querySelector('[data-studio-zoom]');
    const zoomFit = document.querySelector('[data-studio-zoom-fit]');
    const zoomValue = document.querySelector('[data-studio-zoom-value]');

    let device = 'desktop';
    try {
        const saved = localStorage.getItem('studio.preview.device');
        if (saved in DEVICES) device = saved;
    } catch { /* private mode */ }

    // Zoom: null means the auto "fit" path; dragging the slider takes over
    // with a manual value until the fit button resets. The slider stays
    // enabled in fit mode — otherwise it could never take over — and mirrors
    // the computed fit scale so the thumb sits where the canvas actually is.
    let manualZoom = null;
    const paintZoom = () => {
        if (zoomFit) zoomFit.setAttribute('aria-pressed', String(manualZoom === null));
        if (zoomValue) {
            zoomValue.textContent = manualZoom === null
                ? 'Fit'
                : Math.round(manualZoom * 100) + '%';
        }
    };

    const fit = () => {
        const w = stage.clientWidth;
        const h = stage.clientHeight;
        if (!w || !h) return; // pane hidden — ResizeObserver refires on show

        const dev = DEVICES[device] || DEVICES.desktop;
        // Contain-scale a real device viewport into the stage. A fixed
        // internal size (not stageHeight/scale) keeps svh units and the
        // hero's proportions honest, exactly like browser device mode.
        const requested = manualZoom;
        const scale = requested > 0 ? Math.min(1.25, Math.max(0.25, requested)) : Math.min(1, w / dev.w, h / dev.h);
        // In fit mode the slider mirrors the computed scale, so the thumb
        // shows what the canvas is actually at (and dragging starts from it).
        if (manualZoom === null && zoom) zoom.value = String(scale);
        // Size every frame in the stage, not just the visible one: the
        // hot-swap twin must already be laid out when it trades places
        // (theme-studio.js initLive).
        stage.querySelectorAll('[data-studio-preview-frame]').forEach((f) => {
            f.style.width = dev.w + 'px';
            f.style.height = dev.h + 'px';
            f.style.transform = `scale(${scale})`;
            f.style.left = Math.max(0, (w - dev.w * scale) / 2) + 'px';
            f.style.top = Math.max(0, (h - dev.h * scale) / 2) + 'px';
        });

        // Arm the device-switch morph only after the frame has been sized
        // once, so the initial reveal lands at full size instead of
        // animating out of the iframe's default 300x150 box.
        if (!frame.classList.contains('is-device-anim')) {
            requestAnimationFrame(() => stage.querySelectorAll('[data-studio-preview-frame]')
                .forEach((f) => f.classList.add('is-device-anim')));
        }
    };

    const apply = () => {
        deviceBtns.forEach((btn) => {
            const on = btn.dataset.studioPreviewDevice === device;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        stage.classList.toggle('is-mobile', device === 'mobile');
        fit();
    };

    deviceBtns.forEach((btn) => btn.addEventListener('click', () => {
        device = btn.dataset.studioPreviewDevice in DEVICES ? btn.dataset.studioPreviewDevice : 'desktop';
        try { localStorage.setItem('studio.preview.device', device); } catch { /* private mode */ }
        apply();
    }));

    // Maximize is transient on purpose: reloading the studio should never
    // strand the tenant with the form hidden behind a full-width pane.
    const resetMax = () => {
        if (!split?.classList.contains('is-max')) return;
        split.classList.remove('is-max');
        maxBtn?.classList.remove('is-active');
        if (maxBtn) {
            maxBtn.title = 'Maximize';
            maxBtn.querySelector('i')?.classList.toggle('fa-compress', false);
            maxBtn.querySelector('i')?.classList.toggle('fa-expand', true);
        }
    };

    maxBtn?.addEventListener('click', () => {
        const on = !!split?.classList.toggle('is-max');
        maxBtn.classList.toggle('is-active', on);
        maxBtn.title = on ? 'Exit fullscreen' : 'Maximize';
        maxBtn.querySelector('i')?.classList.toggle('fa-compress', on);
        maxBtn.querySelector('i')?.classList.toggle('fa-expand', !on);
    });

    // `input` (not `change`) so the zoom tracks the drag live.
    zoom?.addEventListener('input', () => {
        const value = Number(zoom.value);
        manualZoom = value > 0 ? value : null;
        paintZoom();
        fit();
    });
    zoomFit?.addEventListener('click', () => {
        manualZoom = null;
        paintZoom();
        fit();
    });
    paintZoom();
    if (window.ResizeObserver) new ResizeObserver(fit).observe(stage);
    window.addEventListener('resize', fit);

    frame.addEventListener('load', () => tameFrameScrolling(frame));

    apply();

    return { fit, resetMax };
}

// Live preview: every edit posts the whole form to the studio's `live`
// endpoint (session-scoped preview, nothing persisted). The response carries
// the fully derived token map, which is painted into the panel and the
// iframe as a trailing <style> — instant, no reload. Fields the schema
// classifies as structural (data-live="reload": variants, copy, nav...)
// change markup, not just variables, so for those the frame is hot-swapped
// with a freshly loaded twin instead of navigating in place.
function initLive(form) {
    const split = document.getElementById('studio-split');
    const pane = document.getElementById('studio-preview-pane');
    let frame = pane?.querySelector('[data-studio-preview-frame]');
    const dot = pane?.querySelector('[data-studio-live-dot]');
    const reloadBtn = document.querySelector('[data-studio-preview-reload]');
    if (!split || !pane || !frame) return;

    const chrome = initPreviewChrome(pane, frame);
    // Bound to the pane rather than to the element: the two documents trade
    // places on every structural edit, so a listener on one frame would go
    // deaf after a single swap. Capture phase, so an in-canvas click that
    // decided to navigate is seen before the frame starts loading.
    let swapping = false;
    pane.addEventListener('click', (event) => {
        if (event.target !== frame || swapping) return;
        // The frame is about to navigate somewhere the studio did not choose.
        // Give it a moment to say where, then re-load that address through the
        // swap so the page pointer, the tree and the selection all follow the
        // user instead of pinning them to the page the studio started on.
        setTimeout(() => {
            try {
                const href = frame.contentWindow.location.href;
                if (href && href !== 'about:blank' && href !== pageUrl && !swapping) {
                    swapFrame({ follow: true, url: href, x: 0, y: 0 });
                }
            } catch { /* cross-origin */ }
        }, 700);
    }, true);
    pane.addEventListener('load', (event) => {
        const el = event.target;
        if (el !== frame || el.hasAttribute('aria-hidden')) return;
        if (el.dataset.loaded === '1') {
            if (swapping) return;
            swapFrame({ follow: true, x: 0, y: 0 });
            return;
        }
        form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame: el } }));
    }, true);

    const liveUrl = form.dataset.liveUrl;
    const homeUrl = form.dataset.homeUrl;
    if (!liveUrl || !homeUrl) return;

    // The canvas can point at several pages of the same site (the page
    // switcher in the status bar). Structural swaps, the reload button and
    // the open-in-new-tab link all follow wherever it currently points;
    // frame-ready then rebuilds the tree against that frame's own ids.
    let pageUrl = homeUrl;

    // dotted path => 'token' | 'reload', straight from the rendered schema.
    const modes = {};
    form.querySelectorAll('[data-studio-field]').forEach((el) => {
        modes[el.dataset.studioField] = el.dataset.live;
    });

    let syncTimer = null;
    let swapTimer = null;
    let inflight = null;

    // Hot-swap state: at most two frame documents ever exist; they trade
    // roles on every structural change. lastTokens remembers the newest
    // derived map so a freshly loaded twin never flashes unstyled vars.
    let twin = null;
    let swapQueued = false;
    let lastTokens = null;
    let previousStructural = false;

    const setDot = (state) => { if (dot) dot.dataset.state = state; };

    const frameDoc = () => {
        try { return frame.contentDocument; } catch { return null; } // cross-origin
    };

    // Swap the preview instead of navigating it. Setting `frame.src` blanks
    // the iframe to white for the whole request — the blink. Here a hidden
    // twin loads the same URL while the current frame stays on screen, and
    // on load the two trade places with the scroll position carried over.
    // It is still a real navigation, so the page's own scripts run fresh.
    // Scroll offsets live per frame, not per studio: the two documents trade
    // places, so the one arriving has to be told where the one leaving was.
    // Kept in a WeakMap because a frame's scroll must survive to the *next*
    // time it comes back — a swap swaps twice, and the second swap would
    // otherwise reset the user to the top of a page they never left.
    const frameScrolls = new WeakMap();

    const currentFrame = () => frame;

    // The user's own wheel/touch wins over the restore: as soon as they move
    // inside the frame, a correction would be them fighting us. Any wheel,
    // key or touch inside the document cancels the pending attempts.
    const cancelOnUserScroll = (el) => {
        const doc = el.contentDocument;
        if (!doc) return;
        const off = () => {
            ['wheel', 'touchstart', 'keydown'].forEach((t) => doc.removeEventListener(t, off, true));
            el.dataset.userScrolled = '1';
        };
        ['wheel', 'touchstart', 'keydown'].forEach((t) => doc.addEventListener(t, off, true));
    };

    // Restore the offset across the handful of frames the incoming document
    // needs to finish its own layout — a single scrollTo at `load` clamps at
    // the height the page had before fonts, images and reveals settled, which
    // is why the canvas appeared to jump near the top on a structural edit.
    const restoreScroll = (el, y, x) => {
        el.dataset.userScrolled = '';
        cancelOnUserScroll(el);
        let tries = 0;
        const one = () => {
            if (el.dataset.userScrolled === '1' || el !== currentFrame()) return;
            try {
                // `behavior: 'instant'` so a tenant page that sets
                // `scroll-behavior: smooth` does not turn the restore into a
                // one-second glide from the top — the offset is a correction,
                // not a scroll the visitor asked for.
                const win = el.contentWindow;
                if (typeof win.scrollTo === 'function') {
                    win.scrollTo({ top: y, left: x, behavior: 'instant' });
                }
                // Settled: either we are where we asked, or the page is simply
                // not that tall yet — keep retrying while it grows.
                if (Math.abs(el.contentWindow.scrollY - y) < 2 || y === 0) return;
            } catch { return; /* cross-origin */ }
            if (++tries > 30) return; // ~0.5s — past this, the page is not growing
            requestAnimationFrame(one);
        };
        requestAnimationFrame(one);
    };

    // Swap the preview instead of navigating it. Setting `frame.src` blanks
    // the iframe to white for the whole request — the blink. Here a hidden
    // twin loads the same URL while the current frame stays on screen, and
    // on load the two trade places: the outgoing offset is carried across and
    // the reveal waits for a painted frame, so a structural edit (an archetype
    // or a variant) reads as the same page redrawing, not as a reload.
    const swapFrame = (options = {}) => {
        if (swapping) { swapQueued = true; return; }
        const current = frame;
        if (!twin) {
            twin = current.cloneNode(false); // attrs/classes only, no document
            twin.removeAttribute('src');
            twin.removeAttribute('data-loaded');
            twin.style.visibility = 'hidden';
            twin.setAttribute('aria-hidden', 'true');
            current.parentElement.appendChild(twin);
        }
        swapping = true;
        const incoming = twin;
        const onLoaded = () => {
            incoming.removeEventListener('load', onLoaded);
            tameFrameScrolling(incoming);
            if (lastTokens) paintVars(incoming.contentDocument, lastTokens.vars, lastTokens.schemes);

            // The offset travels with the frame, not the studio: the twin
            // comes back later as the buffer, so it must be told where it was
            // last time the user looked at it. An explicit x/y (a navigation
            // the user made) wins; otherwise ask the outgoing document where
            // it actually is — the WeakMap only remembers offsets an earlier
            // swap carried, so a page scrolled by hand would otherwise fall
            // back to 0 and snap the canvas to the top on every structural
            // edit. `current` is still the visible frame here, so its offset
            // is live.
            const remembered = frameScrolls.get(current) ?? { x: 0, y: 0 };
            let x = remembered.x;
            let y = remembered.y;
            if (options.x !== undefined || options.y !== undefined) {
                x = options.x ?? 0;
                y = options.y ?? 0;
            } else {
                try {
                    x = current.contentWindow.scrollX;
                    y = current.contentWindow.scrollY;
                } catch { /* cross-origin: keep the remembered offset */ }
            }
            frameScrolls.set(current, { x, y });

            // Standing still: the outgoing frame keeps the screen until the
            // arriving one has painted. A frame that is shown before its first
            // paint is a white flash, which is the blink the swap exists to
            // avoid; one rAF is not enough (the document has just loaded, the
            // browser has not laid it out), so the reveal waits for two.
            const reveal = () => {
                current.style.visibility = 'hidden';
                current.setAttribute('aria-hidden', 'true');
                delete current.dataset.loaded;
                incoming.style.visibility = '';
                incoming.removeAttribute('aria-hidden');
                incoming.dataset.loaded = '1';
                twin = current;   // the outgoing frame becomes the next buffer
                frame = incoming;
                restoreScroll(incoming, y, x);
                form.dispatchEvent(new CustomEvent('studio:frame-ready', { detail: { frame } }));
                swapping = false;
                if (swapQueued) { swapQueued = false; swapFrame(); }
            };
            if (options.instant) reveal();
            else requestAnimationFrame(() => requestAnimationFrame(reveal));
        };
        incoming.addEventListener('load', onLoaded);
        // A navigation the user made inside the canvas (a link, a form-less
        // button that pushes a route) is not a theme change — it is where they
        // are now. Following it keeps the studio's page pointer true, so the
        // next structural edit refreshes the page they are actually looking
        // at instead of yanking them back to the home page.
        let target = options.url;
        if (!target && options.follow) {
            // The outgoing frame already carries the address the user went to.
            try { target = current.contentWindow.location.href; } catch { /* cross-origin */ }
        }
        if (options.follow) {
            incoming.addEventListener('load', () => {
                try {
                    const href = incoming.contentWindow.location.href;
                    if (href && href !== 'about:blank') pageUrl = href;
                } catch { /* cross-origin — keep the pointer where it was */ }
            });
        }
        incoming.src = target || pageUrl;
    };

    const sync = () => {
        setDot('sync');

        const data = new FormData(form);
        data.set('scope', 'preview');

        inflight?.abort();
        inflight = new AbortController();

        fetch(liveUrl, {
            method: 'POST',
            body: data,
            signal: inflight.signal,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then((res) => {
            if (!res.ok) {
                setDot(res.status === 422 ? 'invalid' : 'error');
                if (res.status === 422) {
                    // Validation failure: feed the messages to the status-bar
                    // indicator (studio-workspace.js listens for this).
                    return res.json().catch(() => null).then((body) => {
                        const errors = body?.errors && typeof body.errors === 'object'
                            ? Object.entries(body.errors).map(([path, messages]) => ({
                                path,
                                message: [].concat(messages)[0],
                            })).filter((entry) => entry.message)
                            : [];
                        form.dispatchEvent(new CustomEvent('studio:errors', { detail: { errors } }));
                        return null;
                    });
                }
                return null;
            }
            return res.json();
        }).then((json) => {
            if (!json) return;
            // A successful sync clears any live-validation errors the
            // indicator was showing.
            form.dispatchEvent(new CustomEvent('studio:errors', { detail: { errors: [] } }));
            lastTokens = { vars: json.vars, schemes: json.schemes };
            // The studio chrome is a platform surface: tenant tokens are
            // painted into the preview iframe only, never onto the page
            // itself (the standalone shell must not inherit tenant theming).
            paintVars(frameDoc(), json.vars, json.schemes);
            setDot('ok');

            const structural = (json.changed || []).some((path) => modes[path] !== 'token');
            if (structural || previousStructural) {
                // Shorter than the old reload debounce: the swap is
                // invisible, so the only cost of firing early is one more
                // background page load.
                clearTimeout(swapTimer);
                swapTimer = setTimeout(swapFrame, 500);
            }
            previousStructural = structural;
        }).catch((err) => {
            if (err?.name !== 'AbortError') setDot('error');
        });
    };

    // A load in the visible frame that nobody asked for is an in-frame
    // navigation. The twin is always aria-hidden, so the guard also keeps the
    // swap's own loads out. The click listener runs first and starts a
    // 700ms-attempt swap, which covers the "already navigated by the time the
    // load lands" case; a load with no swap in flight is caught here.
    // Bound to the pane, not to `frame`: the two documents trade places, so a
    // listener on one element would go deaf after a single swap.
    pane.addEventListener('load', (event) => {
        const el = event.target;
        if (el !== frame || el.hasAttribute('aria-hidden')) return;
        if (el.dataset.loaded === '1' && !swapping) {
            swapFrame({ follow: true, x: 0, y: 0 });
        }
    }, true);
    // One debounced sync per burst of edits: a typing run or a slider drag
    // would otherwise POST on every input event.
    const queue = () => {
        clearTimeout(syncTimer);
        syncTimer = setTimeout(sync, 300);
    };
    form.addEventListener('input', queue);
    form.addEventListener('change', queue);

    // The preview is always on: it is the studio's main canvas, not an
    // opt-in panel. Load the frame once and sync immediately on boot.
    if (!frame.dataset.loaded) {
        frame.src = pageUrl;
        frame.dataset.loaded = '1';
    }
    reloadBtn?.addEventListener('click', swapFrame);

    // Page switcher: same swap path as a structural edit (the outgoing frame
    // stays visible while the twin loads the new route), so the tree, the
    // inspector and the selection all re-derive from the page that actually
    // opened. aria-pressed mirrors the current page; the new-tab link points
    // at it too.
    const pageButtons = [...document.querySelectorAll('[data-studio-page]')];
    const openLink = document.querySelector('[data-studio-preview-open]');
    const paintPages = () => {
        pageButtons.forEach((button) => {
            const on = button.dataset.studioPage === pageUrl;
            button.setAttribute('aria-pressed', String(on));
            button.classList.toggle('is-active', on);
        });
        if (openLink) openLink.href = pageUrl;
    };
    pageButtons.forEach((button) => button.addEventListener('click', () => {
        const url = button.dataset.studioPage;
        if (!url || url === pageUrl) return;
        pageUrl = url;
        paintPages();
        swapFrame();
    }));
    paintPages();

    sync();
}
