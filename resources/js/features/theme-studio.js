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

export default function init() {
    const form = document.getElementById('studio-form');
    if (!form) return;

    initColors(form);
    initToggles(form);
    initReset(form);
    initSections(form);
    initLists(form);
    initRanges(form);
    initGroups();
    initHints();
    initLive(form);
}

// Color: keep the read-only hex field in sync with the picker.
function initColors(form) {
    form.querySelectorAll('.studio-color').forEach((wrap) => {
        const picker = wrap.querySelector('[data-color-pick]');
        const text = wrap.querySelector('[data-color-text]');
        if (picker && text) {
            text.value = picker.value;
            picker.addEventListener('input', () => { text.value = picker.value; });
        }
    });
}

// Toggle: update the on/off label.
function initToggles(form) {
    form.querySelectorAll('.studio-toggle input[type="checkbox"]').forEach((cb) => {
        const label = cb.parentElement.querySelector('span');
        cb.addEventListener('change', () => { if (label) label.textContent = cb.checked ? 'روشن' : 'خاموش'; });
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

// Lists: repeater rows for item-valued content (cards, buttons, links).
// "Add" clones the <template> row, swapping its __KEY__ placeholders for a
// unique browser-side key so a fresh row can never collide with a stored
// index; delete/move just edit the DOM, and the submitted nested array's key
// order carries the result (StudioSchema::normalizeList re-indexes it). An
// abandoned empty row is dropped server-side before validation, and clearing
// every row forgets the list override so the file-owned items show through.
function initLists(form) {
    form.querySelectorAll('[data-studio-list]').forEach((wrap) => {
        const rows = wrap.querySelector('[data-list-rows]');
        const tpl = wrap.querySelector('[data-list-template]');
        const addBtn = wrap.querySelector('[data-list-add]');
        if (!rows || !tpl) return;

        const max = parseInt(wrap.dataset.max || '20', 10);
        let seq = 0;

        const rowList = () => Array.from(rows.querySelectorAll('[data-list-row]'));

        const refresh = (notify) => {
            rowList().forEach((row, i) => {
                const num = row.querySelector('[data-list-num]');
                if (num) num.textContent = i + 1;
            });
            if (addBtn) addBtn.hidden = rowList().length >= max;
            // Structural change: bubble an input event so the live preview
            // (which listens on the form) re-renders the site.
            if (notify) form.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const wireRow = (row) => {
            // Typed rows: only show the cells the selected block type uses.
            // Hidden inputs still submit; the server drops values the row's
            // type does not own, so switching back restores what was typed.
            const typeGroup = row.querySelector('[data-list-type]');
            if (typeGroup) {
                const applyType = () => {
                    const checked = typeGroup.querySelector('input:checked');
                    const type = checked ? checked.value : '';
                    row.querySelectorAll('[data-show-for]').forEach((cell) => {
                        cell.hidden = !cell.dataset.showFor.split(' ').includes(type);
                    });
                };
                typeGroup.addEventListener('change', applyType);
                applyType();
            }

            row.querySelector('.list-move-up')?.addEventListener('click', () => {
                const prev = row.previousElementSibling;
                if (prev) rows.insertBefore(row, prev);
                refresh(true);
            });
            row.querySelector('.list-move-down')?.addEventListener('click', () => {
                const next = row.nextElementSibling;
                if (next) rows.insertBefore(next, row);
                refresh(true);
            });
            row.querySelector('[data-list-del]')?.addEventListener('click', () => {
                row.remove();
                refresh(true);
            });
        };

        rowList().forEach(wireRow);
        refresh(false);

        addBtn?.addEventListener('click', () => {
            const key = 'n' + (++seq) + '-' + Math.random().toString(36).slice(2, 8);
            const node = tpl.content.firstElementChild?.cloneNode(true);
            if (!node) return;

            node.querySelectorAll('[name],[id],[for]').forEach((el) => {
                ['name', 'id', 'for'].forEach((attr) => {
                    const v = el.getAttribute(attr);
                    if (v && v.includes('__KEY__')) el.setAttribute(attr, v.replaceAll('__KEY__', key));
                });
            });

            rows.appendChild(node);
            wireRow(node);
            // Label sync for the new row's toggles (re-binding old ones is a
            // no-op: the handler only rewrites the label text).
            initToggles(form);
            refresh(true);
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

// Inspector rail: the tab buttons switch panels — exactly one group is
// visible at a time (the rest carry [hidden]), so the bar never turns into
// one long accordion scroll. The active tab survives reloads; a failed save
// arrives with the error group flagged data-studio-error, which outranks the
// remembered tab. The rail hides its scrollbar, so wheel, arrow keys and
// scroll-into-view on activation keep every tab reachable.
function initGroups() {
    const groups = Array.from(document.querySelectorAll('[data-studio-group]'));
    if (!groups.length) return;

    const tabs = Array.from(document.querySelectorAll('[data-studio-tab]'));
    const rail = tabs[0] ? tabs[0].parentElement : null;
    const main = groups[0].closest('.studio-main');
    const rtl = rail ? getComputedStyle(rail).direction === 'rtl' : true;

    const activate = (key) => {
        groups.forEach((g) => {
            const on = g.dataset.studioGroup === key;
            g.hidden = !on;
            g.open = on;
        });
        tabs.forEach((t) => {
            const on = t.dataset.studioTab === key;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-current', on ? 'true' : 'false');
            // Keep the chosen tab inside the rail's visible slice.
            if (on) t.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        });
        try { localStorage.setItem('studio.tab', key); } catch { /* private mode */ }
    };

    tabs.forEach((t) => t.addEventListener('click', () => {
        activate(t.dataset.studioTab);
        main?.scrollTo({ top: 0, behavior: 'smooth' });
    }));

    // A vertical wheel over the rail scrolls it sideways instead of being
    // eaten by the bar behind it — but only while there is overflow, so the
    // page still scrolls normally once the rail fits all tabs.
    if (rail) rail.addEventListener('wheel', (e) => {
        if (rail.scrollWidth <= rail.clientWidth || Math.abs(e.deltaY) < Math.abs(e.deltaX)) return;
        e.preventDefault();
        rail.scrollBy({ left: (rtl ? -1 : 1) * e.deltaY, top: 0 });
    }, { passive: false });

    const focusTab = (t) => {
        activate(t.dataset.studioTab);
        t.focus();
    };

    tabs.forEach((t, i) => t.addEventListener('keydown', (e) => {
        // Visual direction: in RTL the tab flow runs right-to-left, so the
        // left arrow advances through the sections.
        const next = rtl ? 'ArrowLeft' : 'ArrowRight';
        const prev = rtl ? 'ArrowRight' : 'ArrowLeft';
        if (e.key === next) focusTab(tabs[(i + 1) % tabs.length]);
        else if (e.key === prev) focusTab(tabs[(i - 1 + tabs.length) % tabs.length]);
        else if (e.key === 'Home') focusTab(tabs[0]);
        else if (e.key === 'End') focusTab(tabs[tabs.length - 1]);
        else return;
        e.preventDefault();
    }));

    let saved = null;
    try { saved = localStorage.getItem('studio.tab'); } catch { /* private mode */ }
    const initial = groups.find((g) => g.hasAttribute('data-studio-error'))
        ?? groups.find((g) => g.dataset.studioGroup === saved)
        ?? groups.find((g) => g.open)
        ?? groups[0];
    activate(initial.dataset.studioGroup);
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

// Preview chrome: a desktop/mobile device switch plus a maximize button.
// The iframe always renders at the *device's* CSS width (1440 or 390 px)
// and is scaled down to fit the pane — otherwise a narrow pane would
// silently be a mobile-only preview, which is exactly what made the old
// fixed-width frame useless for theme work.
function initPreviewChrome(pane, frame) {
    const stage = pane.querySelector('[data-studio-preview-stage]');
    const deviceBtns = Array.from(pane.querySelectorAll('[data-studio-preview-device]'));
    const maxBtn = pane.querySelector('[data-studio-preview-max]');
    const split = document.getElementById('studio-split');
    if (!stage || !deviceBtns.length) return;

    const DEVICES = { desktop: { w: 1440, h: 900 }, mobile: { w: 390, h: 844 } };

    let device = 'desktop';
    try {
        if (localStorage.getItem('studio.preview.device') === 'mobile') device = 'mobile';
    } catch { /* private mode */ }

    const fit = () => {
        const w = stage.clientWidth;
        const h = stage.clientHeight;
        if (!w || !h) return; // pane hidden — ResizeObserver refires on show

        const dev = DEVICES[device] || DEVICES.desktop;
        // Contain-scale a real device viewport into the stage. A fixed
        // internal size (not stageHeight/scale) keeps svh units and the
        // hero's proportions honest, exactly like browser device mode.
        const scale = Math.min(1, w / dev.w, h / dev.h);
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
    // resetMax() is called by setLive() when the preview closes.
    const resetMax = () => {
        if (!split?.classList.contains('is-max')) return;
        split.classList.remove('is-max');
        maxBtn?.classList.remove('is-active');
        if (maxBtn) {
            maxBtn.title = 'تمام‌صفحه';
            maxBtn.querySelector('i')?.classList.toggle('fa-compress', false);
            maxBtn.querySelector('i')?.classList.toggle('fa-expand', true);
        }
    };

    maxBtn?.addEventListener('click', () => {
        const on = !!split?.classList.toggle('is-max');
        maxBtn.classList.toggle('is-active', on);
        maxBtn.title = on ? 'خروج از تمام‌صفحه' : 'تمام‌صفحه';
        maxBtn.querySelector('i')?.classList.toggle('fa-compress', on);
        maxBtn.querySelector('i')?.classList.toggle('fa-expand', !on);
    });

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
    const toggle = document.querySelector('[data-studio-live]');
    const split = document.getElementById('studio-split');
    const pane = document.getElementById('studio-preview-pane');
    let frame = pane?.querySelector('[data-studio-preview-frame]');
    const dot = pane?.querySelector('[data-studio-live-dot]');
    const reloadBtn = pane?.querySelector('[data-studio-preview-reload]');
    if (!toggle || !split || !pane || !frame) return;

    const chrome = initPreviewChrome(pane, frame);

    const liveUrl = form.dataset.liveUrl;
    const homeUrl = form.dataset.homeUrl;

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
    let swapping = false;
    let swapQueued = false;
    let lastTokens = null;

    const setDot = (state) => { if (dot) dot.dataset.state = state; };

    const frameDoc = () => {
        try { return frame.contentDocument; } catch { return null; } // cross-origin
    };

    // Swap the preview instead of navigating it. Setting `frame.src` blanks
    // the iframe to white for the whole request — the blink. Here a hidden
    // twin loads the same URL while the current frame stays on screen, and
    // on load the two trade places with the scroll position carried over.
    // It is still a real navigation, so the page's own scripts run fresh.
    const swapFrame = () => {
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
            let y = 0;
            try { y = current.contentWindow.scrollY; } catch { /* cross-origin */ }
            try { incoming.contentWindow.scrollTo(0, y); } catch { /* cross-origin */ }
            current.style.visibility = 'hidden';
            current.setAttribute('aria-hidden', 'true');
            delete current.dataset.loaded;
            incoming.style.visibility = '';
            incoming.removeAttribute('aria-hidden');
            incoming.dataset.loaded = '1';
            twin = current;   // the outgoing frame becomes the next buffer
            frame = incoming;
            swapping = false;
            if (swapQueued) { swapQueued = false; swapFrame(); }
        };
        incoming.addEventListener('load', onLoaded);
        incoming.src = homeUrl;
    };

    const sync = () => {
        if (!toggle.checked) return;
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
                return null;
            }
            return res.json();
        }).then((json) => {
            if (!json) return;
            lastTokens = { vars: json.vars, schemes: json.schemes };
            paintVars(document, json.vars, json.schemes);
            paintVars(frameDoc(), json.vars, json.schemes);
            setDot('ok');

            const structural = (json.changed || []).some((path) => modes[path] !== 'token');
            if (structural) {
                // Shorter than the old reload debounce: the swap is
                // invisible, so the only cost of firing early is one more
                // background page load.
                clearTimeout(swapTimer);
                swapTimer = setTimeout(swapFrame, 500);
            }
        }).catch((err) => {
            if (err?.name !== 'AbortError') setDot('error');
        });
    };

    const queue = () => {
        if (!toggle.checked) return;
        clearTimeout(syncTimer);
        syncTimer = setTimeout(sync, 300);
    };
    form.addEventListener('input', queue);
    form.addEventListener('change', queue);

    const setLive = (on) => {
        toggle.checked = on;
        pane.hidden = !on;
        split.classList.toggle('is-live', on);
        // Wide screens: the whole page turns into the studio (preview as
        // main content, settings as a docked bar). app.css owns the layout.
        document.body.classList.toggle('studio-mode', on);
        if (on) {
            if (!frame.dataset.loaded) {
                frame.src = homeUrl;
                frame.dataset.loaded = '1';
            }
            sync();
        } else {
            // The panel must not keep a theme it is no longer previewing,
            // nor a maximized pane that would hide the form next time.
            document.getElementById('studio-live-vars')?.remove();
            chrome?.resetMax();
        }
        try { localStorage.setItem('studio.live', on ? '1' : '0'); } catch { /* private mode */ }
    };

    toggle.addEventListener('change', () => setLive(toggle.checked));
    reloadBtn?.addEventListener('click', swapFrame);

    try {
        if (localStorage.getItem('studio.live') === '1') setLive(true);
    } catch { /* private mode */ }
}
