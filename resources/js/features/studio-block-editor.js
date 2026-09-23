const STYLE_KEYS = ['background', 'color', 'padding', 'radius', 'width'];

// crypto.randomUUID exists only in secure contexts; the studio is served
// over plain http on tenant hostnames, so fall back to getRandomValues.
export function uid() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    return [...bytes].map((b, i) => `${[4, 6, 8, 10].includes(i) ? '-' : ''}${b.toString(16).padStart(2, '0')}`).join('');
}

// Form controls remain the serialization boundary: all edits use the existing
// schema-validated, tenant-scoped save and live-preview endpoints.
export default function initBlockEditor(form, wrap) {
    if (wrap.dataset.editorReady) return;
    wrap.dataset.editorReady = '1';
    const rows = wrap.querySelector('[data-list-rows]');
    const template = wrap.querySelector('[data-list-template]');
    const layers = wrap.querySelector('[data-block-layers]');
    const status = wrap.querySelector('[data-block-status]');
    const list = wrap.studioList;
    if (!rows || !template || !layers || !list) return;
    const max = Number(wrap.dataset.max || 24);
    let selected = null;
    let restoring = false;
    let dragged = null;
    let previewDocument = null;
    let interactive = false;
    const all = () => [...rows.querySelectorAll('[data-list-row]')];
    const controls = (row) => [...row.querySelectorAll('input,textarea,select')];
    const field = (row, key) => row.querySelector(`[name$="[${key}]"]`);
    const id = (row) => field(row, 'id')?.value || row.dataset.legacyId;
    const type = (row) => row.querySelector('[data-list-type] input:checked')?.value || 'heading';
    const label = (row) => field(row, 'title')?.value || field(row, 'text')?.value || type(row);
    all().forEach((row, index) => { row.dataset.legacyId = `legacy-${index}`; });
    const section = () => form.querySelector('[data-sections] input[type="checkbox"][value="blocks"]');
    const snapshot = () => JSON.stringify({
        section: section()?.checked || false,
        rows: all().map((row) => ({
            legacy: row.dataset.legacyId,
            values: controls(row).map((el) => ({ value: el.value, checked: el.checked })),
        })),
    });
    let history = [snapshot()];
    let position = 0;

    const updateButtons = () => {
        wrap.querySelector('[data-block-undo]').disabled = position === 0;
        wrap.querySelector('[data-block-redo]').disabled = position === history.length - 1;
        wrap.querySelector('[data-block-duplicate]').disabled = !selected || all().length >= max;
        wrap.querySelector('[data-block-reset-style]').disabled = !selected;
        wrap.querySelectorAll('[data-block-insert]').forEach((btn) => { btn.disabled = all().length >= max; });
        form.dispatchEvent(new CustomEvent('studio:block-state')); 
    };
    const commit = () => {
        if (restoring) return;
        const state = snapshot();
        if (state !== history[position]) {
            history = history.slice(0, position + 1);
            history.push(state);
            if (history.length > 60) history.shift();
            position = history.length - 1;
        }
        updateButtons();
    };
    const paintSelection = () => {
        previewDocument?.querySelectorAll('[data-studio-block]').forEach((el) => {
            const on = selected && (el.dataset.studioBlock === id(selected) || el.dataset.studioBlock === selected.dataset.legacyId);
            el.classList.toggle('studio-object-selected', Boolean(on) && !interactive);
        });
    };
    const select = (row) => {
        selected = row;
        all().forEach((item) => {
            item.classList.toggle('is-block-selected', item === row);
            item.querySelector('.studio-list-fields').hidden = item !== row;
            item.querySelector('.studio-list-row-head').hidden = item !== row;
            item.hidden = item !== row;
        });
        layers.querySelectorAll('[data-layer-id]').forEach((btn) => {
            btn.setAttribute('aria-pressed', String(row && btn.dataset.layerId === id(row)));
        });
        paintSelection();
        updateButtons();
        form.dispatchEvent(new CustomEvent('studio:block-selected', { detail: { row, id: row && id(row) } }));
    };
    const renderLayers = () => {
        layers.replaceChildren();
        all().forEach((row) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'studio-block-layer';
            btn.dataset.layerId = id(row);
            btn.draggable = true;
            const visible = row.querySelector('input[type="checkbox"][name$="[visible]"]')?.checked;
            btn.textContent = `${visible ? '◈' : '○'} ${label(row).slice(0, 60)}`;
            btn.addEventListener('click', () => select(row));
            btn.addEventListener('dragstart', (event) => {
                dragged = row;
                event.dataTransfer?.setData('text/plain', id(row));
                if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
            });
            btn.addEventListener('dragend', () => { dragged = null; });
            btn.addEventListener('dragover', (event) => { if (dragged) event.preventDefault(); });
            btn.addEventListener('drop', (event) => {
                event.preventDefault();
                if (!dragged || dragged === row) return;
                const after = event.clientY > btn.getBoundingClientRect().top + btn.getBoundingClientRect().height / 2;
                stabilize();
                rows.insertBefore(dragged, after ? row.nextElementSibling : row);
                selected = dragged;
                dragged = null;
                list.refresh(true);
            });
            layers.append(btn);
        });
        if (selected && !selected.isConnected) selected = all()[0] || null;
        select(selected);
        status.textContent = all().length ? `${all().length} / ${max} بلوک` : 'برای شروع یک بلوک اضافه کنید.';
    };
    // Freeze every legacy identity before changing order; old preview documents
    // still map through legacyId until the next server-rendered frame arrives.
    const stabilize = () => all().forEach((row) => {
        const input = field(row, 'id');
        if (input && !input.value) input.value = uid();
    });
    const makeRow = (saved = null) => {
        const row = template.content.firstElementChild.cloneNode(true);
        const key = 'n' + uid();
        row.querySelectorAll('[name],[id],[for]').forEach((el) => {
            for (const attr of ['name', 'id', 'for']) {
                const value = el.getAttribute(attr);
                if (value) el.setAttribute(attr, value.replaceAll('__KEY__', key));
            }
        });
        if (saved) {
            controls(row).forEach((el, i) => {
                const value = saved.values[i];
                if (!value) return;
                el.value = value.value;
                if (el.type === 'checkbox' || el.type === 'radio') el.checked = value.checked;
            });
            if (saved.legacy) row.dataset.legacyId = saved.legacy;
        }
        rows.append(row);
        list.wireRow(row);
        row.querySelectorAll('[data-filter-select]').forEach((group) => {
            const checked = group.querySelector('input:checked');
            const display = group.querySelector('[data-filter-select-display]');
            if (checked && display) display.textContent = checked.closest('label').dataset.label;
        });
        return row;
    };
    const enableSection = () => {
        const checkbox = section();
        if (checkbox && !checkbox.checked) {
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
    const insert = (blockType) => {
        if (all().length >= max) return;
        restoring = true;
        stabilize();
        const row = makeRow();
        const radio = row.querySelector(`[data-list-type] input[value="${blockType}"]`);
        if (radio) {
            radio.checked = true;
            const display = row.querySelector('[data-list-type] [data-filter-select-display]');
            if (display) display.textContent = radio.closest('label')?.dataset.label || blockType;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
        field(row, 'id').value = uid();
        const titles = { heading: 'عنوان جدید', button: 'دکمه جدید', card: 'کارت جدید' };
        if (titles[blockType]) field(row, 'title').value = titles[blockType];
        if (blockType === 'text') field(row, 'text').value = 'متن خود را اینجا بنویسید';
        if (blockType === 'button') field(row, 'href').value = '#';
        row.querySelector('input[type="checkbox"][name$="[visible]"]').checked = true;
        selected = row;
        enableSection();
        restoring = false;
        list.refresh(true);
        if (blockType === 'image') {
            status.textContent = 'نشانی تصویر را وارد کنید تا پیش‌نمایش نمایش داده شود.';
            field(row, 'src').focus();
        }
    };
    const restore = (next) => {
        if (next < 0 || next >= history.length) return;
        commit();
        restoring = true;
        position = next;
        const previousId = selected && id(selected);
        rows.replaceChildren();
        const savedState = JSON.parse(history[position]);
        savedState.rows.forEach((saved) => makeRow(saved));
        if (section()) {
            section().checked = savedState.section;
            section().dispatchEvent(new Event('change', { bubbles: true }));
        }
        selected = all().find((row) => id(row) === previousId) || all()[0] || null;
        list.refresh(true);
        restoring = false;
        renderLayers();
    };
    wrap.addEventListener('input', (event) => {
        const group = event.target.closest('[data-block-color]');
        if (!group) return;
        const picker = group.querySelector('[data-block-color-picker]');
        const value = group.querySelector('[data-block-color-value]');
        if (event.target === picker) value.value = picker.value;
        else if (/^#[0-9a-f]{6}$/i.test(value.value)) picker.value = value.value;
    });
    wrap.addEventListener('change', () => { if (!restoring) { commit(); renderLayers(); } });
    wrap.addEventListener('studio:list-changed', () => { renderLayers(); commit(); });
    wrap.addEventListener('click', (event) => {
        if (event.target.closest('.list-move-up,.list-move-down,[data-list-del]')) stabilize();
    }, true);
    wrap.querySelectorAll('[data-block-insert]').forEach((btn) => btn.addEventListener('click', () => insert(btn.dataset.blockInsert)));
    wrap.querySelector('[data-list-add]').hidden = true;
    wrap.addEventListener('studio:list-changed', () => { wrap.querySelector('[data-list-add]').hidden = true; });
    wrap.querySelector('[data-block-undo]').addEventListener('click', () => restore(position - 1));
    wrap.querySelector('[data-block-redo]').addEventListener('click', () => restore(position + 1));
    wrap.querySelector('[data-block-duplicate]').addEventListener('click', () => {
        if (!selected || all().length >= max) return;
        stabilize();
        const saved = JSON.parse(snapshot()).rows[all().indexOf(selected)];
        const row = makeRow(saved);
        field(row, 'id').value = uid();
        delete row.dataset.legacyId;
        rows.insertBefore(row, selected.nextElementSibling);
        selected = row;
        list.refresh(true);
    });
    wrap.querySelector('[data-block-reset-style]').addEventListener('click', () => {
        if (!selected) return;
        STYLE_KEYS.forEach((key) => { field(selected, key).value = ''; });
        list.refresh(true);
    });
    wrap.addEventListener('keydown', (event) => {
        if (event.target.matches('input,textarea,select')) return;
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
            event.preventDefault();
            restore(position + (event.shiftKey ? 1 : -1));
        }
    });

    const bindFrame = (frame) => {
        let doc;
        try { doc = frame.contentDocument; } catch { return; }
        if (!doc?.body) return;
        previewDocument = doc;
        doc.documentElement.classList.toggle('studio-canvas-editing', !interactive);
        if (!doc.documentElement.dataset.blockEditorBound) {
            doc.documentElement.dataset.blockEditorBound = '1';
            doc.addEventListener('click', (event) => {
                if (interactive) return;
                const element = event.target.closest('[data-studio-block]');
                if (!element) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                const row = all().find((item) => id(item) === element.dataset.studioBlock || item.dataset.legacyId === element.dataset.studioBlock);
                if (row) {
                    document.querySelector('[data-studio-tab="blocks"]')?.click();
                    select(row);
                }
            }, true);
            doc.addEventListener('submit', (event) => {
                if (!interactive) { event.preventDefault(); event.stopImmediatePropagation(); }
            }, true);
        }
        paintSelection();
    };
    form.addEventListener('studio:frame-ready', (event) => bindFrame(event.detail.frame));
    // Interaction mode comes from the workspace tool (select vs interact);
    // studio-workspace.js broadcasts the switch on the form.
    form.addEventListener('studio:tool', (event) => {
        interactive = event.detail?.tool === 'interact';
        previewDocument?.documentElement.classList.toggle('studio-canvas-editing', !interactive);
        paintSelection();
    });
    selected = all()[0] || null;
    renderLayers();
    const editor = { snapshot, insert, select, undo: () => restore(position - 1), redo: () => restore(position + 1) };
    wrap.studioEditor = editor;
    return editor;
}
