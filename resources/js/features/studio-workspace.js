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
    const layers = inspector.querySelector('[data-page-layers]');
    const toggle = document.querySelector('[data-studio-interact]');
    const fields = [...form.querySelectorAll('[data-studio-field]')];
    let doc = null;
    let selected = null;
    let identity = null;
    let observer = null;
    const interactive = () => Boolean(toggle?.checked);
    const fieldAt = (path) => fields.find((field) => field.dataset.studioField === path);
    const tab = (key) => [...root.querySelectorAll('[data-studio-tab]')].find((button) => button.dataset.studioTab === key);
    const openField = (field) => {
        if (!field) return;
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

    // --- Per-element style -------------------------------------------------
    // "Recolour this one button" needs storage the shared tokens cannot give:
    // an override row addressed by the element's own path. Rows live in the
    // `public.landing.overrides` list (a normal schema list, so validation and
    // tenant scoping apply unchanged) and are emitted as one stylesheet on the
    // public page. The controls below are a view over those rows: they write
    // into the row's real inputs, which stay the single source of truth.
    const STYLE_KEYS = [
        { key: 'background', label: 'پس‌زمینه', kind: 'color' },
        { key: 'color', label: 'رنگ متن', kind: 'color' },
        { key: 'radius', label: 'گردی گوشه', kind: 'range', min: 0, max: 128, unit: 'px' },
        { key: 'padding', label: 'فاصله داخلی', kind: 'range', min: 0, max: 128, unit: 'px' },
        { key: 'width', label: 'عرض', kind: 'range', min: 10, max: 100, unit: '%' },
        { key: 'font_scale', label: 'مقیاس قلم', kind: 'range', min: 50, max: 400, unit: '%' },
    ];
    const overridesField = fields.find((field) => field.dataset.studioField === 'public.landing.overrides');
    const overridesList = overridesField?.querySelector('[data-studio-list]');
    const rowInput = (row, key) => row.querySelector(`[name$="[${key}]"]`);
    // Reading never materializes a row: a path the tenant has not styled yet
    // has no row, and creating one on mere selection would fill the stored
    // layer (and the list's 64-row budget) with empty path-only rows.
    const findOverride = (path) => {
        if (!overridesList || !path) return null;
        return [...overridesList.querySelectorAll('[data-list-row]')]
            .find((row) => rowInput(row, 'path')?.value === path) ?? null;
    };
    const canAddOverride = () => {
        if (!overridesList?.studioList) return false;
        return overridesList.querySelectorAll('[data-list-row]').length < Number(overridesList.dataset.max || 0);
    };
    // Written only when a control actually changes: the row is created on that
    // first edit, addressed by the element's path.
    const writeOverride = (path, key, value) => {
        let row = findOverride(path);
        if (!row) {
            if (!canAddOverride()) return null;
            row = overridesList.studioList.add();
            if (!row) return null;
            const pathInput = rowInput(row, 'path');
            if (pathInput) pathInput.value = path;
        }
        const input = rowInput(row, key);
        if (!input) return null;
        input.value = value;
        // The existing live pipeline classifies this path as structural, so
        // this is what makes the override visible in the canvas.
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return row;
    };
    const styleControl = (path, spec) => {
        const wrap = document.createElement('label');
        wrap.className = 'studio-context-style';
        const name = document.createElement('span');
        name.textContent = spec.label;
        const row = findOverride(path);
        const stored = row ? rowInput(row, spec.key)?.value ?? '' : '';
        if (spec.kind === 'color') {
            const picker = document.createElement('input');
            picker.type = 'color';
            picker.value = /^#[0-9a-f]{6}$/i.test(stored) ? stored : '#000000';
            const clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'studio-context-style-clear';
            clear.textContent = 'پیش‌فرض';
            clear.title = 'برداشتن این سبک و بازگشت به ظاهر قالب';
            picker.addEventListener('input', () => writeOverride(path, spec.key, picker.value));
            clear.addEventListener('click', () => writeOverride(path, spec.key, ''));
            wrap.append(name, picker, clear);
            return wrap;
        }
        const bar = document.createElement('input');
        bar.type = 'range';
        bar.min = String(spec.min);
        bar.max = String(spec.max);
        bar.step = '1';
        const out = document.createElement('output');
        const current = parseFloat(stored);
        bar.value = String(Number.isNaN(current) ? Math.round((spec.min + spec.max) / 2) : current);
        out.textContent = stored === '' ? 'پیش‌فرض' : `${stored}${spec.unit}`;
        bar.addEventListener('input', () => {
            writeOverride(path, spec.key, bar.value);
            out.textContent = `${bar.value}${spec.unit}`;
        });
        wrap.append(name, bar, out);
        return wrap;
    };
    const elementStyleSection = (path) => {
        if (!overridesField || !path) return null;
        // Offered only while a new row could still be created, unless this
        // element already owns one (which its controls then edit in place).
        if (!findOverride(path) && !canAddOverride()) return null;
        const details = document.createElement('details');
        details.className = 'studio-context-style-box';
        const summary = document.createElement('summary');
        summary.textContent = 'سبک همین عنصر';
        const grid = document.createElement('div');
        grid.className = 'studio-context-style-grid';
        for (const spec of STYLE_KEYS) grid.append(styleControl(path, spec));
        // Open by default: the user just picked an element to style it, so
        // hiding the controls behind a second click is friction, not tidiness.
        details.open = true;
        details.append(summary, grid);
        return details;
    };
    const contextPanel = form.querySelector('[data-context-panel]');
    const contextTitle = form.querySelector('[data-context-title]');
    const contextNote = form.querySelector('[data-context-note]');
    const contextFields = form.querySelector('[data-context-fields]');
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
                control('افزودن مورد', 'fa-plus', () => after(api.add()), full),
                control('تکثیر مورد', 'fa-clone', () => after(api.duplicate(row)), !row || full),
                control('انتقال به بالا', 'fa-sort-up', () => { api.move(row, -1); highlightRow(listField, index); build(); }, !row || index === 0),
                control('انتقال به پایین', 'fa-sort-down', () => { api.move(row, 1); highlightRow(listField, index); build(); }, !row || index === list.length - 1),
                control('حذف مورد', 'fa-trash', () => { api.remove(row); after(rows()[Math.min(index, rows().length - 1)]); }, !row),
            );
            return bar;
        };
        build();
        return bar;
    };
    const closeContext = () => {
        contextFields?.querySelectorAll('.is-context-row').forEach((node) => node.classList.remove('is-context-row'));
        for (const [field, home] of homes) {
            if (field.isConnected) home.parent.insertBefore(field, home.next);
        }
        homes.clear();
        // Row-action bars and any other panel-only scaffolding never move back
        // into the form, so they are dropped outright.
        contextFields?.replaceChildren();
        if (contextPanel) contextPanel.hidden = true;
    };
    const openContext = (path, label, fallback) => {
        closeContext();
        if (!contextPanel || !contextFields || !path) return false;
        const { fields: matched, row } = resolve(path);
        if (!matched.length) return false;

        for (const field of matched) {
            homes.set(field, { parent: field.parentElement, next: field.nextElementSibling });
            contextFields.append(field);
        }
        if (row !== undefined && isListField(matched[0])) {
            const index = highlightRow(matched[0], row);
            contextFields.prepend(rowActions(matched[0], index));
        }
        // Per-element overrides target the element itself, so every path
        // selection offers them (the list's own rows keep their row actions).
        if (path && !matched.some(isListField)) {
            const styleBox = elementStyleSection(path);
            if (styleBox) contextFields.append(styleBox);
        }

        contextTitle.textContent = label || path;
        contextNote.textContent = matched.length === 1 && isListField(matched[0])
            ? 'این فهرست را می‌توانید همین‌جا ویرایش، جابه‌جا، تکثیر یا حذف کنید.'
            : 'با تغییر هر مقدار، پیش‌نمایش بی‌درنگ به‌روز می‌شود.';
        contextPanel.hidden = false;
        // The panel sits at the top of the scrolling dock; `nearest` only
        // scrolls when it is out of sight, so selecting in the canvas never
        // yanks the page around, but a deep scroll down the form still brings
        // the fields back into view.
        contextPanel.scrollIntoView?.({ block: 'nearest' });
        return true;
    };
    const measure = () => {
        metrics.replaceChildren();
        if (!selected?.isConnected) return;
        const element = visual(selected);
        const rect = element.getBoundingClientRect();
        const css = doc.defaultView.getComputedStyle(element);
        const values = [
            ['عرض × ارتفاع (px)', `${Math.round(rect.width)} × ${Math.round(rect.height)}`],
            ['رنگ متن', css.color], ['پس‌زمینه', css.backgroundColor],
            ['اندازه قلم', css.fontSize], ['قلم', css.fontFamily],
            ['ضخامت قلم', css.fontWeight], ['گردی', css.borderRadius],
            ['فاصله داخلی', css.padding],
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
    };
    // --- Layers --------------------------------------------------------------
    // Sections with their named children (everything carrying a
    // data-studio-path), built from the live frame so the tree always matches
    // the rendered markup. A click selects the node; the row mirrors the
    // current selection and dims elements that an override row has hidden.
    const sectionLabel = (key) => {
        const control = [...form.querySelectorAll('[data-section-row]')]
            .find((row) => row.querySelector('input[type="checkbox"]')?.value === key);
        return control?.textContent.trim() || tab(key)?.getAttribute('aria-label') || key;
    };
    const leafLabel = (node) => (node.getAttribute('aria-label')
        || node.getAttribute('alt')
        || node.textContent
        || node.tagName).trim().slice(0, 48) || node.tagName;
    const selectFromTree = (element) => {
        element.scrollIntoView?.({ block: 'center' });
        select(element);
    };
    const buildLayers = () => {
        layers.replaceChildren();
        doc.querySelectorAll('[data-studio-section]').forEach((section) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = sectionLabel(section.dataset.studioSection);
            button.addEventListener('click', () => selectFromTree(section));
            item.append(button);
            const leaves = [...section.querySelectorAll('[data-studio-path]')]
                .filter((node) => node.closest('[data-studio-section]') === section);
            if (leaves.length) {
                const childList = document.createElement('ul');
                for (const leaf of leaves) {
                    const child = document.createElement('li');
                    const childButton = document.createElement('button');
                    childButton.type = 'button';
                    childButton.dataset.leafPath = leaf.dataset.studioPath;
                    childButton.textContent = leafLabel(leaf);
                    childButton.addEventListener('click', () => selectFromTree(leaf));
                    child.append(childButton);
                    childList.append(child);
                }
                item.append(childList);
            }
            layers.append(item);
        });
        paintLayers();
    };
    const paintLayers = () => {
        const path = identity?.path;
        layers.querySelectorAll('[data-leaf-path]').forEach((button) => {
            button.setAttribute('aria-current', String(button.dataset.leafPath === path));
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
        title.textContent = 'عنصری انتخاب نشده است';
        scope.textContent = 'برای مشاهدهٔ ویژگی‌ها، عنصری را در بوم انتخاب کنید.';
    };
    const sectionOf = (element) => element.closest('[data-studio-section]')?.dataset.studioSection;
    const select = (element, blockRow = null) => {
        if (!element) { clear(); return; }
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
            scope.textContent = 'سبک‌های زیر فقط روی این بلوک اعمال می‌شوند. اندازه‌های بالا اندازهٔ محاسبه‌شده در بوم هستند.';
            closeContext();
            shortcut('ویرایش همین بلوک', () => {
                tab('blocks')?.click();
                blockRow?.scrollIntoView?.({ block: 'nearest' });
            });
        } else {
            const opened = openContext(path, title.textContent, section);
            scope.textContent = opened
                ? 'این عنصر مستقیماً در پنل ویژگی‌ها ویرایش می‌شود؛ تغییرات بی‌درنگ در بوم اعمال می‌شوند.'
                : 'اندازه‌ها و رنگ‌های بالا اطلاعات محاسبه‌شده‌اند. محتوای بخش از فرم زیر و سبک مشترک از میان‌برها ویرایش می‌شود؛ سبک مشترک روی عناصر دیگر هم اثر دارد.';
            const prefix = section === 'nav' || section === 'footer' ? `public.${section}.` : `public.landing.${section}.`;
            const relevant = section && !opened ? fields.filter((field) => field.dataset.studioField.startsWith(prefix)) : [];
            relevant.forEach((field) => shortcut(field.querySelector('.studio-field-label')?.textContent.trim() || field.dataset.studioField, () => openField(field)));
            if (relevant.length) {
                tab(relevant[0].closest('[data-studio-group]')?.dataset.studioGroup)?.click();
            }
            for (const [key, label] of [['colors', 'رنگ‌های مشترک'], ['typography', 'قلم‌های مشترک'], ['shape', 'گردی مشترک'], ['buttons', 'سبک مشترک دکمه‌ها']]) {
                if (tab(key)) shortcut(label, () => tab(key).click());
            }
            if (!opened && !relevant.length) scope.textContent = 'این عنصر کنترل محتوای مستقل ندارد. ویژگی‌های بالا فقط خواندنی هستند؛ ابزارهای سبک مشترک روی کل قالب اثر می‌گذارند.';
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
            title.textContent = 'بلوک انتخاب‌شده';
            scope.textContent = 'ویژگی‌های بلوک در فرم زیر قابل ویرایش است؛ ابعاد پس از آماده‌شدن پیش‌نمایش نمایش داده می‌شود.';
        }
    });
    const paintTools = () => {
        root.querySelectorAll('[data-workspace-tool]').forEach((button) => {
            button.setAttribute('aria-pressed', String((button.dataset.workspaceTool === 'interact') === interactive()));
        });
        doc?.documentElement.classList.toggle('studio-canvas-editing', !interactive());
        selected?.classList.toggle('studio-inspected-object', !interactive());
    };
    root.querySelectorAll('[data-workspace-tool]').forEach((button) => button.addEventListener('click', () => {
        if (!toggle) return;
        toggle.checked = button.dataset.workspaceTool === 'interact';
        toggle.dispatchEvent(new Event('change', { bubbles: true }));
    }));
    toggle?.addEventListener('change', paintTools);
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
        if (toggle?.checked) {
            toggle.checked = false;
            toggle.dispatchEvent(new Event('change', { bubbles: true }));
        }
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
            style.textContent = '.studio-canvas-editing .studio-inspected-object{outline:2px solid #4285fa!important;outline-offset:3px}.studio-canvas-editing [data-studio-section] :is(a,button,h1,h2,h3,p,img,.lp-card,[data-studio-path]):hover{outline:1px dashed #4285fa;cursor:crosshair}.studio-canvas-editing .reveal{opacity:1!important;transform:none!important}';
            doc.head.append(style);
            doc.addEventListener('click', (click) => {
                if (interactive()) return;
                click.preventDefault();
                click.stopImmediatePropagation();
                if (click.target.closest('[data-studio-block]')) return;
                const element = click.target.closest('[data-studio-path]')
                    || click.target.closest('a,button,h1,h2,h3,h4,p,img,input,label,.lp-card,section,nav,footer')
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
        buildLayers();
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
    const saveMenu = document.querySelector('[data-studio-save-menu]');
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
            if (dirtyText) dirtyText.textContent = `ذخیره‌نشده (${dirtyCount} تغییر)`;
        } else {
            dirtyCount = 0;
            if (dirtyText) dirtyText.textContent = 'ذخیره‌نشده';
        }
    };
    form.addEventListener('input', paintDirty);
    form.addEventListener('change', paintDirty);
    // A successful save re-renders the page, so a form submit resets the
    // baseline: anything still pending afterwards is a genuinely new edit.
    form.addEventListener('submit', () => { baseline = serial(); paintDirty(); });
    // Saving moved from a card at the bottom of the panel into the toolbar:
    // the save button toggles a small scope menu (preview / me / everyone)
    // whose buttons submit the studio form via the `form` attribute.
    saveJump?.addEventListener('click', () => saveMenu?.classList.toggle('is-open'));
    document.addEventListener('click', (event) => {
        if (!saveMenu?.classList.contains('is-open')) return;
        if (saveJump?.contains(event.target) || saveMenu.contains(event.target)) return;
        saveMenu.classList.remove('is-open');
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') saveMenu?.classList.remove('is-open');
    });
    saveMenu?.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.addEventListener('click', () => saveMenu.classList.remove('is-open'));
    });
    paintDirty();

    return { select, clear, measure, openContext, closeContext, selectPath };
}
