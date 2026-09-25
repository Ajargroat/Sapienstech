// The inspector navigates the existing schema form; it never serializes DOM/CSS
// back to the server. Shared tokens and block-instance overrides stay distinct.
export default function initWorkspace(form) {
    const root = document.getElementById('studio-split');
    const inspector = root?.querySelector('[data-object-inspector]');
    if (!inspector || root.dataset.workspaceReady) return;
    root.dataset.workspaceReady = '1';
    const title = inspector.querySelector('[data-object-title]');
    const scope = inspector.querySelector('[data-object-scope]');
    const metrics = inspector.querySelector('[data-object-metrics]');
    const shortcuts = inspector.querySelector('[data-object-controls]');
    // The layer tree moved out of the inspector into the left rail (it names
    // the whole document, not just the selected object), so it resolves from
    // the split rather than from the inspector.
    const layers = root.querySelector('[data-page-layers]');
    const fields = [...form.querySelectorAll('[data-studio-field]')];
    let doc = null;
    let selected = null;
    let identity = null;
    let observer = null;
    // Interaction mode derives from the active workspace tool (select vs
    // interact); the old preview-head checkbox is gone.
    let activeTool = 'select';
    const interactive = () => activeTool === 'interact';
    const fieldAt = (path) => fields.find((field) => field.dataset.studioField === path);
    // Jump to a Design group. Test fixtures still carry the old tab buttons
    // (a real click there is exactly what they assert); the production page
    // has none — its groups are an accordion, so a jump dispatches
    // `studio:open-group` and theme-studio.js opens the group by key.
    const tab = (key) => root.querySelector(`[data-studio-tab="${key}"]`)
        || (root.querySelector(`[data-studio-group="${key}"]`)
            ? { click: () => document.dispatchEvent(new CustomEvent('studio:open-group', { detail: { key } })) }
            : undefined);

    // --- Inspector column -------------------------------------------------
    // The whole inspector (`.studio-main`) can fold away so the canvas gets
    // the full width. Selecting an object or clicking a rail tab reopens it;
    // the choice persists like the device choice does.
    const inspectorToggle = root.querySelector('[data-inspector-toggle]');
    let inspectorOpen = true;
    try { inspectorOpen = localStorage.getItem('studio.inspector.open') !== '0'; } catch { inspectorOpen = true; }

    // --- Panel widths ------------------------------------------------------
    // Both side panels resize by dragging their sash; a release below the
    // snap threshold folds a panel away, a double-click toggles it. The
    // values feed the CSS variables the grid columns and the sashes read, so
    // the whole layout — and its content — follows every pixel.
    const RAIL_DEFAULT = 240;
    const INSPECTOR_DEFAULT = 340;
    const RAIL_MAX = 480;
    const INSPECTOR_MAX = 560;
    const RAIL_SNAP = 120;      // a release below this collapses the rail
    const INSPECTOR_SNAP = 200; // …and below this one collapses the inspector
    const readStored = (key, fallback) => {
        try {
            const raw = localStorage.getItem(key);
            if (raw === null) return fallback; // Number(null) is 0 — not a stored width
            const value = Number(raw);
            return Number.isFinite(value) && value >= 0 ? value : fallback;
        } catch { return fallback; }
    };
    const store = (key, value) => {
        try { localStorage.setItem(key, String(value)); } catch { /* private mode */ }
    };
    let railW = readStored('studio.rail.w', RAIL_DEFAULT) || 0;
    let inspectorW = readStored('studio.inspector.w', INSPECTOR_DEFAULT) || INSPECTOR_DEFAULT;
    if (inspectorW <= 0) inspectorW = INSPECTOR_DEFAULT;
    const applyWidths = () => {
        root.style.setProperty('--rail-w', railW + 'px');
        root.style.setProperty('--inspector-w', (inspectorOpen ? inspectorW : 0) + 'px');
    };

    // Clicking a Design group reopens a collapsed inspector (same affordance
    // as selecting a canvas object): the group's fields live in that column.
    root.addEventListener('click', (event) => {
        if (event.target.closest?.('[data-studio-tab], [data-studio-group] > summary')) openInspector();
    });
    const paintInspector = () => {
        root.classList.toggle('is-inspector-closed', !inspectorOpen);
        inspectorToggle?.setAttribute('aria-expanded', String(inspectorOpen));
        if (inspectorToggle) inspectorToggle.title = inspectorOpen ? 'Close inspector panel' : 'Open inspector panel';
        applyWidths();
    };
    const openInspector = () => {
        if (inspectorOpen) return;
        inspectorOpen = true;
        try { localStorage.setItem('studio.inspector.open', '1'); } catch { /* private mode */ }
        paintInspector();
    };
    inspectorToggle?.addEventListener('click', () => {
        inspectorOpen = !inspectorOpen;
        store('studio.inspector.open', inspectorOpen ? '1' : '0');
        paintInspector();
    });
    paintInspector();

    // One sash per panel border. Pointer capture keeps the drag alive over
    // the preview iframe; a double-click or the keyboard does the same job
    // without a pointer. Fixtures without sashes are simply skipped.
    const wireSash = (side) => {
        const sash = root.querySelector(`[data-studio-sash="${side}"]`);
        if (!sash) return;
        const limit = () => (side === 'rail' ? RAIL_MAX : INSPECTOR_MAX);
        const clamp = (value) => Math.min(limit(), Math.max(0, value));
        const pointerWidth = (clientX) => {
            const box = root.getBoundingClientRect();
            return side === 'rail' ? clientX - box.left : box.right - clientX;
        };
        const preview = (raw) => {
            const width = clamp(raw);
            if (side === 'rail') {
                railW = width;
            } else {
                if (!inspectorOpen) {
                    if (width <= 8) return; // still collapsed; wait for intent
                    inspectorOpen = true;
                    store('studio.inspector.open', '1');
                    root.classList.remove('is-inspector-closed');
                    inspectorToggle?.setAttribute('aria-expanded', 'true');
                }
                inspectorW = width;
            }
            applyWidths();
        };
        const commit = (raw) => {
            const width = clamp(raw);
            if (side === 'rail') {
                railW = width < RAIL_SNAP ? 0 : width;
                store('studio.rail.w', railW);
            } else if (width < INSPECTOR_SNAP) {
                inspectorOpen = false;
                store('studio.inspector.open', '0');
            } else {
                inspectorW = width;
                inspectorOpen = true;
                store('studio.inspector.open', '1');
                store('studio.inspector.w', inspectorW);
            }
            paintInspector();
        };
        sash.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) return;
            event.preventDefault();
            root.classList.add('is-resizing');
            try { sash.setPointerCapture(event.pointerId); } catch { /* unsupported */ }
            const move = (e) => preview(pointerWidth(e.clientX));
            const end = (e) => {
                sash.removeEventListener('pointermove', move);
                sash.removeEventListener('pointerup', end);
                sash.removeEventListener('pointercancel', end);
                root.classList.remove('is-resizing');
                try { sash.releasePointerCapture(event.pointerId); } catch { /* released */ }
                commit(pointerWidth(e.clientX));
            };
            sash.addEventListener('pointermove', move);
            sash.addEventListener('pointerup', end);
            sash.addEventListener('pointercancel', end);
            move(event);
        });
        sash.addEventListener('dblclick', () => {
            if (side === 'rail') {
                railW = railW > 0 ? 0 : RAIL_DEFAULT;
                store('studio.rail.w', railW);
            } else {
                inspectorOpen = !inspectorOpen;
                store('studio.inspector.open', inspectorOpen ? '1' : '0');
            }
            paintInspector();
        });
        sash.addEventListener('keydown', (event) => {
            const step = 16;
            if (side === 'rail') {
                if (event.key === 'ArrowLeft') railW = clamp(railW > 0 ? railW - step : 0);
                else if (event.key === 'ArrowRight') railW = clamp(railW > 0 ? railW + step : RAIL_SNAP);
                else if (event.key === 'Home') railW = RAIL_DEFAULT;
                else if (event.key === 'Enter' || event.key === ' ') railW = railW > 0 ? 0 : RAIL_DEFAULT;
                else return;
                store('studio.rail.w', railW);
            } else {
                if (event.key === 'ArrowLeft') inspectorW = clamp(inspectorW + step);
                else if (event.key === 'ArrowRight') inspectorW = clamp(inspectorW - step);
                else if (event.key === 'Home') inspectorW = INSPECTOR_DEFAULT;
                else if (event.key === 'Enter' || event.key === ' ') {
                    inspectorOpen = !inspectorOpen;
                    store('studio.inspector.open', inspectorOpen ? '1' : '0');
                    paintInspector();
                    event.preventDefault();
                    return;
                } else return;
                if (inspectorW > 0 && !inspectorOpen) {
                    inspectorOpen = true;
                    store('studio.inspector.open', '1');
                }
                store('studio.inspector.w', inspectorW);
            }
            event.preventDefault();
            paintInspector();
        });
    };
    wireSash('rail');
    wireSash('inspector');

    const openField = (field) => {
        if (!field) return;
        openInspector();
        tab(field.closest('[data-studio-group]')?.dataset.studioGroup)?.click();
        form.querySelectorAll('.is-object-field').forEach((node) => node.classList.remove('is-object-field'));
        field.classList.add('is-object-field');
        field.scrollIntoView?.({ block: 'nearest' });
        field.querySelector('input:not([type="hidden"]),textarea,select,button')?.focus({ preventScroll: true });
    };
    const shortcut = (label, action) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.addEventListener('click', action);
        shortcuts.append(button);
    };
    const visual = (element) => element?.matches('[data-studio-block]')
        ? element.querySelector('.lp-btn') || element.firstElementChild || element : element;

    // --- Contextual properties ---------------------------------------------
    // The real schema controls for the selected element are relocated into the
    // context panel. Relocating rather than duplicating keeps exactly one input
    // per name, so the existing validation and tenant scoping still apply and
    // no duplicate value can reach the server. `homes` remembers where each
    // moved field came from so closing the panel puts the form back as it was.
    const homes = new Map();

    // --- Node properties ----------------------------------------------------
    // The canvas document is the storage for node-local values: one opaque
    // JSON textarea the server re-cleans through StudioStyles::cleanNodes on
    // save. This table mirrors the server KEYS whitelist so the editor can
    // validate before it writes or emits; the server stays the authority —
    // drift drops values there, it never lets a hostile one through.
    const NODE_KEYS = {
        background: { prop: 'background', unit: '', hex: true },
        color: { prop: 'color', unit: '', hex: true },
        radius: { prop: 'border-radius', unit: 'px', min: 0, max: 128 },
        padding: { prop: 'padding', unit: 'px', min: 0, max: 128 },
        width: { prop: 'inline-size', unit: '%', min: 10, max: 100 },
        font_scale: { prop: 'font-size', unit: '%', min: 50, max: 400 },
        align: { prop: 'text-align', unit: 'choice', choices: ['start', 'center', 'end'] },
        position: { prop: 'position', unit: 'choice', choices: ['static', 'relative', 'absolute', 'fixed', 'sticky'] },
        display: { prop: 'display', unit: 'choice', choices: ['block', 'inline', 'inline-block', 'flex', 'inline-flex', 'grid', 'inline-grid', 'flow-root'] },
        justify_content: { prop: 'justify-content', unit: 'choice', choices: ['flex-start', 'flex-end', 'center', 'space-between', 'space-around', 'space-evenly'] },
        align_items: { prop: 'align-items', unit: 'choice', choices: ['stretch', 'center', 'flex-start', 'flex-end', 'baseline'] },
        flex_direction: { prop: 'flex-direction', unit: 'choice', choices: ['row', 'column', 'row-reverse', 'column-reverse'] },
        overflow: { prop: 'overflow', unit: 'choice', choices: ['visible', 'hidden', 'clip', 'auto', 'scroll'] },
        top: { prop: 'top', unit: 'ipx', min: -3000, max: 3000 },
        left: { prop: 'left', unit: 'ipx', min: -3000, max: 3000 },
        order: { prop: 'order', unit: 'i', min: -999, max: 999 },
        grid_column: { prop: 'grid-column', unit: 'str', pattern: /^(?:auto|-?\d{1,2}(?:\s*\/\s*(?:span\s+)?-?\d{1,2})?)$/ },
        grid_row: { prop: 'grid-row', unit: 'str', pattern: /^(?:auto|-?\d{1,2}(?:\s*\/\s*(?:span\s+)?-?\d{1,2})?)$/ },
        height: { prop: 'block-size', unit: 'str', pattern: /^(?:auto|-?\d{1,4}(?:px|%|rem|em|vw|vh))$/ },
        margin: { prop: 'margin', unit: 'px', min: 0, max: 256 },
        gap: { prop: 'gap', unit: 'px', min: 0, max: 128 },
        font_weight: { prop: 'font-weight', unit: 'choice', choices: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        line_height: { prop: 'line-height', unit: '%', min: 50, max: 300 },
        text_transform: { prop: 'text-transform', unit: 'choice', choices: ['none', 'uppercase', 'lowercase', 'capitalize'] },
        border_width: { prop: 'border-width', unit: 'px', min: 0, max: 32 },
        border_style: { prop: 'border-style', unit: 'choice', choices: ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'] },
        border_color: { prop: 'border-color', unit: '', hex: true },
        outline_width: { prop: 'outline-width', unit: 'px', min: 0, max: 32 },
        outline_style: { prop: 'outline-style', unit: 'choice', choices: ['none', 'hidden', 'solid', 'dashed', 'dotted', 'double'] },
        outline_color: { prop: 'outline-color', unit: '', hex: true },
        box_shadow: { prop: 'box-shadow', unit: 'str', pattern: /^(?:inset )?(?:0|-?\d{1,3}px) (?:0|-?\d{1,3}px) (?:0|-?\d{1,4}px)(?: (?:0|-?\d{1,3}px))? #[0-9A-Fa-f]{6}$/ },
        opacity: { prop: 'opacity', unit: 'pct', min: 0, max: 100 },
        filter: { prop: 'filter', unit: 'str', pattern: /^[a-zA-Z0-9().,%\s-]*$/ },
        transform: { prop: 'transform', unit: 'str', pattern: /^[a-zA-Z0-9().,%\s-]*$/ },
        transition: {
            prop: 'transition', unit: 'choice',
            choices: ['color 0.15s ease', 'color 0.2s ease-in-out', 'background-color 0.2s ease',
                'transform 0.2s ease', 'box-shadow 0.2s ease', 'all 0.15s ease', 'all 0.2s ease', 'all 0.3s ease-in-out'],
        },
    };
    // The token catalog, served by the form (ThemeTokens::VARS) so the
    // dropdown and the emitter can never disagree with the server. Keys are
    // token paths (colors.primary); values are CSS var names without `--`.
    let TOKEN_VARS = {};
    try { TOKEN_VARS = JSON.parse(form.dataset.studioTokens || '{}'); } catch { TOKEN_VARS = {}; }
    // Which catalog groups may bind to which key — the server accepts any
    // catalog token (it only guarantees a safe var name); this map keeps the
    // dropdown honest about units and meaning.
    const TOKEN_GROUPS = {
        background: ['colors.', 'gradients.'],
        color: ['colors.'],
        border_color: ['colors.'],
        outline_color: ['colors.'],
        radius: ['shape.'],
        border_width: ['shape.'],
        outline_width: ['shape.'],
        padding: ['spacing.', 'layout.'],
        margin: ['spacing.', 'layout.'],
        gap: ['spacing.', 'layout.'],
        font_scale: ['typography.'],
        line_height: ['typography.'],
        font_weight: ['typography.'],
        width: ['layout.', 'spacing.'],
        height: ['layout.', 'landing.'],
        opacity: ['effects.'],
        filter: ['effects.'],
    };
    const tokensFor = (key) => {
        const groups = TOKEN_GROUPS[key];
        if (!groups) return [];
        return Object.keys(TOKEN_VARS).filter((name) => groups.some((prefix) => name.startsWith(prefix)));
    };
    // Materialize the token's CURRENT effective value as a local literal, so
    // detaching never silently swaps a reference for a stale guess: what you
    // saw while bound is exactly what you keep when unbound. Inside a state
    // or breakpoint layer the computed style still reports the BASE value
    // (neither pseudo-class nor media query is active on demand), so that
    // path reads the token's own schema field instead: what the token owns
    // is what the layer re-emits.
    const materialize = (key, path) => {
        const spec = NODE_KEYS[key];
        if (!selected?.isConnected || !doc) return null;
        const [, layerName] = layerBucket();
        if (layerName) {
            const stored = activeRow(path)[key];
            const token = stored && typeof stored === 'object' ? stored.token : '';
            const field = token ? fieldAt(`theme.${token}`) : null;
            const input = field?.querySelector('input[type="color"]')
                || field?.querySelector('input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"])');
            const raw = String(input?.value ?? '');
            if (!raw) return null;
            if (spec.hex) return /^#[0-9a-f]{6}$/i.test(raw) ? raw : null;
            if (spec.unit === 'choice') return spec.choices.includes(raw) ? raw : null;
            if (spec.unit === 'str') return spec.pattern.test(raw) ? raw : null;
            const number = Number(raw);
            if (!Number.isFinite(number)) return null;
            return Math.min(spec.max, Math.max(spec.min, Math.round(number)));
        }
        const style = doc.defaultView.getComputedStyle(selected);
        const raw = String(style.getPropertyValue(spec.prop) ?? '').trim();
        if (spec.hex) {
            // A translucent colour has no hex form — refuse rather than drop
            // the alpha on detach.
            if (/^rgba\(/.test(raw)) {
                const alpha = /rgba\([^)]*,\s*([\d.]+)\s*\)/.exec(raw);
                if (!alpha || Number(alpha[1]) < 0.999) return null;
            }
            const match = /^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/.exec(raw) || /^#([0-9a-f]{6})$/i.exec(raw);
            if (!match) return null;
            if (match.length === 2) return `#${match[1]}`.toLowerCase();
            const toHex = (part) => Number(part).toString(16).padStart(2, '0');
            return `#${toHex(match[1])}${toHex(match[2])}${toHex(match[3])}`;
        }
        if (spec.unit === 'px' || spec.unit === 'ipx' || spec.unit === 'i') {
            const number = parseInt(raw, 10);
            return Number.isFinite(number) ? number : null;
        }
        if (spec.unit === '%') {
            const parentWidth = selected.parentElement?.getBoundingClientRect().width || 0;
            const pixels = parseFloat(raw);
            if (!parentWidth || !Number.isFinite(pixels)) return null;
            const percent = Math.round(pixels / parentWidth * 100);
            return Math.min(spec.max, Math.max(spec.min, percent));
        }
        if (spec.unit === 'pct') {
            const number = parseFloat(raw);
            return Number.isFinite(number) ? Math.round(number * 100) : null;
        }
        if (key === 'height') {
            return /^(?:auto|-?\d{1,4}(?:px|%|rem|em|vw|vh))$/.test(raw) ? raw : null;
        }
        if (spec.unit === 'str') {
            return spec.pattern.test(raw) ? raw : null;
        }
        return null;
    };
    // --- Editing layer (states & breakpoints) --------------------------------
    // A node's values live in three maps and the inspector writes exactly one
    // of them at a time: base `props`, or one `states` row, or one `breakpoints`
    // row. State and breakpoint are exclusive because the stored model has no
    // combined layer — a value is either `states.X` or `breakpoints.Y`, never
    // both — and the emitter would have nowhere to put a hybrid. These tables
    // mirror StudioStyles::STATE_SELECTORS / ::BREAKPOINTS so the live frame
    // paints exactly the rules the server emits on save.
    const STATE_SELECTORS = {
        hover: ':hover',
        active: ':active',
        focus: ':focus',
        focus_visible: ':focus-visible',
        disabled: '[disabled]',
        selected: '[aria-selected="true"]',
    };
    const STATE_ORDER = Object.keys(STATE_SELECTORS);
    const BREAKPOINTS = {
        mobile: '(max-width:640px)',
        tablet: '(min-width:641px) and (max-width:1024px)',
        desktop: '(min-width:1025px)',
    };
    // Narrow → wide, the order the server emits so a wider rule wins.
    const BREAKPOINT_ORDER = ['mobile', 'tablet', 'desktop'];
    // The active layer. Both start empty (base props): the status-bar device
    // is only a VIEW until the user picks a width layer — or clicks a device,
    // which enters that layer so preview width and editing width can never
    // disagree about which override is being written.
    let editState = '';
    let editBreakpoint = '';
    const layerBucket = () => (editState ? ['states', editState]
        : editBreakpoint ? ['breakpoints', editBreakpoint] : ['props', '']);
    const activeRow = (path) => {
        const [bucket, name] = layerBucket();
        const node = readNodes()[path];
        if (!name) return node?.props ?? {};
        const row = node?.[bucket]?.[name];
        return row && typeof row === 'object' ? row : {};
    };
    // Where the value actually comes from — inside the ACTIVE layer. A literal
    // stored in a state/breakpoint row reports that layer (State/Breakpoint
    // kinds); a token reference still reports as a token (the title names the
    // layer). Everything else falls through to the base chain, because that is
    // what the frame is actually showing while the layer has no override of
    // its own.
    const INHERITED_CSS = new Set([
        'color', 'font-family', 'font-size', 'font-weight', 'line-height',
        'letter-spacing', 'word-spacing', 'text-align', 'text-transform',
        'visibility', 'cursor', 'white-space', 'direction',
    ]);
    const originOf = (path, key) => {
        const [, layerName] = layerBucket();
        const stored = activeRow(path)[key];
        if (stored && typeof stored === 'object') {
            return {
                kind: 'token',
                label: 'Token',
                title: layerName
                    ? `Token reference ${stored.token} (${layerName} layer)`
                    : `Token reference ${stored.token}`,
            };
        }
        if (stored !== undefined && stored !== null && stored !== '') {
            if (editState) {
                return { kind: 'state', label: 'State', title: `${editState} value on this node` };
            }
            if (editBreakpoint) {
                return { kind: 'breakpoint', label: 'Breakpoint', title: `${editBreakpoint} value on this node` };
            }
            return { kind: 'local', label: 'Local', title: 'Local value on this node' };
        }
        if (!selected?.isConnected || !doc) return { kind: 'component', label: 'Component', title: 'Template style' };
        const property = NODE_KEYS[key].prop;
        const inline = selected.style?.getPropertyValue?.(property);
        if (inline) return { kind: 'component', label: 'Component', title: 'Inline template style' };
        const style = doc.defaultView.getComputedStyle(selected);
        const value = style.getPropertyValue(property);
        const parent = selected.parentElement;
        if (parent && INHERITED_CSS.has(property)) {
            const parentValue = doc.defaultView.getComputedStyle(parent).getPropertyValue(property);
            if (parentValue && value === parentValue) {
                return { kind: 'inherited', label: 'Inherited', title: 'Inherited from parent' };
            }
        }
        return { kind: 'component', label: 'Component', title: 'Template default style' };
    };
    const validNodeValue = (key, value) => {
        const spec = NODE_KEYS[key];
        if (!spec || value === null || value === undefined || value === '') return false;
        if (typeof value === 'object') {
            return !Array.isArray(value) && typeof value.token === 'string' && TOKEN_VARS[value.token] !== undefined;
        }
        if (spec.unit === 'choice') return spec.choices.includes(String(value));
        if (spec.hex) return /^#[0-9a-f]{6}$/i.test(String(value));
        if (spec.unit === 'str') return spec.pattern.test(String(value));
        const number = Number(value);
        return Number.isInteger(number) && number >= spec.min && number <= spec.max;
    };
    const nodeDeclaration = (key, value) => {
        const spec = NODE_KEYS[key];
        if (value && typeof value === 'object') {
            return `${spec.prop}:var(--${TOKEN_VARS[value.token]});`;
        }
        const raw = ['px', '%', 'pct', 'ipx', 'i'].includes(spec.unit) ? Number(value) : String(value);
        const css = spec.unit === 'px' || spec.unit === 'ipx' ? `${raw}px`
            : spec.unit === '%' ? `${raw}%`
                : spec.unit === 'pct' ? String(raw / 100)
                    : String(raw);
        return `${spec.prop}:${css};`;
    };

    // Which editors a node exposes, decided by what it IS — tag, content and
    // computed display — rather than hardcoded per component. Tokens ride the
    // value-source row inside each control; states and breakpoints are the
    // layer switcher above this table (they pick WHERE a value is written,
    // not WHICH value).
    const CAPABILITIES = [
        {
            id: 'layout', label: 'Layout', when: () => true,
            keys: [['position', 'Position'], ['display', 'Display'], ['top', 'Top'], ['left', 'Left'],
                ['overflow', 'Overflow'],
                ['flex_direction', 'Flex direction'], ['justify_content', 'Justify content'], ['align_items', 'Align items'],
                ['order', 'Order'],
                ['grid_column', 'Column'], ['grid_row', 'Row']],
            flexKeys: ['flex_direction', 'justify_content', 'align_items', 'order'],
            positionKeys: ['top', 'left'],
            gridKeys: ['grid_column', 'grid_row'],
        },
        { id: 'size', label: 'Size', when: () => true, keys: [['width', 'Width'], ['height', 'Height']] },
        { id: 'spacing', label: 'Spacing', when: () => true, keys: [['padding', 'Padding'], ['margin', 'Margin'], ['gap', 'Gap']] },
        {
            id: 'typography', label: 'Typography',
            when: (element) => (element.textContent || '').trim() !== '',
            keys: [['color', 'Text color'], ['font_scale', 'Scale'], ['align', 'Align'], ['font_weight', 'Weight'],
                ['line_height', 'Line height'], ['text_transform', 'Text transform']],
        },
        { id: 'background', label: 'Background', when: () => true, keys: [['background', 'Color']] },
        { id: 'border', label: 'Border', when: () => true, keys: [['border_width', 'Width'], ['border_style', 'Style'], ['border_color', 'Color']] },
        {
            id: 'outline', label: 'Focus outline',
            when: (element) => element.matches('a,button,input,select,textarea,summary,[tabindex]'),
            keys: [['outline_width', 'Width'], ['outline_style', 'Style'], ['outline_color', 'Color']],
        },
        { id: 'radius', label: 'Radius', when: () => true, keys: [['radius', 'Corner']] },
        { id: 'shadow', label: 'Shadow', when: () => true, keys: [['box_shadow', 'Shadow']] },
        { id: 'effects', label: 'Effects', when: () => true, keys: [['opacity', 'Opacity'], ['filter', 'Blur']] },
        { id: 'transform', label: 'Transform', when: () => true, keys: [['transform', 'Transform']] },
        { id: 'animation', label: 'Transition', when: () => true, keys: [['transition', 'Transition']] },
    ];
    const contextPanel = form.querySelector('[data-context-panel]');
    const contextTitle = form.querySelector('[data-context-title]');
    const contextNote = form.querySelector('[data-context-note]');
    const contextFields = form.querySelector('[data-context-fields]');
    const crumbHost = form.querySelector('[data-context-crumb]');

    // --- Capability editors -------------------------------------------------
    // One control per stored key; the kind follows the key's unit. Every
    // control writes through setNodeProp (validate → store → live emit) and
    // carries its own reset-to-inherit button, so removing a value lets the
    // layer below show through again.
    // A plain option element — the `Option` constructor is a browser global
    // the jsdom test harness does not install, and createElement works
    // identically in both worlds.
    const domOption = (text, value) => {
        const node = document.createElement('option');
        node.textContent = text;
        node.value = value;
        return node;
    };
    // Write a catalog token's GLOBAL value through its own schema field, so a
    // promote rides the same validation, scope (preview/me/everyone) and live
    // pipeline as every other theme edit. Only fields the schema publishes can
    // be promote targets — the rest of the catalog is read-only from here.
    const writeThemeValue = (token, value) => {
        const field = fieldAt(`theme.${token}`);
        if (!field) return false;
        const input = field.querySelector('input[type="color"]')
            || field.querySelector('input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"])');
        if (!input) return false;
        const literal = String(value);
        const previous = input.value;
        input.value = literal;
        const rejected = (input.type === 'color' || input.type === 'number' || input.type === 'text')
            && String(input.value).toLowerCase() !== literal.toLowerCase();
        if (rejected) { input.value = previous; return false; }
        input.dispatchEvent(new Event('input', { bubbles: true }));
        const twin = field.querySelector('[data-color-text]');
        if (twin) twin.value = literal;
        return true;
    };
    const capControl = (path, key, label, current) => {
        const spec = NODE_KEYS[key];
        const stored = current[key];
        const bound = stored && typeof stored === 'object' ? String(stored.token) : '';
        const wrap = document.createElement('div');
        wrap.className = 'studio-context-style';
        const name = document.createElement('span');
        name.textContent = label;
        const origin = document.createElement('em');
        origin.className = 'studio-context-origin';
        name.append(origin);
        // Token row: the value-source picker every token-capable key gets,
        // plus — while a LOCAL value is showing — the promote ("create a
        // token") action with its target select and confirm step.
        const tokenNames = tokensFor(key);
        const tokenRow = document.createElement('span');
        tokenRow.className = 'studio-context-token';
        if (tokenNames.length) {
            const picker = document.createElement('select');
            picker.setAttribute('aria-label', `${label} — value source`);
            picker.append(domOption('Local (this element)', ''));
            for (const token of tokenNames) picker.append(domOption(token, token));
            picker.value = bound;
            picker.addEventListener('change', () => {
                const token = picker.value;
                if (!token) {
                    // Detach: materialize what the token currently resolves to
                    // as the local literal — explicit, never silent. A rebuild
                    // follows either way so the literal control re-enables
                    // (while bound it is disabled on purpose).
                    const literal = materialize(key, path);
                    if (literal !== null) setNodeProp(path, key, literal);
                    rerenderCaps();
                    return;
                }
                if (setNodeProp(path, key, { token })) paintOrigin();
                rerenderCaps();
            });
            tokenRow.append(picker);
        }
        let promoteButton = null;
        const buildPromote = () => {
            const storedNow = activeRow(path)[key];
            const localValue = storedNow !== undefined && storedNow !== null && storedNow !== ''
                && typeof storedNow !== 'object' ? storedNow : null;
            if (localValue === null) return null;
            const promotable = tokenNames.filter((token) => {
                const field = fieldAt(`theme.${token}`);
                if (!field) return false;
                return field.querySelector('input[type="color"]')
                    ? spec.hex && /^#[0-9a-f]{6}$/i.test(String(localValue))
                    : true;
            });
            if (!promotable.length) return null;
            const promote = document.createElement('button');
            promote.type = 'button';
            promote.className = 'studio-context-promote';
            promote.textContent = 'Save as token';
            promote.title = 'Saves the current value as the global value of a token and binds this node to it. Every place using that token changes.';
            promote.addEventListener('click', () => {
                const target = document.createElement('select');
                target.setAttribute('aria-label', `${label} — target token`);
                target.append(domOption('Choose…', ''));
                for (const token of promotable) target.append(domOption(token, token));
                const confirm = document.createElement('button');
                confirm.type = 'button';
                confirm.className = 'studio-context-promote';
                confirm.textContent = 'Save';
                confirm.disabled = true;
                target.addEventListener('change', () => { confirm.disabled = !target.value; });
                confirm.addEventListener('click', () => {
                    if (!target.value) return;
                    if (!writeThemeValue(target.value, localValue)) return;
                    if (setNodeProp(path, key, { token: target.value })) rerenderCaps();
                });
                const cancel = document.createElement('button');
                cancel.type = 'button';
                cancel.className = 'studio-context-style-clear';
                cancel.textContent = 'Cancel';
                cancel.addEventListener('click', () => rerenderCaps());
                promote.replaceWith(target, confirm, cancel);
                target.focus?.();
            });
            return promote;
        };
        const paintOrigin = () => {
            const info = originOf(path, key);
            origin.textContent = info.label;
            origin.title = info.title;
            origin.dataset.kind = info.kind;
            // Promote exists only while a LOCAL literal is showing (in the
            // base layer or in the state/breakpoint row being edited) — and a
            // first literal edit promotes the button into existence here,
            // without rebuilding the control the user is still dragging.
            const localKind = info.kind === 'local' || info.kind === 'state' || info.kind === 'breakpoint';
            if (localKind && tokenNames.length && !promoteButton) {
                promoteButton = buildPromote();
                if (promoteButton) tokenRow.append(promoteButton);
            } else if (!localKind && promoteButton) {
                promoteButton.remove();
                promoteButton = null;
            }
        };
        paintOrigin();
        const commit = (value) => {
            if (!setNodeProp(path, key, value)) return;
            paintOrigin();
            // The flex/grid/positioned sub-controls hang off computed styles,
            // so those changes rebuild the capability sections in place.
            if (key === 'display' || key === 'position') rerenderCaps();
        };
        const clear = () => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'studio-context-style-clear';
            button.textContent = 'Default';
            button.title = 'Remove this value and fall back to the layer below';
            button.addEventListener('click', () => { commit(null); rerenderCaps(); });
            return button;
        };
        // Common tail: name, the primary control(s), the token row, then the
        // reset button. While a token is bound the literal control is
        // disabled — the value comes from the token, and typing into it would
        // silently replace the reference.
        const finish = (parts, withClear = true) => {
            if (bound) {
                for (const part of parts) {
                    if ('disabled' in part) part.disabled = true;
                    part.querySelectorAll?.('input,select,button').forEach((inner) => { inner.disabled = true; });
                }
            }
            wrap.append(name, ...parts);
            if (tokenNames.length) wrap.append(tokenRow);
            if (withClear) wrap.append(clear());
            return wrap;
        };

        if (spec.hex) {
            const picker = document.createElement('input');
            picker.type = 'color';
            picker.setAttribute('aria-label', label);
            picker.value = /^#[0-9a-f]{6}$/i.test(String(stored || '')) ? stored : '#000000';
            picker.addEventListener('input', () => commit(picker.value));
            return finish([picker]);
        }

        if (spec.unit === 'choice') {
            const select = document.createElement('select');
            select.setAttribute('aria-label', label);
            select.append(domOption('Default', ''));
            for (const choice of spec.choices) select.append(domOption(choice, choice));
            select.value = stored === undefined || typeof stored === 'object' ? '' : String(stored);
            select.addEventListener('change', () => commit(select.value || null));
            return finish([select], false);
        }

        if (spec.unit === 'px' || spec.unit === '%' || spec.unit === 'pct' || spec.unit === 'ipx' || spec.unit === 'i') {
            const bar = document.createElement('input');
            bar.type = 'range';
            bar.min = String(spec.min);
            bar.max = String(spec.max);
            bar.step = '1';
            bar.setAttribute('aria-label', label);
            const out = document.createElement('output');
            const current = Number.isFinite(Number(stored)) && typeof stored !== 'object' && stored !== undefined ? Number(stored) : null;
            bar.value = String(current ?? Math.round((spec.min + spec.max) / 2));
            const suffix = spec.unit === 'i' ? '' : spec.unit === '%' || spec.unit === 'pct' ? '%' : 'px';
            out.textContent = bound ? 'Token' : current === null ? 'Default' : `${current}${suffix}`;
            bar.addEventListener('input', () => {
                commit(Number(bar.value));
                out.textContent = `${bar.value}${suffix}`;
            });
            return finish([bar, out]);
        }

        // unit === 'str': composers, because a free-text CSS field is an
        // injection surface. Each one round-trips its structured shape.
        if (key === 'height') {
            const number = document.createElement('input');
            number.type = 'number';
            number.setAttribute('aria-label', `${label} — number`);
            const unit = document.createElement('select');
            unit.setAttribute('aria-label', `${label} — unit`);
            for (const unitName of ['px', '%', 'rem', 'em', 'vw', 'vh', 'auto']) unit.append(domOption(unitName, unitName));
            const match = /^(-?\d{1,4})(px|%|rem|em|vw|vh)$/.exec(String(stored || ''));
            if (stored === 'auto') { number.value = ''; unit.value = 'auto'; number.disabled = true; }
            else if (match) { number.value = match[1]; unit.value = match[2]; }
            unit.addEventListener('change', () => {
                number.disabled = unit.value === 'auto';
                if (unit.value === 'auto') commit('auto');
                else if (number.value !== '') commit(`${number.value}${unit.value}`);
            });
            number.addEventListener('input', () => {
                if (number.value !== '' && unit.value !== 'auto') commit(`${number.value}${unit.value}`);
            });
            const pair = document.createElement('span');
            pair.className = 'studio-context-compose';
            pair.append(number, unit);
            return finish([pair]);
        }

        if (key === 'box_shadow') {
            const storedText = String(stored || '');
            const has = spec.pattern.test(storedText);
            const inset = has && storedText.startsWith('inset ');
            const numbers = has
                ? storedText.replace(/^inset /, '').replace(/ #[0-9A-Fa-f]{6}$/, '').split(' ').map((token) => parseInt(token, 10))
                : [0, 4, 12, 0];
            const hex = has ? storedText.match(/#[0-9A-Fa-f]{6}/)[0] : '#000000';
            const pair = document.createElement('span');
            pair.className = 'studio-context-compose';
            const field = (title, value) => {
                const input = document.createElement('input');
                input.type = 'number';
                input.value = String(value);
                input.title = title;
                input.setAttribute('aria-label', `${label} — ${title}`);
                input.addEventListener('input', compose);
                return input;
            };
            const x = field('X', numbers[0] ?? 0);
            const y = field('Y', numbers[1] ?? 0);
            const blur = field('Blur', numbers[2] ?? 0);
            const spread = field('Spread', numbers[3] ?? 0);
            const color = document.createElement('input');
            color.type = 'color';
            color.value = hex;
            color.setAttribute('aria-label', `${label} — color`);
            color.addEventListener('input', compose);
            const insetBox = document.createElement('input');
            insetBox.type = 'checkbox';
            insetBox.checked = inset;
            insetBox.title = 'Inset shadow';
            insetBox.setAttribute('aria-label', `${label} — inset`);
            insetBox.addEventListener('change', compose);
            function compose() {
                const px = (input) => `${Math.max(-999, Math.min(999, Number(input.value) || 0))}px`;
                commit(`${insetBox.checked ? 'inset ' : ''}${px(x)} ${px(y)} ${px(blur)} ${px(spread)} ${color.value}`);
            }
            pair.append(x, y, blur, spread, color, insetBox);
            return finish([pair]);
        }

        if (key === 'transform') {
            const storedText = String(stored || '');
            const grab = (fn) => {
                const match = new RegExp(`${fn}\\((-?[\\d.]+)px, (-?[\\d.]+)px\\)`).exec(storedText);
                return match ? [Number(match[1]), Number(match[2])] : [0, 0];
            };
            const translate = grab('translate');
            const scaleMatch = /scale\((-?[\d.]+)\)/.exec(storedText);
            const rotateMatch = /rotate\((-?[\d.]+)deg\)/.exec(storedText);
            const pair = document.createElement('span');
            pair.className = 'studio-context-compose';
            const field = (title, value) => {
                const input = document.createElement('input');
                input.type = 'number';
                input.step = 'any';
                input.value = String(value);
                input.title = title;
                input.setAttribute('aria-label', `${label} — ${title}`);
                input.addEventListener('input', compose);
                return input;
            };
            const tx = field('X', translate[0]);
            const ty = field('Y', translate[1]);
            const scale = field('Scale', scaleMatch ? Number(scaleMatch[1]) : 1);
            const rotate = field('Rotate', rotateMatch ? Number(rotateMatch[1]) : 0);
            function compose() {
                const parts = [];
                const x = Number(tx.value) || 0;
                const y = Number(ty.value) || 0;
                if (x || y) parts.push(`translate(${x}px, ${y}px)`);
                const s = Number(scale.value);
                if (s && s !== 1) parts.push(`scale(${s})`);
                const r = Number(rotate.value);
                if (r) parts.push(`rotate(${r}deg)`);
                commit(parts.join(' ') || null);
            }
            pair.append(tx, ty, scale, rotate);
            return finish([pair]);
        }

        if (key === 'filter') {
            const match = /blur\((-?[\d.]+)px\)/.exec(String(stored || ''));
            const pair = document.createElement('span');
            pair.className = 'studio-context-compose';
            const blur = document.createElement('input');
            blur.type = 'number';
            blur.min = '0';
            blur.value = match ? match[1] : '0';
            blur.title = 'Blur (px)';
            blur.setAttribute('aria-label', `${label} — blur`);
            blur.addEventListener('input', () => {
                const amount = Number(blur.value) || 0;
                commit(amount > 0 ? `blur(${amount}px)` : null);
            });
            const suffix = document.createElement('output');
            suffix.textContent = 'px';
            pair.append(blur, suffix);
            return finish([pair]);
        }

        if (key === 'grid_column' || key === 'grid_row') {
            // Two numbers: the line, and optionally a span — the two shapes
            // the drag writer and the server pattern both accept.
            const storedText = String(stored || '');
            const match = /^(-?\d{1,2})(?:\s*\/\s*span\s+(-?\d{1,2}))?$/.exec(storedText);
            const pair = document.createElement('span');
            pair.className = 'studio-context-compose';
            const line = document.createElement('input');
            line.type = 'number';
            line.min = '-12';
            line.max = '24';
            line.value = match ? match[1] : '';
            line.title = 'Line';
            line.setAttribute('aria-label', `${label} — line`);
            const cover = document.createElement('input');
            cover.type = 'number';
            cover.min = '1';
            cover.max = '12';
            cover.value = match?.[2] ?? '';
            cover.title = 'Span';
            cover.setAttribute('aria-label', `${label} — span`);
            const compose = () => {
                if (line.value === '') { commit(null); return; }
                commit(cover.value ? `${line.value} / span ${cover.value}` : String(Number(line.value)));
            };
            line.addEventListener('input', compose);
            cover.addEventListener('input', compose);
            pair.append(line, cover);
            return finish([pair]);
        }

        return finish([]);
    };

    // The capability box: one section per module the selected node actually
    // exposes. Flex/grid sub-controls appear only under a flex/grid computed
    // display, so the inspector never offers a property that cannot apply.
    // The other half of the width mirror: the status-bar device buttons own
    // the preview size (theme-studio.js), and clicking one enters that width
    // layer here — so what the frame shows and which override is being
    // written are always the same width. The listener sits on the document
    // because the status bar lives outside the form.
    const DEVICE_LABELS = { mobile: 'Mobile', tablet: 'Tablet', desktop: 'Desktop' };
    const previewDeviceButtons = () => [...document.querySelectorAll('[data-studio-preview-device]')];
    const setPreviewDevice = (device) => {
        const button = previewDeviceButtons()
            .find((item) => item.dataset.studioPreviewDevice === device);
        // theme-studio flips aria-pressed inside its own click handler; only
        // ask for a change when the button is not already the active device.
        if (button && button.getAttribute('aria-pressed') !== 'true') button.click();
    };
    // The editing-layer switcher: which map under the node the capability
    // controls write into. State and breakpoint are mutually exclusive (the
    // stored model has no combined row); the breakpoint selector is mirrored
    // with the status bar in both directions.
    const editLayers = (path) => {
        const row = document.createElement('div');
        row.className = 'studio-context-layers';

        const stateLabel = document.createElement('span');
        stateLabel.textContent = 'State';
        const states = document.createElement('select');
        states.setAttribute('aria-label', 'Edit state');
        states.append(domOption('Default', ''));
        for (const state of STATE_ORDER) states.append(domOption(state.replace('_', '-'), state));
        states.value = editState;
        states.addEventListener('change', () => {
            editState = states.value;
            if (editState) editBreakpoint = ''; // one layer at a time
            rerenderCaps();
        });

        const sizeLabel = document.createElement('span');
        sizeLabel.textContent = 'Width';
        const sizes = document.createElement('select');
        sizes.setAttribute('aria-label', 'Edit width');
        sizes.append(domOption('Base', ''));
        for (const device of BREAKPOINT_ORDER) sizes.append(domOption(DEVICE_LABELS[device], device));
        sizes.value = editBreakpoint;
        sizes.addEventListener('change', () => {
            editBreakpoint = sizes.value;
            if (editBreakpoint) editState = '';
            if (editBreakpoint) setPreviewDevice(editBreakpoint);
            rerenderCaps();
        });

        row.append(stateLabel, states, sizeLabel, sizes);

        // Per-context reset: wipe the whole row this switcher is editing so
        // the layer below shows through again. Only offered inside a layer
        // that actually holds values — base props reset per key as before.
        if ((editState || editBreakpoint) && Object.keys(activeRow(path)).length) {
            const wipe = document.createElement('button');
            wipe.type = 'button';
            wipe.className = 'studio-context-style-clear studio-context-layer-clear';
            wipe.textContent = 'Clear this layer';
            wipe.title = 'Removes every value in this layer so the layer below shows through';
            wipe.addEventListener('click', () => {
                const nodes = readNodes();
                const [bucket, name] = layerBucket();
                if (name && nodes[path]?.[bucket]) {
                    delete nodes[path][bucket][name];
                    pruneNode(nodes, path);
                    writeNodes(nodes);
                }
                rerenderCaps();
            });
            row.append(wipe);
        }
        return row;
    };
    // The status bar is outside the form: a device click there enters that
    // width layer here (and leaves any state layer — one layer at a time).
    document.addEventListener('click', (event) => {
        const button = event.target?.closest?.('[data-studio-preview-device]');
        if (!button) return;
        const device = button.dataset.studioPreviewDevice;
        editBreakpoint = device in BREAKPOINTS ? device : '';
        if (editBreakpoint) editState = '';
        rerenderCaps();
    });

    const capabilitySections = (path) => {
        if (!path || !selected) return null;
        let style = null;
        try { style = doc?.defaultView?.getComputedStyle(selected); } catch { style = null; }
        const display = style?.display || '';
        const flexy = /flex|grid/.test(display);
        const gridy = /grid/.test(display);
        const positioned = (style?.position || 'static') !== 'static';
        const props = activeRow(path);
        const box = document.createElement('details');
        box.className = 'studio-context-style-box studio-context-caps';
        box.open = true;
        const summary = document.createElement('summary');
        summary.textContent = 'Node properties';
        box.append(summary);
        const body = document.createElement('div');
        body.className = 'studio-context-caps-body';
        body.append(editLayers(path));
        // Every module collapses; only the first one opens, so a node with
        // twelve capabilities does not fill the panel by default.
        let firstModule = true;
        for (const cap of CAPABILITIES) {
            if (!cap.when(selected)) continue;
            const keys = cap.keys.filter(([key]) => {
                if (cap.positionKeys?.includes(key)) return positioned;
                if (cap.gridKeys?.includes(key)) return gridy;
                if (cap.flexKeys?.includes(key)) return flexy;
                return true;
            });
            if (!keys.length) continue;
            const section = document.createElement('details');
            section.className = 'studio-context-cap';
            section.dataset.cap = cap.id;
            section.open = firstModule;
            firstModule = false;
            const head = document.createElement('summary');
            head.className = 'studio-context-cap-title';
            head.textContent = cap.label;
            const grid = document.createElement('div');
            grid.className = 'studio-context-style-grid';
            for (const [key, controlLabel] of keys) grid.append(capControl(path, key, controlLabel, props));
            section.append(head, grid);
            body.append(section);
        }
        box.append(body);
        return box;
    };
    // A display change flips which flex/grid controls are meaningful, so the
    // box rebuilds itself in place (choice selects hold no typing focus worth
    // preserving; range drags never trigger this path).
    const rerenderCaps = () => {
        const old = contextFields?.querySelector('.studio-context-caps');
        if (!old || !identity?.path) return;
        const fresh = capabilitySections(identity.path);
        if (fresh) old.replaceWith(fresh);
    };
    // Selection breadcrumb: the path chain of the selected node, root first.
    // Selection itself already survives a reload through the URL hash, so the
    // breadcrumb simply rebuilds from it.
    const buildCrumb = () => {
        if (!crumbHost) return;
        crumbHost.replaceChildren();
        const chain = pathChain(selected).reverse();
        if (chain.length < 2) { crumbHost.hidden = true; return; }
        const parts = (element) => (element.dataset.studioPath || '').split('.');
        chain.forEach((element, index) => {
            if (index) {
                const sep = document.createElement('span');
                sep.className = 'studio-context-crumb-sep';
                sep.textContent = '/';
                sep.setAttribute('aria-hidden', 'true');
                crumbHost.append(sep);
            }
            const segments = parts(element);
            let name = segments[segments.length - 1] || leafLabel(element);
            if (/^[0-9]+$/.test(name)) name = segments.slice(-2).join('.');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'studio-context-crumb-item';
            button.textContent = name;
            button.title = element.dataset.studioPath || name;
            button.addEventListener('click', () => selectFromTree(element));
            crumbHost.append(button);
        });
        crumbHost.hidden = false;
    };

    const isListField = (field) => Boolean(field.querySelector('[data-studio-list]'));

    // A canvas path is a schema path in the common case, but list items and
    // grouped elements address a container instead of a leaf (`…hero.buttons.0`
    // is a row of `…hero.buttons`). Resolve most-specific-first so a row opens
    // the repeater that owns it, and only fall back to a prefix match.
    const resolve = (path) => {
        const exact = fields.filter((field) => field.dataset.studioField === path);
        if (exact.length) return { fields: exact };

        const parts = path.split('.');
        for (let i = parts.length - 1; i > 0; i -= 1) {
            const candidate = parts.slice(0, i).join('.');
            const owner = fields.find((field) => field.dataset.studioField === candidate);
            if (!owner) continue;
            if (isListField(owner)) return { fields: [owner], row: parts[i] };
            // A grouped container (nav cta, a heading split in two cells).
            return { fields: [owner] };
        }

        return { fields: fields.filter((field) => field.dataset.studioField.startsWith(path + '.')) };
    };
    const highlightRow = (listField, row) => {
        const rows = [...listField.querySelectorAll('[data-list-row]')];
        const index = Number(row);
        rows.forEach((node, i) => {
            node.classList.toggle('is-context-row', i === index);
        });
        rows[index]?.scrollIntoView?.({ block: 'nearest' });
        return index;
    };
    // Row operations for a list adopted by the panel, driven through the list
    // factory so add/duplicate/move/remove all go down the same path as the
    // inline buttons (and therefore notify the live preview).
    const rowActions = (listField, index) => {
        // studioList is published by the list factory on the [data-studio-list]
        // element itself, which is nested inside the field wrapper.
        const listEl = listField.querySelector('[data-studio-list]');
        const api = listEl?.studioList;
        const bar = document.createElement('div');
        bar.className = 'studio-context-row-actions';
        if (!api) return bar;
        const max = Number(listEl.dataset.max || 20);
        const rows = () => [...listField.querySelectorAll('[data-list-row]')];
        const at = () => rows()[index];
        const after = (next) => {
            // Deleting the last row leaves nothing to scope to: unhide the
            // (now empty) list so its Add control is reachable again.
            if (!next) listField.classList.remove('is-context-scoped');
            const pos = next && rows().indexOf(next);
            if (pos >= 0) index = pos;
            highlightRow(listField, index);
            build();
        };
        const control = (label, icon, run, disabled) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'studio-context-row-action';
            button.title = label;
            button.setAttribute('aria-label', label);
            button.disabled = Boolean(disabled);
            button.innerHTML = `<i class="fas ${icon}" aria-hidden="true"></i>`;
            button.addEventListener('click', run);
            return button;
        };
        const build = () => {
            bar.replaceChildren();
            const row = at();
            const list = rows();
            const full = list.length >= max;
            bar.append(
                control('Add item', 'fa-plus', () => after(api.add()), full),
                control('Duplicate item', 'fa-clone', () => after(api.duplicate(row)), !row || full),
                control('Move up', 'fa-sort-up', () => { api.move(row, -1); highlightRow(listField, index); build(); }, !row || index === 0),
                control('Move down', 'fa-sort-down', () => { api.move(row, 1); highlightRow(listField, index); build(); }, !row || index === list.length - 1),
                control('Delete item', 'fa-trash', () => { api.remove(row); after(rows()[Math.min(index, rows().length - 1)]); }, !row),
            );
            return bar;
        };
        build();
        return bar;
    };
    const closeContext = () => {
        contextFields?.querySelectorAll('.is-context-row').forEach((node) => node.classList.remove('is-context-row'));
        for (const [field, home] of homes) {
            field.classList.remove('is-context-scoped');
            if (field.isConnected) home.parent.insertBefore(field, home.next);
        }
        homes.clear();
        // Row-action bars, capability sections and any other panel-only
        // scaffolding never move back into the form, so they are dropped
        // outright.
        contextFields?.replaceChildren();
        if (crumbHost) { crumbHost.replaceChildren(); crumbHost.hidden = true; }
        if (contextPanel) contextPanel.hidden = true;
    };
    const openContext = (path, label, fallback) => {
        closeContext();
        if (!contextPanel || !contextFields || !path) return false;
        const { fields: matched, row } = resolve(path);

        for (const field of matched) {
            homes.set(field, { parent: field.parentElement, next: field.nextElementSibling });
            contextFields.append(field);
        }
        if (row !== undefined && isListField(matched[0])) {
            const index = highlightRow(matched[0], row);
            // Scope the adopted list to the selected row: the siblings belong
            // to other elements, and showing them here is just noise.
            matched[0].classList.add('is-context-scoped');
            contextFields.prepend(rowActions(matched[0], index));
        }
        // Node-local values live in the canvas document, so the capability box
        // opens for every path — it replaces the old override-row style box.
        const caps = capabilitySections(path);
        if (caps) contextFields.append(caps);
        buildCrumb();

        contextTitle.textContent = label || path;
        contextNote.textContent = matched.length === 1 && isListField(matched[0])
            ? (row !== undefined
                ? 'Only the selected row is shown; its siblings stay hidden while it is selected.'
                : 'Edit, reorder, duplicate or delete this list right here.')
            : matched.length
                ? 'Every change updates the live preview instantly.'
                : 'Edit this node’s properties here; changes apply to the canvas live.';
        contextPanel.hidden = false;
        openInspector();
        // The panel sits at the top of the scrolling dock; `nearest` only
        // scrolls when it is out of sight, so selecting in the canvas never
        // yanks the page around, but a deep scroll down the form still brings
        // the fields back into view.
        contextPanel.scrollIntoView?.({ block: 'nearest' });
        return Boolean(caps) || matched.length > 0;
    };
    const measure = () => {
        metrics.replaceChildren();
        if (!selected?.isConnected) return;
        const element = visual(selected);
        const rect = element.getBoundingClientRect();
        const css = doc.defaultView.getComputedStyle(element);
        const values = [
            ['Width × Height (px)', `${Math.round(rect.width)} × ${Math.round(rect.height)}`],
            ['Text color', css.color], ['Background', css.backgroundColor],
            ['Font size', css.fontSize], ['Font', css.fontFamily],
            ['Font weight', css.fontWeight], ['Radius', css.borderRadius],
            ['Padding', css.padding],
        ];
        for (const [label, value] of values) {
            const cell = document.createElement('div');
            const term = document.createElement('dt');
            const description = document.createElement('dd');
            term.textContent = label;
            description.textContent = value || '—';
            description.dir = 'ltr';
            cell.append(term, description);
            metrics.append(cell);
        }
        paintHandles();
    };
    // --- Layers --------------------------------------------------------------
    // Sections with their named children (everything carrying a
    // data-studio-path), built from the live frame so the tree always matches
    // the rendered markup. A click selects the node; the row mirrors the
    // current selection and dims elements that an override row has hidden.
    const sectionLabel = (key) => {
        const control = [...form.querySelectorAll('[data-section-row]')]
            .find((row) => row.querySelector('input[type="checkbox"]')?.value === key);
        return control?.textContent.trim() || tab(key)?.getAttribute?.('aria-label') || key;
    };
    const leafLabel = (node) => (node.getAttribute('aria-label')
        || node.getAttribute('alt')
        || node.textContent
        || node.tagName).trim().slice(0, 48) || node.tagName;
    const selectFromTree = (element) => {
        element.scrollIntoView?.({ block: 'center' });
        select(element);
    };

    // --- Node flags (lock & visibility) --------------------------------------
    // The canvas document rides the form as one opaque JSON textarea (the same
    // blob the server cleans through StudioStyles::cleanNodes), so the editor
    // reads and rewrites that field instead of keeping a second document.
    // Lock is editor-only: it changes what a canvas click may select and never
    // what the public page renders. Hide is real — it emits display:none — so
    // the frame gets a small mirror stylesheet while the tree row stays put as
    // the way back.
    const canvasField = () => form.querySelector('[data-studio-canvas-nodes]');
    // The same charset the server whitelists: it is what makes a path safe to
    // interpolate into an attribute selector below.
    const NODE_ID = /^[a-z0-9_.]+$/;
    let nodeFlags = new Map();
    const readNodes = () => {
        try { return JSON.parse(canvasField()?.value || '') || {}; } catch { return {}; }
    };
    const syncFlags = () => {
        nodeFlags = new Map(
            Object.entries(readNodes())
                .filter(([id, node]) => NODE_ID.test(id) && node && typeof node === 'object')
                .map(([id, node]) => [id, (node.flags && typeof node.flags === 'object') ? node.flags : {}]),
        );
    };
    // A node materializes only on a real edit and is forgotten when nothing is
    // left in it — the same sparse-diff rule the server applies on save.
    const ensureNode = (nodes, path) => {
        const node = nodes[path] ?? (nodes[path] = {});
        for (const key of ['props', 'states', 'breakpoints', 'flags']) {
            if (!node[key] || typeof node[key] !== 'object') node[key] = {};
        }
        return node;
    };
    const pruneNode = (nodes, path) => {
        const node = nodes[path];
        if (node && ['props', 'states', 'breakpoints', 'flags']
            .every((key) => !Object.keys(node[key]).length)) {
            delete nodes[path];
        }
    };
    const writeNodes = (nodes) => {
        const field = canvasField();
        if (field) {
            field.value = Object.keys(nodes).length ? JSON.stringify(nodes) : '';
            // Programmatic value writes fire no event; the unsaved-changes
            // indicator and the live pipeline both hang off form input, so the
            // textarea announces its own change exactly like a typed field.
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
        syncFlags();
        paintNodeStyles();
        paintLayers();
        paintHandles();
    };
    // The editor's live stylesheet: a hidden flag becomes display:none, the
    // three value layers their declarations — the same cascade the server
    // emits on save (local → pseudo-class → @media narrow→wide), injected
    // here without a reload. Values are re-validated on the way out, so even
    // a hand-edited textarea cannot inject CSS into the frame.
    const paintNodeStyles = () => {
        if (!doc) return;
        const body = (row) => Object.entries(row)
            .filter(([key, value]) => validNodeValue(key, value))
            .map(([key, value]) => nodeDeclaration(key, value))
            .join('');
        const rules = [];
        for (const [id, node] of Object.entries(readNodes())) {
            if (!NODE_ID.test(id) || !node || typeof node !== 'object') continue;
            const selector = `[data-studio-path="${id}"]`;
            if (node.flags?.hidden === true) {
                rules.push(`${selector}{display:none;}`);
                continue;
            }
            const local = body(node.props && typeof node.props === 'object' ? node.props : {});
            if (local) rules.push(`${selector}{${local}}`);
            for (const state of STATE_ORDER) {
                const row = node.states?.[state];
                const css = row && typeof row === 'object' ? body(row) : '';
                if (css) rules.push(`${selector}${STATE_SELECTORS[state]}{${css}}`);
            }
            for (const device of BREAKPOINT_ORDER) {
                const row = node.breakpoints?.[device];
                const css = row && typeof row === 'object' ? body(row) : '';
                if (css) rules.push(`@media ${BREAKPOINTS[device]}{${selector}{${css}}}`);
            }
        }
        const css = rules.join('');
        let style = doc.getElementById('studio-editor-node-styles');
        if (!css) { style?.remove(); return; }
        if (!style) {
            style = doc.createElement('style');
            style.id = 'studio-editor-node-styles';
            doc.head.append(style);
        }
        style.textContent = css;
    };
    const setFlag = (path, flag, on) => {
        const nodes = readNodes();
        const node = ensureNode(nodes, path);
        if (on) node.flags[flag] = true;
        else delete node.flags[flag];
        pruneNode(nodes, path);
        writeNodes(nodes);
    };
    // One serialize/dispatch/paint for a whole batch — a flex drop assigns
    // `order` to every sibling, and that must not stringify the document once
    // per child. Entries are [path, key, value]; null/'' clears the key.
    // Every write lands in the ACTIVE layer: base props, or the state /
    // breakpoint row the switcher is editing. Reset deletes from that same
    // row, so clearing a hover value never touches the base one (and the
    // empty row is forgotten the moment its last key goes).
    const setNodeProps = (entries) => {
        const usable = entries.filter(([path, key, value]) => {
            if (!NODE_KEYS[key] || !path) return false;
            const clearing = value === null || value === undefined || value === '';
            return clearing || validNodeValue(key, value);
        });
        if (!usable.length) return false;
        const nodes = readNodes();
        const [bucket, name] = layerBucket();
        for (const [path, key, value] of usable) {
            const node = ensureNode(nodes, path);
            const target = name ? (node[bucket][name] || (node[bucket][name] = {})) : node.props;
            const clearing = value === null || value === undefined || value === '';
            if (clearing) delete target[key];
            else if (typeof value === 'object') target[key] = { token: value.token };
            else target[key] = ['px', '%', 'pct', 'ipx', 'i'].includes(NODE_KEYS[key].unit) ? Number(value) : String(value);
            if (name && !Object.keys(node[bucket][name]).length) delete node[bucket][name];
            pruneNode(nodes, path);
        }
        writeNodes(nodes);
        return true;
    };
    const setNodeProp = (path, key, value) => setNodeProps([[path, key, value]]);
    const isLocked = (element) => nodeFlags.get(element.dataset.studioPath)?.locked === true;
    // Every path-bearing ancestor of a hit, nearest first.
    const pathChain = (from) => {
        const chain = [];
        for (let node = from; node?.nodeType === 1; node = node.parentElement) {
            if (node.hasAttribute?.('data-studio-path')) chain.push(node);
        }
        return chain;
    };
    // The hit-test walk. A lock cuts its whole subtree out of canvas selection:
    // the first candidate above the outermost lock wins. `found:false` means no
    // path was involved (the caller falls back to generic tags); `found:true`
    // with a null target means a lock owns the whole chain — the caller must
    // clear rather than fall back, or the locked element becomes selectable
    // anyway through the tag selector.
    const hitTarget = (from) => {
        const chain = pathChain(from);
        if (!chain.length) return { found: false, target: null };
        let cut = -1;
        chain.forEach((node, index) => { if (isLocked(node)) cut = index; });
        return { found: true, target: chain[cut + 1] ?? null };
    };
    syncFlags();

    // --- Direct manipulation -------------------------------------------------
    // Resize handles, a move surface, alignment guides and a live readout,
    // drawn inside the frame so every coordinate is the frame's own. Writes go
    // through setNodeProps → paintNodeStyles: no reload, the same path the
    // inspector uses. The route follows the element's real layout model and
    // NEVER rewrites it — nothing here converts a node to position:absolute:
    //   positioned (abs/fixed/relative) → top/left
    //   flex or grid child              → order for the whole set / grid cell
    //   flow child of a list row        → one list move, applied on release
    //   anything else                   → not movable (nothing would persist)
    const HANDLE_NAMES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];
    let drag = null;

    const handleRect = () => {
        if (!doc || interactive()) return null;
        if (!selected?.isConnected || !selected.dataset.studioPath) return null;
        if (isLocked(selected)) return null;
        if (nodeFlags.get(selected.dataset.studioPath)?.hidden === true) return null;
        const rect = selected.getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) return null;
        return rect;
    };

    const paintHandles = () => {
        if (!doc) return;
        const layer = doc.getElementById('studio-handle-layer');
        if (!layer) return;
        const rect = handleRect();
        layer.hidden = !rect;
        if (!rect) return;
        const style = doc.defaultView.getComputedStyle(selected);
        const offset = /absolute|fixed|relative/.test(style.position);
        const resizable = !/inline/.test(style.display);
        // Handles that pull the top/left edge only track the pointer when the
        // node is positioned; in flow those edges are pinned by the document.
        const rear = /^(nw|n|ne|w|sw)$/;
        for (const handle of layer.querySelectorAll('[data-handle]')) {
            const name = handle.dataset.handle;
            handle.hidden = !resizable || (rear.test(name) && !offset);
            const midX = rect.left + rect.width / 2;
            const midY = rect.top + rect.height / 2;
            const spots = {
                nw: [rect.left, rect.top], n: [midX, rect.top], ne: [rect.right, rect.top],
                e: [rect.right, midY], se: [rect.right, rect.bottom], s: [midX, rect.bottom],
                sw: [rect.left, rect.bottom], w: [rect.left, midY],
            };
            const [x, y] = spots[name];
            handle.style.left = `${x - 5}px`;
            handle.style.top = `${y - 5}px`;
        }
        const badge = layer.querySelector('.studio-handle-badge');
        if (badge) {
            badge.style.left = `${rect.left}px`;
            badge.style.top = `${rect.top - 4}px`;
            if (!drag) badge.textContent = `${Math.round(rect.width)}×${Math.round(rect.height)}`;
        }
    };

    const dragModel = () => {
        const style = doc.defaultView.getComputedStyle(selected);
        if (/absolute|fixed|relative/.test(style.position)) return 'offset';
        const parentStyle = doc.defaultView.getComputedStyle(selected.parentElement);
        if (/flex/.test(parentStyle.display)) return 'flex';
        if (/grid/.test(parentStyle.display)) return 'grid';
        return 'flow';
    };
    // The list that owns this path as a row — the only persistable form of
    // flow reordering (arbitrary flow siblings have no storage in the model).
    const listTarget = () => {
        if (!identity?.path) return null;
        const { fields: matched, row } = resolve(identity.path);
        if (row === undefined || !matched.length || !isListField(matched[0])) return null;
        const listField = matched[0];
        const listRow = [...listField.querySelectorAll('[data-list-row]')][Number(row)];
        const api = listField.querySelector('[data-studio-list]')?.studioList;
        if (!listRow || !api) return null;
        return { listField, listRow, api, row: Number(row) };
    };
    const startResize = (event, name) => {
        const rect = selected.getBoundingClientRect();
        const parentRect = selected.parentElement.getBoundingClientRect();
        const style = doc.defaultView.getComputedStyle(selected);
        const offset = /absolute|fixed|relative/.test(style.position);
        const parseLength = (value) => {
            const number = parseInt(value, 10);
            return Number.isFinite(number) ? number : null;
        };
        const top0 = offset ? (parseLength(style.top) ?? rect.top - parentRect.top) : null;
        const left0 = offset ? (parseLength(style.left) ?? rect.left - parentRect.left) : null;
        drag = {
            kind: 'resize', name, started: false,
            x0: event.clientX, y0: event.clientY,
            rect, parentRect, offset, top0, left0,
            path: selected.dataset.studioPath,
        };
    };
    const startMove = (event) => {
        const rect = selected.getBoundingClientRect();
        const parent = selected.parentElement;
        const parentRect = parent.getBoundingClientRect();
        const model = dragModel();
        if (model === 'flow' && !listTarget()) return;
        const style = doc.defaultView.getComputedStyle(selected);
        const offset = /absolute|fixed|relative/.test(style.position);
        const parseLength = (value) => {
            const number = parseInt(value, 10);
            return Number.isFinite(number) ? number : null;
        };
        const siblings = [...parent.children]
            .filter((element) => element !== selected && element.nodeType === 1)
            .map((element) => ({ element, rect: element.getBoundingClientRect() }));
        drag = {
            kind: 'move', model, started: false,
            x0: event.clientX, y0: event.clientY,
            rect, parentRect, siblings,
            path: selected.dataset.studioPath,
            top0: offset ? (parseLength(style.top) ?? rect.top - parentRect.top) : null,
            left0: offset ? (parseLength(style.left) ?? rect.left - parentRect.left) : null,
            direction: doc.defaultView.getComputedStyle(parent).flexDirection || 'row',
            list: model === 'flow' ? listTarget() : null,
        };
    };
    // Target index among the sibling rects snapshotted at drag start — the
    // pointer against their visual centers, so reverse axes resolve the same way.
    const targetIndex = (coord, axis) => {
        const key = axis === 'x' ? 'left' : 'top';
        const size = axis === 'x' ? 'width' : 'height';
        return drag.siblings.filter((entry) => entry.rect[key] + entry.rect[size] / 2 < coord).length;
    };
    const drawGuides = (predicted) => {
        const layer = doc.getElementById('studio-handle-layer');
        if (!layer) return;
        const vertical = layer.querySelector('.studio-guide-v');
        const horizontal = layer.querySelector('.studio-guide-h');
        const band = 4;
        const statics = [drag.parentRect, ...drag.siblings.map((entry) => entry.rect)];
        let vx = null;
        let hy = null;
        for (const rect of statics) {
            for (const edge of [rect.left, rect.left + rect.width / 2, rect.right]) {
                for (const mine of [predicted.left, predicted.left + predicted.width / 2, predicted.right]) {
                    if (Math.abs(edge - mine) <= band) vx = edge;
                }
            }
            for (const edge of [rect.top, rect.top + rect.height / 2, rect.bottom]) {
                for (const mine of [predicted.top, predicted.top + predicted.height / 2, predicted.bottom]) {
                    if (Math.abs(edge - mine) <= band) hy = edge;
                }
            }
        }
        if (vx !== null && vertical) {
            vertical.hidden = false;
            vertical.style.left = `${vx}px`;
            vertical.style.top = `${Math.min(predicted.top, drag.parentRect.top)}px`;
            vertical.style.height = `${Math.max(predicted.bottom, drag.parentRect.bottom) - Math.min(predicted.top, drag.parentRect.top)}px`;
        } else if (vertical) vertical.hidden = true;
        if (hy !== null && horizontal) {
            horizontal.hidden = false;
            horizontal.style.top = `${hy}px`;
            horizontal.style.left = `${Math.min(predicted.left, drag.parentRect.left)}px`;
            horizontal.style.width = `${Math.max(predicted.right, drag.parentRect.right) - Math.min(predicted.left, drag.parentRect.left)}px`;
        } else if (horizontal) horizontal.hidden = true;
    };
    const clearGuides = () => {
        const layer = doc?.getElementById('studio-handle-layer');
        const vertical = layer?.querySelector('.studio-guide-v');
        const horizontal = layer?.querySelector('.studio-guide-h');
        if (vertical) vertical.hidden = true;
        if (horizontal) horizontal.hidden = true;
    };
    const hintBadge = (text) => {
        const badge = doc?.getElementById('studio-handle-layer')?.querySelector('.studio-handle-badge');
        if (!badge) return;
        const rect = handleRect();
        badge.textContent = rect ? `${Math.round(rect.width)}×${Math.round(rect.height)} · ${text}` : text;
    };
    const applyResize = (x, y) => {
        const dx = x - drag.x0;
        const dy = y - drag.y0;
        let width = drag.rect.width;
        let height = drag.rect.height;
        let topDelta = 0;
        let leftDelta = 0;
        if (drag.name.includes('e')) width += dx;
        if (drag.name.includes('w')) { width -= dx; leftDelta = dx; }
        if (drag.name.includes('s')) height += dy;
        if (drag.name.includes('n')) { height -= dy; topDelta = dy; }
        width = Math.max(8, width);
        height = Math.max(0, height);
        const parentWidth = drag.parentRect.width || 1;
        const widthPercent = Math.min(NODE_KEYS.width.max, Math.max(NODE_KEYS.width.min, Math.round(width / parentWidth * 100)));
        const entries = [
            [drag.path, 'width', widthPercent],
            [drag.path, 'height', `${Math.round(height)}px`],
        ];
        if (drag.offset && drag.left0 !== null) entries.push([drag.path, 'left', drag.left0 + leftDelta]);
        if (drag.offset && drag.top0 !== null) entries.push([drag.path, 'top', drag.top0 + topDelta]);
        setNodeProps(entries);
        hintBadge('Resize');
        paintHandles();
    };
    const applyMove = (x, y) => {
        const dx = x - drag.x0;
        const dy = y - drag.y0;
        if (drag.model === 'offset') {
            setNodeProps([
                [drag.path, 'left', Math.round(drag.left0 + dx)],
                [drag.path, 'top', Math.round(drag.top0 + dy)],
            ]);
            drawGuides({
                left: drag.rect.left + dx, top: drag.rect.top + dy,
                width: drag.rect.width, height: drag.rect.height,
                right: drag.rect.left + dx + drag.rect.width, bottom: drag.rect.top + dy + drag.rect.height,
            });
            hintBadge(`${Math.round(drag.left0 + dx)}, ${Math.round(drag.top0 + dy)}`);
            paintHandles();
            return;
        }
        if (drag.model === 'flex') {
            const mainAxis = /column/.test(drag.direction) ? 'y' : 'x';
            const index = targetIndex(mainAxis === 'x' ? x : y, mainAxis);
            // Explicit order for every child: order is a sort key, so one
            // node's value cannot land it in the middle of implicit zeros.
            const ordered = drag.siblings.slice();
            ordered.splice(index, 0, { element: selected, rect: drag.rect, path: drag.path });
            const entries = ordered
                .map((entry, position) => [entry.path ?? entry.element.dataset.studioPath, 'order', position])
                .filter(([path]) => path);
            setNodeProps(entries);
            clearGuides();
            hintBadge(`Position ${index + 1}`);
            paintHandles();
            return;
        }
        if (drag.model === 'grid') {
            const column = Math.min(12, targetIndex(x, 'x') + 1);
            const row = Math.min(24, targetIndex(y, 'y') + 1);
            setNodeProps([
                [drag.path, 'grid_column', String(column)],
                [drag.path, 'grid_row', String(row)],
            ]);
            clearGuides();
            hintBadge(`Cell ${column}×${row}`);
            paintHandles();
            return;
        }
        // flow: preview the landing index; the list move itself happens on release.
        const rects = drag.siblings.map((entry) => entry.rect);
        const spreadX = rects.length ? Math.max(...rects.map((r) => r.right)) - Math.min(...rects.map((r) => r.left)) : 0;
        const spreadY = rects.length ? Math.max(...rects.map((r) => r.bottom)) - Math.min(...rects.map((r) => r.top)) : 0;
        const index = targetIndex(spreadX > spreadY ? x : y, spreadX > spreadY ? 'x' : 'y');
        drag.targetIndex = index;
        clearGuides();
        hintBadge(`Position ${index + 1}`);
    };
    const onDragMove = (event) => {
        if (!drag) return;
        if (!selected?.isConnected) { drag = null; return; }
        if (!drag.started) {
            if (Math.abs(event.clientX - drag.x0) < 3 && Math.abs(event.clientY - drag.y0) < 3) return;
            drag.started = true;
        }
        event.preventDefault();
        if (drag.kind === 'resize') applyResize(event.clientX, event.clientY);
        else applyMove(event.clientX, event.clientY);
    };
    const onDragEnd = () => {
        if (!drag) return;
        const current = drag;
        drag = null;
        clearGuides();
        if (!current.started) { paintHandles(); return; }
        if (current.kind === 'move' && current.model === 'flow' && current.list) {
            const target = current.targetIndex ?? current.list.row;
            const delta = target - current.list.row;
            for (let step = 0; step < Math.abs(delta); step += 1) {
                current.list.api.move(current.listRow, delta < 0 ? -1 : 1);
            }
            return; // the list's live pipeline reloads the frame
        }
        paintHandles();
    };
    // Per-frame wiring: created with the inspection layer, one set of
    // listeners per document (an old frame's listeners die with it).
    const initFrameHandles = () => {
        const layer = doc.createElement('div');
        layer.id = 'studio-handle-layer';
        layer.hidden = true;
        for (const name of HANDLE_NAMES) {
            const handle = doc.createElement('div');
            handle.className = `studio-handle studio-handle--${name}`;
            handle.dataset.handle = name;
            layer.append(handle);
        }
        const badge = doc.createElement('div');
        badge.className = 'studio-handle-badge';
        layer.append(badge);
        for (const guide of ['v', 'h']) {
            const line = doc.createElement('div');
            line.className = `studio-guide-${guide}`;
            line.hidden = true;
            layer.append(line);
        }
        doc.body.append(layer);
        layer.addEventListener('pointerdown', (event) => {
            const handle = event.target.closest?.('[data-handle]');
            if (!handle || interactive() || !selected?.isConnected) return;
            event.preventDefault();
            event.stopPropagation();
            startResize(event, handle.dataset.handle);
        });
        doc.addEventListener('pointerdown', (event) => {
            if (interactive() || event.button !== 0 || drag) return;
            if (!selected?.isConnected || !selected.contains(event.target)) return;
            if (!selected.dataset.studioPath || isLocked(selected)) return;
            startMove(event);
        }, true);
        doc.addEventListener('pointermove', onDragMove);
        doc.addEventListener('pointerup', onDragEnd);
        doc.addEventListener('pointercancel', onDragEnd);
        doc.addEventListener('scroll', () => { if (!drag) paintHandles(); }, true);
        paintHandles();
    };

    // A small type glyph per row, derived from the tag, so two rows with the
    // same text ("Button") are still tellable apart at a glance.
    // Every glyph here must already be in resources/icons/catalog.json: the
    // icon audit fails the build on a used-but-unregistered icon, and a tree
    // glyph is not worth a catalog entry.
    const typeIcon = (node) => {
        const map = {
            h1: 'fa-heading', h2: 'fa-heading', h3: 'fa-heading', h4: 'fa-heading', h5: 'fa-heading',
            p: 'fa-font', a: 'fa-link', img: 'fa-image', button: 'fa-square-plus',
            section: 'fa-inbox', main: 'fa-inbox', aside: 'fa-inbox', article: 'fa-inbox',
            nav: 'fa-bars', header: 'fa-bars', footer: 'fa-shoe-prints',
            ul: 'fa-list', ol: 'fa-list', li: 'fa-list',
            span: 'fa-circle', strong: 'fa-bold', blockquote: 'fa-quote-left', time: 'fa-clock',
            form: 'fa-clipboard-list', input: 'fa-keyboard', svg: 'fa-shapes',
        };
        return map[node.tagName.toLowerCase()] || 'fa-puzzle-piece';
    };

    // One tree row: `depth` dashed guides that step the row right, an optional
    // expand/collapse control (spans, not buttons, so the row count stays one
    // "button" per addressable element — the tree's public contract), a type
    // glyph, and the label. `path` is set only for addressable elements, which
    // is what paintLayers keys the selection off.
    const layerRow = (label, icon, { path = null, branch = false, depth = 0 } = {}) => {
        const li = document.createElement('li');
        li.className = 'studio-layer';

        // One guide per ancestor level; the deepest one also draws the
        // horizontal tick that connects the row to its parent's line.
        for (let level = 0; level < depth; level += 1) {
            const guide = document.createElement('span');
            guide.className = 'studio-layer-guide';
            if (level === depth - 1) guide.classList.add('is-connect');
            guide.setAttribute('aria-hidden', 'true');
            li.append(guide);
        }

        if (branch) {
            const toggle = document.createElement('span');
            toggle.className = 'studio-layer-toggle';
            toggle.setAttribute('role', 'button');
            toggle.setAttribute('tabindex', '0');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', 'Expand or collapse layer');
            toggle.innerHTML = '<i class="fas fa-chevron-down" aria-hidden="true"></i>';
            const flip = () => {
                const open = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!open));
                li.classList.toggle('is-collapsed', open);
            };
            toggle.addEventListener('click', (event) => { event.stopPropagation(); flip(); });
            toggle.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); flip(); }
            });
            li.append(toggle);
        } else {
            const spacer = document.createElement('span');
            spacer.className = 'studio-layer-spacer';
            spacer.setAttribute('aria-hidden', 'true');
            li.append(spacer);
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'studio-layer-name';
        if (path) button.dataset.leafPath = path;
        const glyph = document.createElement('i');
        glyph.className = `fas ${icon}`;
        glyph.setAttribute('aria-hidden', 'true');
        // Appended as separate nodes so textContent stays exactly the label.
        button.append(glyph, document.createTextNode(label));
        li.append(button);

        // Lock & visibility: spans with role=button, like the expand toggle, so
        // the tree's public contract stays exactly one real <button> per
        // addressable element. Only path rows get them — section visibility
        // belongs to public.landing.sections, not the node map.
        if (path) {
            const flags = document.createElement('span');
            flags.className = 'studio-layer-flags';
            const control = (iconName, labelText, flag) => {
                const span = document.createElement('span');
                span.className = 'studio-layer-flag';
                span.dataset.flag = flag;
                span.setAttribute('role', 'button');
                span.setAttribute('tabindex', '0');
                span.setAttribute('aria-pressed', 'false');
                span.setAttribute('aria-label', labelText);
                span.innerHTML = `<i class="fas ${iconName}" aria-hidden="true"></i>`;
                const flip = () => setFlag(path, flag, span.getAttribute('aria-pressed') !== 'true');
                span.addEventListener('click', (event) => { event.stopPropagation(); flip(); });
                span.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); flip(); }
                });
                flags.append(span);
            };
            control('fa-lock', 'Lock element', 'locked');
            control('fa-eye', 'Hide element', 'hidden');
            li.append(flags);
        }

        return { li, button };
    };

    // The layer tree is built from the live frame's real DOM, so it always
    // matches what is rendered. Sections come from the markers; inside each,
    // every `[data-studio-path]` element nests under its nearest path-bearing
    // ancestor, giving arbitrary depth instead of the old two levels.
    const buildLayers = () => {
        layers.replaceChildren();

        doc.querySelectorAll('[data-studio-section]').forEach((section) => {
            const nodes = [...section.querySelectorAll('[data-studio-path]')]
                .filter((node) => node.closest('[data-studio-section]') === section);

            // The nesting is decided up front: document order puts every
            // ancestor before its descendants, so one pass fixes both the
            // parent of each row and whether that row needs a toggle.
            const known = new Set(nodes);
            const ownerOf = (node) => {
                let parent = node.parentElement;
                while (parent && parent !== section) {
                    if (known.has(parent)) return parent;
                    parent = parent.parentElement;
                }
                return null;
            };
            const owners = new Map(nodes.map((node) => [node, ownerOf(node)]));
            const children = new Map(nodes.map((node) => [node, 0]));
            for (const owner of owners.values()) {
                if (owner) children.set(owner, children.get(owner) + 1);
            }
            // Depth drives the row guides: a top-level element is 1 (under its
            // section row), and each nesting level adds one more. Document
            // order puts every owner before its descendants, so one forward
            // pass resolves the whole chain.
            const depthOf = new Map();
            for (const node of nodes) {
                const owner = owners.get(node);
                depthOf.set(node, owner ? (depthOf.get(owner) ?? 0) + 1 : 1);
            }

            const { li: sectionRow, button: sectionButton } = layerRow(
                sectionLabel(section.dataset.studioSection), typeIcon(section), { branch: nodes.length > 0 },
            );
            sectionButton.addEventListener('click', () => selectFromTree(section));

            const root = document.createElement('ul');
            root.className = 'studio-layer-children';
            // node -> { li, ul } ; a row's <ul> is created the first time a
            // child is appended to it, so an ancestor is always in place.
            const placed = new Map();
            const ulFor = (owner) => {
                if (!owner) return root;
                const entry = placed.get(owner);
                if (!entry.ul) {
                    entry.ul = document.createElement('ul');
                    entry.ul.className = 'studio-layer-children';
                    entry.li.append(entry.ul);
                }
                return entry.ul;
            };

            for (const node of nodes) {
                const { li, button } = layerRow(
                    leafLabel(node), typeIcon(node),
                    { path: node.dataset.studioPath, branch: children.get(node) > 0, depth: depthOf.get(node) ?? 1 },
                );
                button.addEventListener('click', () => selectFromTree(node));
                placed.set(node, { li, ul: null });
                ulFor(owners.get(node)).append(li);
            }

            if (nodes.length) sectionRow.append(root);
            layers.append(sectionRow);
        });

        paintLayers();
    };
    const paintLayers = () => {
        const path = identity?.path;
        layers.querySelectorAll('[data-leaf-path]').forEach((button) => {
            const leaf = button.dataset.leafPath;
            button.setAttribute('aria-current', String(leaf === path));
            const flags = nodeFlags.get(leaf) || {};
            const row = button.closest('li');
            row.classList.toggle('is-locked', flags.locked === true);
            row.classList.toggle('is-hidden', flags.hidden === true);
            row.querySelectorAll('.studio-layer-flag').forEach((control) => {
                control.setAttribute('aria-pressed', String(flags[control.dataset.flag] === true));
            });
        });
    };
    const clear = () => {
        selected?.classList.remove('studio-inspected-object');
        selected = null;
        identity = null;
        observer?.disconnect();
        metrics.replaceChildren();
        shortcuts.replaceChildren();
        closeContext();
        syncHash();
        title.textContent = 'No element selected';
        scope.textContent = 'Select an element on the canvas to see its properties.';
        paintHandles();
    };
    const sectionOf = (element) => element.closest('[data-studio-section]')?.dataset.studioSection;
    const select = (element, blockRow = null) => {
        if (!element) { clear(); return; }
        openInspector();
        selected?.classList.remove('studio-inspected-object');
        selected = element;
        if (!interactive()) selected.classList.add('studio-inspected-object');
        const section = sectionOf(element);
        const block = element.closest('[data-studio-block]');
        // Path identity is stable across a preview reload (it is schema data,
        // not a DOM position), so it is preferred whenever the markup carries
        // it; the section/index pair stays as the fallback for older frames.
        const pathElement = element.closest('[data-studio-path]');
        const path = pathElement?.dataset.studioPath;
        identity = block ? { block: block.dataset.studioBlock } : path ? { path } : {
            section,
            index: [...(element.closest('[data-studio-section]') || doc.body).querySelectorAll('*')].indexOf(element),
        };
        title.textContent = (element.getAttribute('aria-label') || element.getAttribute('alt') || element.textContent || element.tagName).trim().slice(0, 72);
        shortcuts.replaceChildren();
        syncHash();
        paintLayers();
        if (block) {
            scope.textContent = 'These styles apply only to this block. The measurements above are computed on the canvas.';
            closeContext();
            shortcut('Edit this block', () => {
                tab('blocks')?.click();
                blockRow?.scrollIntoView?.({ block: 'nearest' });
            });
        } else {
            const opened = openContext(path, title.textContent, section);
            scope.textContent = opened
                ? 'This element is edited directly in the properties panel; changes apply to the canvas live.'
                : 'The sizes and colors above are computed. Section content is edited in the form below and shared style via the shortcuts; shared style affects other elements too.';
            const prefix = section === 'nav' || section === 'footer' ? `public.${section}.` : `public.landing.${section}.`;
            const relevant = section && !opened ? fields.filter((field) => field.dataset.studioField.startsWith(prefix)) : [];
            relevant.forEach((field) => shortcut(field.querySelector('.studio-field-label')?.textContent.trim() || field.dataset.studioField, () => openField(field)));
            if (relevant.length) {
                tab(relevant[0].closest('[data-studio-group]')?.dataset.studioGroup)?.click();
            }
            for (const [key, label] of [['colors', 'Shared colors'], ['typography', 'Shared fonts'], ['shape', 'Shared radius'], ['buttons', 'Shared button style']]) {
                if (tab(key)) shortcut(label, () => tab(key).click());
            }
            if (!opened && !relevant.length) scope.textContent = 'This element has no content controls of its own. The properties above are read-only; the shared style tools affect the whole template.';
        }
        observer?.disconnect();
        if (doc.defaultView.ResizeObserver) {
            observer = new doc.defaultView.ResizeObserver(measure);
            observer.observe(visual(element));
        }
        measure();
    };
    const blockElement = (id) => [...(doc?.querySelectorAll('[data-studio-block]') || [])].find((node) => node.dataset.studioBlock === id);
    form.addEventListener('studio:block-selected', (event) => {
        if (!event.detail.row) {
            if (identity?.block) clear();
            return;
        }
        const element = blockElement(event.detail.id) || blockElement(event.detail.row.dataset.legacyId);
        if (element) select(element, event.detail.row);
        else {
            clear();
            identity = { block: event.detail.id };
            title.textContent = 'Selected block';
            scope.textContent = 'Block properties are edited in the form below; sizes appear once the preview is ready.';
        }
    });
    const paintTools = () => {
        root.querySelectorAll('[data-workspace-tool]').forEach((button) => {
            button.setAttribute('aria-pressed', String((button.dataset.workspaceTool === 'interact') === interactive()));
        });
        doc?.documentElement.classList.toggle('studio-canvas-editing', !interactive());
        selected?.classList.toggle('studio-inspected-object', !interactive());
        paintHandles();
    };
    const setTool = (tool) => {
        activeTool = tool === 'interact' ? 'interact' : 'select';
        paintTools();
        form.dispatchEvent(new CustomEvent('studio:tool', { detail: { tool: activeTool } }));
    };
    root.querySelectorAll('[data-workspace-tool]').forEach((button) => button.addEventListener('click', () => {
        setTool(button.dataset.workspaceTool);
    }));
    form.querySelector('[data-context-clear]')?.addEventListener('click', () => clear());
    const proxies = [...root.querySelectorAll('[data-workspace-insert],[data-workspace-action]')];
    const targetOf = (button) => button.hasAttribute('data-workspace-insert')
        ? form.querySelector(`[data-block-insert="${button.dataset.workspaceInsert}"]`)
        : form.querySelector(`[data-block-${button.dataset.workspaceAction}]`);
    const syncActions = () => proxies.forEach((button) => {
        const target = targetOf(button);
        button.disabled = !target || target.disabled;
    });
    proxies.forEach((button) => button.addEventListener('click', () => {
        if (button.disabled) return;
        tab('blocks')?.click();
        if (interactive()) setTool('select');
        targetOf(button)?.click();
        syncActions();
    }));
    form.addEventListener('studio:block-state', syncActions);
    form.addEventListener('input', () => { measure(); syncActions(); });
    form.addEventListener('studio:frame-ready', (event) => {
        const frame = event.detail.frame;
        if (frame.hasAttribute?.('aria-hidden')) return;
        let next;
        try { next = frame.contentDocument; } catch { return; }
        if (!next?.body) return;
        doc = next;
        // Inert markers preserve section sibling selectors on the public page.
        doc.querySelectorAll('[data-studio-section-marker]').forEach((marker) => {
            const section = marker.nextElementSibling;
            if (section && !section.matches('[data-studio-section-marker]')) {
                section.dataset.studioSection = marker.dataset.studioSectionMarker;
            }
        });
        if (!doc.getElementById('studio-inspection-style')) {
            const style = doc.createElement('style');
            style.id = 'studio-inspection-style';
            style.textContent = '.studio-canvas-editing .studio-inspected-object{outline:2px solid #0D99FF!important;outline-offset:3px}.studio-canvas-editing [data-studio-section] :is(a,button,h1,h2,h3,p,img,.lp-card,[data-studio-path]):hover{outline:1px dashed #0D99FF;cursor:crosshair}.studio-canvas-editing .reveal{opacity:1!important;transform:none!important}.studio-hover-label{position:absolute;z-index:2147483647;max-inline-size:220px;padding:2px 8px;border-radius:4px;background:#0D99FF;color:#fff;font:11px/1.6 inherit;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;pointer-events:none}'
                // Direct-manipulation chrome: the handle layer floats over the
                // frame (pointer-events pass through except on handles), with a
                // monospace readout badge and two magenta alignment guides.
                + '#studio-handle-layer{position:fixed;inset:0;pointer-events:none;z-index:2147483645}'
                + '#studio-handle-layer[hidden]{display:none}'
                + '.studio-handle{position:absolute;width:10px;height:10px;background:#fff;border:1.5px solid #0D99FF;border-radius:2px;pointer-events:auto}'
                + '.studio-handle[hidden]{display:none}'
                + '.studio-handle--nw,.studio-handle--se{cursor:nwse-resize}'
                + '.studio-handle--ne,.studio-handle--sw{cursor:nesw-resize}'
                + '.studio-handle--n,.studio-handle--s{cursor:ns-resize}'
                + '.studio-handle--e,.studio-handle--w{cursor:ew-resize}'
                + '.studio-handle-badge{position:absolute;background:#0D99FF;color:#fff;font:11px/1.4 ui-monospace,monospace;padding:2px 6px;border-radius:3px;white-space:nowrap;transform:translateY(-100%);pointer-events:none}'
                + '.studio-guide-v{position:absolute;width:1px;background:#ff2d95;pointer-events:none}'
                + '.studio-guide-h{position:absolute;height:1px;background:#ff2d95;pointer-events:none}'
                + '.studio-guide-v[hidden],.studio-guide-h[hidden]{display:none}';
            doc.head.append(style);
            // One handle layer per document: pointer wiring lives with it and
            // dies with the frame it belongs to.
            initFrameHandles();
            // Hover chip: names the element under the pointer (like Figma's
            // hover overlay), so users know what a click would select before
            // they commit. Fixed-position, so it must be re-pinned on scroll.
            const hoverChip = doc.createElement('div');
            hoverChip.className = 'studio-hover-label';
            hoverChip.hidden = true;
            doc.body.append(hoverChip);
            let hoverTarget = null;
            const hoverElement = (node) => {
                // The chip must name exactly what a click would select: for a
                // path chain that is the hit-test walk (a lock owns the chain →
                // null → the chip hides), otherwise the section/block fallback.
                const hit = hitTarget(node);
                if (hit.found) return hit.target;
                return node?.closest?.('[data-studio-section],[data-studio-block]');
            };
            const placeChip = () => {
                if (!hoverTarget?.isConnected || interactive()) { hoverChip.hidden = true; return; }
                const rect = hoverTarget.getBoundingClientRect();
                hoverChip.textContent = leafLabel(hoverTarget);
                hoverChip.style.insetInlineStart = Math.max(0, rect.left) + 'px';
                hoverChip.style.top = Math.max(0, rect.top - 24) + 'px';
                hoverChip.hidden = false;
            };
            doc.addEventListener('mouseover', (event) => {
                if (interactive()) return;
                hoverTarget = hoverElement(event.target);
                placeChip();
            }, true);
            doc.addEventListener('scroll', placeChip, true);
            form.addEventListener('studio:tool', () => { hoverTarget = null; placeChip(); });
            doc.addEventListener('click', (click) => {
                if (interactive()) return;
                click.preventDefault();
                click.stopImmediatePropagation();
                if (click.target.closest('[data-studio-block]')) return;
                // Selection runs through the hit-test walk: a lock stops it at
                // the subtree boundary (clear when a lock owns the whole chain),
                // and only a pathless click falls back to generic tags.
                const hit = hitTarget(click.target);
                const element = hit.found
                    ? hit.target
                    : click.target.closest('a,button,h1,h2,h3,h4,p,img,input,label,.lp-card,section,nav,footer')
                        || click.target;
                form.querySelector('[data-block-editor]')?.studioEditor?.select(null);
                select(element);
            }, true);
            doc.addEventListener('submit', (submit) => {
                if (!interactive()) { submit.preventDefault(); submit.stopImmediatePropagation(); }
            }, true);
            doc.addEventListener('keydown', (key) => {
                if (interactive()) return;
                if (key.key === 'Escape') {
                    form.querySelector('[data-block-editor]')?.studioEditor?.select(null);
                    clear();
                    return;
                }
                // Delete removes the selected list row, Ctrl/Cmd+D duplicates it.
                // Guarded against typing contexts, and both act through the list
                // factory so the live preview is notified like every other edit.
                if (key.key !== 'Delete' && !(key.key === 'd' && (key.ctrlKey || key.metaKey))) return;
                if (key.target?.closest('input,textarea,select,[contenteditable]')) return;
                const path = identity?.path;
                if (!path) return;
                const { fields: matched, row } = resolve(path);
                if (row === undefined || !matched.length || !isListField(matched[0])) return;
                const api = matched[0].querySelector('[data-studio-list]')?.studioList;
                const target = [...matched[0].querySelectorAll('[data-list-row]')][Number(row)];
                if (!api || !target) return;
                key.preventDefault();
                key.stopImmediatePropagation();
                const label = contextTitle.textContent;
                if (key.key === 'Delete') {
                    api.remove(target);
                    clear();
                } else {
                    api.duplicate(target);
                    openContext(path, label);
                }
            });
        }
        paintNodeStyles();
        buildLayers();
        paintHandles();
        if (identity?.block) {
            const element = blockElement(identity.block);
            if (element) select(element); else clear();
        } else if (identity?.path) {
            const element = elementAtPath(identity.path);
            if (element) select(element); else clear();
        } else if (identity?.section) {
            const section = [...doc.querySelectorAll('[data-studio-section]')].find((node) => node.dataset.studioSection === identity.section);
            select(identity.index === -1 ? section : section?.querySelectorAll('*')[identity.index]);
        } else {
            // Nothing selected yet: honour a deep link, which is how a reloaded
            // or shared studio link reopens the element it was pointing at.
            const linked = hashPath();
            if (linked && !selectPath(linked, { scroll: false })) selectPath(linked);
        }
        paintTools();
    });
    const elementAtPath = (path) => [...(doc?.querySelectorAll('[data-studio-path]') || [])]
        .find((node) => node.dataset.studioPath === path);
    // Deep link: the selected element is mirrored into the URL hash, so a
    // reload or a shared link reopens the same element. Only replacement is
    // used, so selecting through the canvas never grows the history stack.
    const selectPath = (path, { scroll = true } = {}) => {
        const element = elementAtPath(path);
        if (!element) return false;
        select(element);
        if (scroll) element.scrollIntoView?.({ block: 'center' });
        return true;
    };
    // The URL helpers are guarded: the module can run where `location` or
    // `history` is unavailable (a sandboxed frame, or a bare test document).
    const view = () => globalThis.location;
    const navigate = () => globalThis.history;
    const syncHash = () => {
        const loc = view();
        if (!loc) return;
        const path = identity?.path;
        const next = path ? `#studio=${encodeURIComponent(path)}` : loc.pathname + loc.search;
        try { navigate()?.replaceState(null, '', next); } catch { /* file: or sandboxed */ }
    };
    const hashPath = () => {
        const hash = view()?.hash;
        if (!hash) return null;
        const match = /(?:^|[#&])studio=([^&]+)/.exec(hash);
        try { return match ? decodeURIComponent(match[1]) : null; } catch { return null; }
    };
    paintTools();
    syncActions();

    // --- Inspector collapse --------------------------------------------------
    // The metrics/shortcuts/layers block is read-mostly metadata; collapsed by
    // default it stops crowding out the editable fields above it. The choice
    // persists so the panel comes back exactly as the user left it.
    const collapseButton = inspector.querySelector('[data-object-collapse]');
    if (collapseButton) {
        let open;
        try { open = localStorage.getItem('studio.inspector') === '1'; } catch { open = false; }
        const paintCollapse = () => {
            inspector.classList.toggle('is-collapsed', !open);
            collapseButton.setAttribute('aria-expanded', String(open));
        };
        collapseButton.addEventListener('click', () => {
            open = !open;
            try { localStorage.setItem('studio.inspector', open ? '1' : '0'); } catch { /* private mode */ }
            paintCollapse();
        });
        paintCollapse();
    }

    // --- Unsaved-changes indicator -----------------------------------------
    // The live preview persists nothing: every edit lands in the session
    // preview layer, so "saved" means one of the three submit buttons was
    // used. A baseline snapshot of the form's own values is enough to tell the
    // two apart, and it is taken from the rendered form rather than tracked by
    // handlers, so no edit path can escape it.
    const dirty = document.querySelector('[data-studio-dirty]');
    const dirtyText = document.querySelector('[data-studio-dirty-text]');
    const saveJump = document.querySelector('[data-studio-save-jump]');
    const saveConfirm = document.querySelector('[data-save-confirm]');
    const serial = () => JSON.stringify([...form.elements]
        .filter((el) => el.name && el.type !== 'file')
        .map((el) => [el.name, el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value]));
    let baseline = serial();
    let dirtyCount = 0;
    const paintDirty = () => {
        const now = serial();
        const isDirty = now !== baseline;
        if (dirty) dirty.hidden = !isDirty;
        if (saveJump) saveJump.hidden = !isDirty;
        if (isDirty) {
            dirtyCount += 1;
            if (dirtyText) dirtyText.textContent = `Unsaved (${dirtyCount} changes)`;
        } else {
            dirtyCount = 0;
            if (dirtyText) dirtyText.textContent = 'Unsaved';
        }
    };
    form.addEventListener('input', paintDirty);
    form.addEventListener('change', paintDirty);
    // A successful save re-renders the page, so a form submit resets the
    // baseline: anything still pending afterwards is a genuinely new edit.
    form.addEventListener('submit', () => {
        baseline = serial();
        paintDirty();
        saveConfirm?.close?.();
    });
    // One save action: the button raises a confirmation dialog (the scope
    // menu with preview/me options is gone — publishing is the only path,
    // and it says so before anything is posted).
    const openSaveConfirm = () => {
        if (!saveConfirm) return;
        if (typeof saveConfirm.showModal === 'function') saveConfirm.showModal();
        else saveConfirm.setAttribute('open', '');
    };
    saveJump?.addEventListener('click', openSaveConfirm);
    saveConfirm?.querySelector('[data-save-confirm-cancel]')?.addEventListener('click', () => {
        saveConfirm.close?.();
        saveConfirm.removeAttribute?.('open');
    });
    // The scope-carrying submit button lives inside the dialog; submitting
    // through it closes the dialog (submit fires before the navigation).
    saveConfirm?.querySelector('button[type="submit"]')?.addEventListener('click', () => {
        saveConfirm.close?.();
    });
    paintDirty();

    // --- Status-bar error indicator ----------------------------------------
    // Two sources merge here: the server-rendered seed list (a failed save
    // re-render) and live-preview 422s (theme-studio.js dispatches
    // `studio:errors` on the form). The icon + count sit in the status bar;
    // clicking opens a popover whose rows jump to the offending field.
    const errorsToggle = document.querySelector('[data-studio-errors-toggle]');
    const errorsCount = document.querySelector('[data-studio-errors-count]');
    let serverErrors = [...document.querySelectorAll('[data-studio-error-seed] [data-message]')]
        .map((node) => ({ path: node.dataset.path || null, message: node.dataset.message }))
        .filter((entry) => entry.message);
    let liveErrors = [];

    const popover = document.createElement('ul');
    popover.className = 'studio-errors-popover';
    popover.setAttribute('aria-label', 'Validation errors');

    const closePopover = () => {
        popover.classList.remove('is-open');
        errorsToggle?.setAttribute('aria-expanded', 'false');
    };
    const paintErrors = () => {
        const entries = [...serverErrors, ...liveErrors];
        const shown = entries.length > 0;
        if (errorsToggle) {
            errorsToggle.hidden = !shown;
            errorsToggle.title = shown ? entries[0].message : '';
        }
        if (errorsCount) errorsCount.textContent = String(entries.length);
        if (!shown) closePopover();
    };
    const fillPopover = () => {
        popover.replaceChildren();
        const entries = [...serverErrors, ...liveErrors];
        for (const entry of entries) {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = entry.message;
            button.addEventListener('click', () => {
                closePopover();
                if (entry.path) openField(fieldAt(entry.path));
            });
            item.append(button);
            popover.append(item);
        }
    };
    if (errorsToggle) {
        errorsToggle.parentElement.style.position ||= 'relative';
        errorsToggle.parentElement.append(popover);
        errorsToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const opening = !popover.classList.contains('is-open');
            if (opening) fillPopover();
            popover.classList.toggle('is-open', opening);
            errorsToggle.setAttribute('aria-expanded', String(opening));
        });
        document.addEventListener('click', (event) => {
            if (!popover.classList.contains('is-open')) return;
            if (popover.contains(event.target) || errorsToggle.contains(event.target)) return;
            closePopover();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closePopover();
        });
    }
    form.addEventListener('studio:errors', (event) => {
        liveErrors = Array.isArray(event.detail?.errors) ? event.detail.errors : [];
        paintErrors();
    });
    // A full save re-renders the page, so a submit means the server seed is
    // about to be replaced; drop the live list with it.
    form.addEventListener('submit', () => { liveErrors = []; paintErrors(); });
    paintErrors();

    return { select, clear, measure, openContext, closeContext, selectPath };
}
