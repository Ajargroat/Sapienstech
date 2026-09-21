// Lists: repeater rows for item-valued content (cards, buttons, links).
// "Add" clones the <template> row, swapping its __KEY__ placeholders for a
// unique browser-side key so a fresh row can never collide with a stored
// index; delete/move just edit the DOM, and the submitted nested array's key
// order carries the result (StudioSchema::normalizeList re-indexes it). An
// abandoned empty row is dropped server-side before validation, and clearing
// every row forgets the list override so the file-owned items show through.
//
// Lives in its own module (rather than inside theme-studio.js) so the row
// operations are unit-testable: theme-studio.js imports a stylesheet, which
// the Node test loader cannot resolve.
//
// `syncToggles` is the studio's toggle-label refresher; it is optional so the
// factory can be exercised on its own. It is idempotent by contract — the
// handler only rewrites the label text, so re-binding old rows is a no-op.
export default function initLists(form, syncToggles = null) {
    form.querySelectorAll('[data-studio-list]').forEach((wrap) => {
        const rows = wrap.querySelector('[data-list-rows]');
        const tpl = wrap.querySelector('[data-list-template]');
        const addBtn = wrap.querySelector('[data-list-add]');
        if (!rows || !tpl) return;

        const max = parseInt(wrap.dataset.max || '20', 10);
        let seq = 0;

        const rowList = () => Array.from(rows.querySelectorAll('[data-list-row]'));

        // One pristine clone with its __KEY__ placeholders swapped for a unique
        // browser-side key, so a fresh row can never collide with a stored
        // index (StudioSchema::normalizeList re-indexes whatever survives).
        const spawn = () => {
            const key = 'n' + (++seq) + '-' + Math.random().toString(36).slice(2, 8);
            const node = tpl.content.firstElementChild?.cloneNode(true);
            if (!node) return null;
            node.querySelectorAll('[name],[id],[for]').forEach((el) => {
                ['name', 'id', 'for'].forEach((attr) => {
                    const v = el.getAttribute(attr);
                    if (v && v.includes('__KEY__')) el.setAttribute(attr, v.replaceAll('__KEY__', key));
                });
            });
            syncToggles?.(form);
            return node;
        };

        const refresh = (notify) => {
            rowList().forEach((row, i) => {
                const num = row.querySelector('[data-list-num]');
                if (num) num.textContent = i + 1;
            });
            if (addBtn) addBtn.hidden = wrap.hasAttribute('data-block-editor') || rowList().length >= max;
            // Structural change: bubble an input event so the live preview
            // (which listens on the form) re-renders the site.
            if (notify) form.dispatchEvent(new Event('input', { bubbles: true }));
            wrap.dispatchEvent(new CustomEvent('studio:list-changed', { detail: { notify } }));
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

        // Row operations shared by the inline buttons, the workspace tool rail
        // and the context panel: all of them move DOM nodes, so the submitted
        // key order carries the result and the server re-indexes it. `notify`
        // bubbles a form input event, which is what re-renders the preview.
        const add = () => {
            if (rowList().length >= max) return null;
            const node = spawn();
            if (!node) return null;
            rows.appendChild(node);
            wireRow(node);
            syncToggles?.(form);
            refresh(true);
            return node;
        };
        const remove = (row) => {
            if (!row || !rows.contains(row)) return;
            row.remove();
            refresh(true);
        };
        const duplicate = (row) => {
            if (!row || rowList().length >= max) return null;
            const copy = row.cloneNode(true);
            // A clone would repeat its source's browser-side row key; re-key the
            // copy so both rows stay independently addressable. The row key is
            // the bracket segment before the final field name
            // (list[rowKey][field]), which the lookahead pins down.
            const key = 'n' + (++seq) + '-' + Math.random().toString(36).slice(2, 8);
            copy.querySelectorAll('[name]').forEach((el) => {
                const name = el.getAttribute('name');
                el.setAttribute('name', name.replace(/\[[^\]]+\](?=\[[^\]]+\]$)/, `[${key}]`));
            });
            copy.querySelectorAll('[id],[for]').forEach((el) => {
                ['id', 'for'].forEach((attr) => {
                    const value = el.getAttribute(attr);
                    if (value) el.setAttribute(attr, `${value}-${key}`);
                });
            });
            // A duplicated block row must not reuse its source's identity; a new
            // one is minted by the caller (which owns the uid helper).
            rows.insertBefore(copy, row.nextElementSibling);
            wireRow(copy);
            syncToggles?.(form);
            refresh(true);
            return copy;
        };
        const move = (row, offset) => {
            if (!row) return;
            const sibling = offset < 0 ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling) return;
            if (offset < 0) rows.insertBefore(row, sibling);
            else rows.insertBefore(sibling, row);
            refresh(true);
        };

        wrap.studioList = { wireRow, refresh, add, duplicate, move, remove };
        rowList().forEach(wireRow);
        refresh(false);

        addBtn?.addEventListener('click', () => add());
    });
}
