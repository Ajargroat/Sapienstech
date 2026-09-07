// Appearance studio: color picker <-> hex text sync, per-field reset (posts
// the hidden reset form), section reorder (moves rows so the checkbox array
// submits in the new order, never across a locked row), toggle label text,
// range bars that compose "<number><unit>" into their text input, a tabbed
// inspector rail (opening a group closes the rest; the active tab persists
// per browser), ⓘ hint bubbles that tap-to-pin on touch devices, and the
// live preview: a debounced POST to the studio's `live` endpoint whose token
// map is painted into the panel and the preview iframe without a reload —
// only structural changes reload it. The preview emulates desktop and mobile
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
        frame.style.width = dev.w + 'px';
        frame.style.height = dev.h + 'px';
        frame.style.transform = `scale(${scale})`;
        frame.style.left = Math.max(0, (w - dev.w * scale) / 2) + 'px';
        frame.style.top = Math.max(0, (h - dev.h * scale) / 2) + 'px';

        // Arm the device-switch morph only after the frame has been sized
        // once, so the initial reveal lands at full size instead of
        // animating out of the iframe's default 300x150 box.
        if (!frame.classList.contains('is-device-anim')) {
            requestAnimationFrame(() => frame.classList.add('is-device-anim'));
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

    // The previewed page scrolls, and in RTL its scrollbar sits on the left
    // edge of the device — a stray bar inside the mock. The frame is
    // same-origin, so hide it from the frame document on every load
    // (scrolling itself keeps working).
    const hideScrollbars = () => {
        try {
            const doc = frame.contentDocument;
            if (!doc?.head || doc.getElementById('studio-preview-no-scroll')) return;
            const style = doc.createElement('style');
            style.id = 'studio-preview-no-scroll';
            style.textContent = 'html,body{scrollbar-width:none}'
                + 'html::-webkit-scrollbar,body::-webkit-scrollbar{display:none;width:0;height:0}';
            doc.head.appendChild(style);
        } catch { /* cross-origin */ }
    };
    frame.addEventListener('load', hideScrollbars);

    apply();

    return { fit, resetMax };
}

// Live preview: every edit posts the whole form to the studio's `live`
// endpoint (session-scoped preview, nothing persisted). The response carries
// the fully derived token map, which is painted into the panel and the
// iframe as a trailing <style> — instant, no reload. Fields the schema
// classifies as structural (data-live="reload": variants, copy, nav...)
// change markup, not just variables, so the iframe reloads for those.
function initLive(form) {
    const toggle = document.querySelector('[data-studio-live]');
    const split = document.getElementById('studio-split');
    const pane = document.getElementById('studio-preview-pane');
    const frame = pane?.querySelector('[data-studio-preview-frame]');
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
    let reloadTimer = null;
    let inflight = null;

    const setDot = (state) => { if (dot) dot.dataset.state = state; };

    const frameDoc = () => {
        try { return frame.contentDocument; } catch { return null; } // cross-origin
    };

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

    const reloadFrame = () => {
        let y = 0;
        try { y = frame.contentWindow.scrollY; } catch { /* cross-origin */ }
        frame.addEventListener('load', () => {
            try { frame.contentWindow.scrollTo(0, y); } catch { /* cross-origin */ }
        }, { once: true });
        frame.src = homeUrl;
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
            paintVars(document, json.vars, json.schemes);
            paintVars(frameDoc(), json.vars, json.schemes);
            setDot('ok');

            const structural = (json.changed || []).some((path) => modes[path] !== 'token');
            if (structural) {
                clearTimeout(reloadTimer);
                reloadTimer = setTimeout(reloadFrame, 1200);
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
    reloadBtn?.addEventListener('click', reloadFrame);

    try {
        if (localStorage.getItem('studio.live') === '1') setLive(true);
    } catch { /* private mode */ }
}
