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
        identity = block ? { block: block.dataset.studioBlock } : {
            section,
            index: [...(element.closest('[data-studio-section]') || doc.body).querySelectorAll('*')].indexOf(element),
        };
        title.textContent = (element.getAttribute('aria-label') || element.getAttribute('alt') || element.textContent || element.tagName).trim().slice(0, 72);
        shortcuts.replaceChildren();
        if (block) {
            scope.textContent = 'سبک‌های زیر فقط روی این بلوک اعمال می‌شوند. اندازه‌های بالا اندازهٔ محاسبه‌شده در بوم هستند.';
            shortcut('ویرایش همین بلوک', () => {
                tab('blocks')?.click();
                blockRow?.scrollIntoView?.({ block: 'nearest' });
            });
        } else {
            scope.textContent = 'اندازه‌ها و رنگ‌های بالا اطلاعات محاسبه‌شده‌اند. محتوای بخش از فرم زیر و سبک مشترک از میان‌برها ویرایش می‌شود؛ سبک مشترک روی عناصر دیگر هم اثر دارد.';
            const prefix = section === 'nav' || section === 'footer' ? `public.${section}.` : `public.landing.${section}.`;
            const relevant = section ? fields.filter((field) => field.dataset.studioField.startsWith(prefix)) : [];
            relevant.forEach((field) => shortcut(field.querySelector('.studio-field-label')?.textContent.trim() || field.dataset.studioField, () => openField(field)));
            if (relevant.length) {
                tab(relevant[0].closest('[data-studio-group]')?.dataset.studioGroup)?.click();
            }
            for (const [key, label] of [['colors', 'رنگ‌های مشترک'], ['typography', 'قلم‌های مشترک'], ['shape', 'گردی مشترک'], ['buttons', 'سبک مشترک دکمه‌ها']]) {
                if (tab(key)) shortcut(label, () => tab(key).click());
            }
            if (!relevant.length) scope.textContent = 'این عنصر کنترل محتوای مستقل ندارد. ویژگی‌های بالا فقط خواندنی هستند؛ ابزارهای سبک مشترک روی کل قالب اثر می‌گذارند.';
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
            style.textContent = '.studio-canvas-editing .studio-inspected-object{outline:2px solid #4285fa!important;outline-offset:3px}.studio-canvas-editing [data-studio-section] :is(a,button,h1,h2,h3,p,img,.lp-card):hover{outline:1px dashed #4285fa;cursor:crosshair}.studio-canvas-editing .reveal{opacity:1!important;transform:none!important}';
            doc.head.append(style);
            doc.addEventListener('click', (click) => {
                if (interactive()) return;
                click.preventDefault();
                click.stopImmediatePropagation();
                if (click.target.closest('[data-studio-block]')) return;
                const element = click.target.closest('a,button,h1,h2,h3,h4,p,img,input,label,.lp-card,section,nav,footer') || click.target;
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
        } else if (identity?.section) {
            const section = [...doc.querySelectorAll('[data-studio-section]')].find((node) => node.dataset.studioSection === identity.section);
            select(identity.index === -1 ? section : section?.querySelectorAll('*')[identity.index]);
        }
        paintTools();
    });
    paintTools();
    syncActions();
    return { select, clear, measure };
}
