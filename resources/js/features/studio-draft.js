// The single Theme Studio draft (Prompts §6): nameable, inspectable,
// restorable, persistent — and NOT publish.
//
// The payload is a snapshot of the FORM exactly as the browser would submit
// it: field name => list of submitted values (checked-only checkboxes,
// repeated `[]` names in DOM order, textarea contents). Capture and replay
// are exact inverses — create/update serialize the live form, restore builds
// a real POST to studio.save with scope=preview, so the ordinary save
// pipeline re-validates every value against config/studio.php before the
// session (working) layer sees it. website_configs is not on that path, and
// the server additionally strips `_token`/`scope` from stored payloads so a
// crafted checkpoint can never smuggle a publish past the restore.

const RESERVED = new Set(['_token', '_method', 'scope']);

const csrfToken = (form) => form.querySelector('input[name="_token"]')?.value || '';

const snapshot = (form) => {
    const payload = {};
    for (const [name, value] of new FormData(form)) {
        if (RESERVED.has(name) || typeof value !== 'string') continue;
        // An empty value is semantically "absent" (the server's own
        // ConvertEmptyStringsToNull says the same), so skip it rather than
        // store a string the request layer would null out anyway.
        if (value === '') continue;
        (payload[name] ??= []).push(value);
    }
    return payload;
};

