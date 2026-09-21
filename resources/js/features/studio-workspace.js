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
    const contextPanel = form.querySelector('[data-context-panel]');
    const contextTitle = form.querySelector('[data-context-title]');
    const contextNote = form.querySelector('[data-context-note]');
    const contextFields = form.querySelector('[data-context-fields]');
    const homes = new Map();
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
                control('انتقال به بالا', 'fa-arrow-up', () => { api.move(row, -1); highlightRow(listField, index); build(); }, !row || index === 0),
                control('انتقال به پایین', 'fa-arrow-down', () => { api.move(row, 1); highlightRow(listField, index); build(); }, !row || index === list.length - 1),
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

        contextTitle.textContent = label || path;
        contextNote.textContent = matched.length === 1 && isListField(matched[0])
            ? 'این فهرست را می‌توانید همین‌جا ویرایش، جابه‌جا، تکثیر یا حذف کنید.'
            : 'با تغییر هر مقدار، پیش‌نمایش بی‌درنگ به‌روز می‌شود.';
        contextPanel.hidden = false;
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
    const clear = () => {
        selected?.classList.remove('studio-inspected-object');
        selected = null;
        identity = null;
        observer?.disconnect();
        metrics.replaceChildren();
        shortcuts.replaceChildren();
        closeContext();
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
                if (!interactive() && key.key === 'Escape') {
                    form.querySelector('[data-block-editor]')?.studioEditor?.select(null);
                    clear();
                }
            });
        }
        layers.replaceChildren();
        doc.querySelectorAll('[data-studio-section]').forEach((section) => {
            const key = section.dataset.studioSection;
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            const sectionControl = [...form.querySelectorAll('[data-section-row]')].find((row) => row.querySelector('input[type="checkbox"]')?.value === key);
            button.textContent = sectionControl?.textContent.trim() || tab(key)?.getAttribute('aria-label') || key;
            button.addEventListener('click', () => {
                const element = section; 
                element.scrollIntoView?.({ block: 'center' });
                select(element);
            });
            item.append(button);
            layers.append(item);
        });
        if (identity?.block) {
            const element = blockElement(identity.block);
            if (element) select(element); else clear();
        } else if (identity?.path) {
            const element = [...(doc.querySelectorAll('[data-studio-path]') || [])]
                .find((node) => node.dataset.studioPath === identity.path);
            if (element) select(element); else clear();
        } else if (identity?.section) {
            const section = [...doc.querySelectorAll('[data-studio-section]')].find((node) => node.dataset.studioSection === identity.section);
            select(identity.index === -1 ? section : section?.querySelectorAll('*')[identity.index]);
        }
        paintTools();
    });
    paintTools();
    syncActions();
    return { select, clear, measure, openContext, closeContext };
}