export default function initDraft(form) {
    const panel = document.querySelector('[data-draft-panel]');
    if (!panel || panel.dataset.draftReady) return;
    panel.dataset.draftReady = '1';

    // The panel is a <details>: collapsed by default (anything collapsible
    // is), and the open choice persists per browser like the inspector's.
    if (panel.tagName === 'DETAILS') {
        try { panel.open = localStorage.getItem('studio.draft.open') === '1'; } catch { /* private mode */ }
        panel.addEventListener('toggle', () => {
            try { localStorage.setItem('studio.draft.open', panel.open ? '1' : '0'); } catch { /* private mode */ }
        });
    }

    const body = panel.querySelector('[data-draft-body]');
    const urls = {
        show: panel.dataset.draftShow,
        store: panel.dataset.draftStore,
        update: panel.dataset.draftUpdate,
        destroy: panel.dataset.draftDestroy,
    };

    let draft = null;
    let busy = false;
    let error = '';
    let inspectOpen = false;

    const authHeaders = () => ({
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(form),
        'X-Requested-With': 'XMLHttpRequest',
    });
    const text = (tag, className, value) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        node.textContent = value;
        return node;
    };
    const button = (label, onClick, className) => {
        const node = document.createElement('button');
        node.type = 'button';
        node.className = className || 'studio-workspace-draft-action';
        node.textContent = label;
        node.addEventListener('click', onClick);
        return node;
    };
    const formatDate = (iso) => {
        try { return new Date(iso).toLocaleString('fa-IR'); } catch { return String(iso || ''); }
    };
    // One request path for every write: non-OK bodies carry a message the
    // rail can show; success replaces the local draft copy and repaints.
    const send = async (url, method, payload) => {
        busy = true;
        error = '';
        render();
        try {
            const response = await fetch(url, {
                method,
                headers: authHeaders(),
                body: payload === undefined ? undefined : JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                error = data.message || 'Something went wrong; try again.';
                return;
            }
            if (method === 'DELETE') draft = null;
            else if (data.draft) draft = data.draft;
            inspectOpen = false;
        } catch {
            error = 'Could not reach the server.';
        } finally {
            busy = false;
            render();
        }
    };

    // Restore: a real form POST to the existing save endpoint. The payload
    // rides as hidden inputs (the same name/value pairs the browser would
    // send), scope is forced to preview, and the CSRF token is the LIVE one —
    // never a captured copy. Reserved keys are skipped again here even
    // though the server strips them on store: defense in depth on the only
    // path that could otherwise turn a draft into a publish.
    const restore = () => {
        if (!draft || busy) return;
        if (!confirm('Restore this draft in the editor? Your current unsaved changes will be replaced and nothing is published.')) return;
        const replay = document.createElement('form');
        replay.method = 'POST';
        replay.action = form.action;
        replay.setAttribute('data-router', 'off');
        const add = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            replay.append(input);
        };
        add('_token', csrfToken(form));
        add('scope', 'preview');
        for (const [name, values] of Object.entries(draft.payload || {})) {
            if (RESERVED.has(name) || !Array.isArray(values)) continue;
            for (const value of values) {
                // Null entries are the stored twin of "empty value" — absent
                // on replay, exactly like the form that never typed them.
                if (value === null || value === undefined || value === '') continue;
                add(name, String(value));
            }
        }
        document.body.append(replay);
        replay.submit();
    };

    const inspectList = () => {
        const list = text('ul', 'studio-workspace-draft-inspect', '');
        list.setAttribute('data-draft-inspect', '');
        const entries = Object.entries(draft?.payload || {});
        const shown = entries.slice(0, 40);
        for (const [name, values] of shown) {
            list.append(text('li', '', `${name} (${values.length})`));
        }
        if (entries.length > shown.length) {
            list.append(text('li', '', `and ${entries.length - shown.length} more…`));
        }
        if (!entries.length) list.append(text('li', '', 'Empty'));
        return list;
    };

    // Inline validation: an empty name stays in the open input (rebuilding
    // the panel would throw away what the user was typing).
    const rejectEmptyName = (input) => {
        let status = body.querySelector('.studio-workspace-draft-status');
        if (!status) {
            status = text('p', 'studio-workspace-draft-status', '');
            body.prepend(status);
        }
        status.textContent = 'Enter a draft name.';
        input.focus();
    };

    const startCreate = () => {
        body.replaceChildren();
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'studio-workspace-draft-name';
        input.placeholder = 'Draft name';
        input.setAttribute('aria-label', 'Draft name');
        input.maxLength = 255;
        const commit = () => {
            const name = input.value.trim();
            if (!name) { rejectEmptyName(input); return; }
            send(urls.store, 'POST', { name, payload: snapshot(form) });
        };
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') { event.preventDefault(); commit(); }
        });
        body.append(
            input,
            button('Save', commit),
            button('Cancel', () => { error = ''; render(); }),
        );
        input.focus();
    };

    const startRename = () => {
        if (!draft) return;
        body.replaceChildren();
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'studio-workspace-draft-name';
        input.value = draft.name;
        input.setAttribute('aria-label', 'Draft name');
        input.maxLength = 255;
        const commit = () => {
            const name = input.value.trim();
            if (!name) { rejectEmptyName(input); return; }
            send(urls.update, 'PUT', { name });
        };
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') { event.preventDefault(); commit(); }
        });
        body.append(
            input,
            button('Save', commit),
            button('Cancel', () => { error = ''; render(); }),
        );
        input.focus();
        input.select?.();
    };

    const render = () => {
        body.replaceChildren();
        if (busy) {
            body.append(text('p', 'studio-workspace-draft-status', 'Working…'));
            return;
        }
        if (error) body.append(text('p', 'studio-workspace-draft-status', error));
        if (!draft) {
            body.append(button('Save draft', startCreate, 'studio-workspace-draft-create'));
            return;
        }
        const head = text('div', 'studio-workspace-draft-head');
        head.append(
            text('strong', 'studio-workspace-draft-title', draft.name),
            text('time', 'studio-workspace-draft-time', formatDate(draft.updated_at)),
        );
        const actions = text('div', 'studio-workspace-draft-actions');
        actions.append(
            button('Restore', restore, 'studio-workspace-draft-restore'),
            button('Update', () => send(urls.update, 'PUT', { payload: snapshot(form) })),
            button('Rename', startRename),
            button('Delete', () => {
                if (confirm(`Delete draft "${draft.name}"?`)) send(urls.destroy, 'DELETE');
            }),
            button('Details', () => { inspectOpen = !inspectOpen; render(); }),
        );
        body.append(head, actions);
        if (inspectOpen) body.append(inspectList());
    };

    // Boot: paint whatever checkpoint already exists for this tenant+user.
    render();
    fetch(urls.show, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => response.json())
        .then((data) => { draft = data.draft || null; render(); })
        .catch(() => { error = 'Could not load the draft.'; render(); });
}
