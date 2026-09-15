// Google Docs-style writing surface for the blog body.
//
// The form (consultant/blog/form.blade.php) ships a plain <textarea
// name="body"> plus a hidden contenteditable surface and toolbar. This
// module — when present — unhides the surface, moves the body into it, and
// keeps the textarea synced on every edit, so the submit path (native POST
// or the dialog's JSON fetch) never notices the difference and no-JS users
// keep the old plain-text control.
//
// Storage format is allowlisted HTML; the exact mirror of this filter runs
// server-side in App\Support\RichText on save. The client pass here only
// shapes pasted/dropped junk while typing — the server is the authority.
//
// Images upload straight to the panel's media route (like the schedule
// modal talks its endpoints) and may only reference the tenant's own tree,
// which is also what the server sanitizer re-validates.

const ALIGN_CLASSES = ['align-left', 'align-center', 'align-right'];
const SIZE_CLASSES = ['size-small', 'size-medium', 'size-large', 'size-full'];

const DROP_TAGS = new Set([
    'script', 'style', 'noscript', 'iframe', 'object', 'embed', 'form', 'input',
    'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta', 'base',
]);

// Same vocabulary the server allowlist (App\Support\RichText::ALLOWED) keeps.
const KEEP_TAGS = new Set([
    'p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'pre', 'br',
    'table', 'thead', 'tbody', 'tr', 'td', 'th',
    'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'sub', 'sup',
    'code', 'mark', 'a', 'img',
]);

// Anything that is not a block the server keeps verbatim counts as inline
// for the paragraph-folding passes (normalizeSurface, cleanHtml's div rule).
const INLINE_TAGS = new Set([
    'a', 'b', 'br', 'code', 'del', 'em', 'i', 'img', 'ins', 'mark', 's',
    'small', 'span', 'strike', 'strong', 'sub', 'sup', 'u',
]);

const BLOCK_TAGS = new Set([
    'p', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre',
    'hr', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'div',
]);

const TEXT_ALIGN_RE = /^text-align:\s*(left|right|center|justify|start|end)\s*;?$/i;

export function enhanceBlogEditors(root = document) {
    root.querySelectorAll?.('[data-blog-editor]').forEach((wrap) => {
        if (!wrap.dataset.enhanced) {
            wrap.dataset.enhanced = '1';
            enhance(wrap);
        }
    });
}

export default function init(region) {
    enhanceBlogEditors(region ?? document);
}

/* --------------------------------------------------------------- editor */

function enhance(wrap) {
    const surface = wrap.querySelector('[data-editor-surface]');
    const source = wrap.querySelector('[data-editor-source]');
    const toolbar = wrap.querySelector('.blog-editor-toolbar');
    const fileInput = wrap.querySelector('[data-editor-file]');
    const blockSelect = wrap.querySelector('[data-block-select]');
    const sizeSelect = wrap.querySelector('[data-size-select]');
    const counter = wrap.querySelector('[data-editor-count]');

    if (!surface || !source) return;

    let selectedImage = null;
    let savedRange = null; // caret owned by toolbar actions (menus, file pickers)

    /* boot: textarea value -> surface */
    const initial = source.value.trim();
    surface.innerHTML = initial.includes('<') ? cleanHtml(initial) : paragraphize(initial);
    surface.hidden = false;
    source.classList.add('is-editor-hidden');

    const sync = () => {
        normalizeSurface(); // the browser is sloppy about top-level structure

        let html = surface.innerHTML.trim();

        if (html === '' || /^(<(p|div)>(<br\s*\/?>|&nbsp;)?<\/\2>|<br\s*\/?>)$/i.test(html)) {
            html = ''; // an empty editor posts an empty body, not <p><br></p>
        }

        if (source.value !== html) {
            source.value = html;
            // The dialog watches input events to clear painted 422 errors.
            source.dispatchEvent(new Event('input', { bubbles: true }));
        }

        if (counter) {
            const words = (surface.textContent.trim().match(/\S+/gu) ?? []).length;
            counter.textContent = words
                ? `${words.toLocaleString('fa-IR')} کلمه`
                : '';
        }
    };

    surface.addEventListener('input', () => {
        if (selectedImage && !surface.contains(selectedImage)) deselectImage();
        sync();
    });

    wrap.closest('form')?.addEventListener('submit', sync, true);

    /* selection bookkeeping */

    const inSurface = (node) => {
        const el = node && (node.nodeType === 3 ? node.parentNode : node);
        return !!el && surface.contains(el);
    };

    const saveRange = () => {
        const selection = document.getSelection();
        if (selection?.rangeCount && inSurface(selection.getRangeAt(0).startContainer)) {
            savedRange = selection.getRangeAt(0).cloneRange();
        }
    };

    const restoreRange = () => {
        if (!savedRange) return false;
        const selection = document.getSelection();
        selection.removeAllRanges();
        selection.addRange(savedRange);
        return true;
    };

    const exec = (command, value = null) => {
        surface.focus();
        restoreRange();
        document.execCommand(command, false, value);
        saveRange();
        sync();
        refreshStates();
    };

    const insertNodeAtCaret = (node) => {
        surface.focus();

        if (restoreRange() && savedRange) {
            savedRange.insertNode(node);
            const after = document.createRange();
            after.setStartAfter(node);
            after.collapse(true);
            savedRange = after;
        } else {
            surface.appendChild(node);
        }

        sync();
    };

    /* structure normalization
       ---------------------------------------------------------------
       A contenteditable root starts life as loose text nodes plus <div>
       line wrappers (Chrome's Enter behavior). Left as-is the browser
       renders them without paragraph rhythm and the server sees markup the
       editor never "shows"; folding those runs into <p>s here keeps the
       surface, the stored HTML and the public typography in agreement. */

    const isInlineNode = (node) => node.nodeType === Node.TEXT_NODE
        || (node.nodeType === Node.ELEMENT_NODE
            && !BLOCK_TAGS.has(node.tagName.toLowerCase()));

    const toParagraph = (element) => {
        const p = document.createElement('p');
        const style = element.getAttribute('style');
        if (style) p.setAttribute('style', style);
        while (element.firstChild) p.appendChild(element.firstChild);
        element.replaceWith(p);
    };

    function normalizeSurface() {
        for (const node of [...surface.childNodes]) {
            if (node.nodeType === Node.ELEMENT_NODE
                && /^(div|figure|section|article)$/i.test(node.tagName)) {
                const hasBlock = [...node.children].some((c) => !INLINE_TAGS.has(c.tagName.toLowerCase()));

                if (hasBlock || node.tagName.toLowerCase() !== 'div') {
                    unwrap(node);
                } else {
                    toParagraph(node); // mirrors the server's div rule
                }
            }
        }

        let run = [];

        const flush = () => {
            const meaningful = run.filter((n) => !(n.nodeType === Node.TEXT_NODE && !n.textContent.trim()));

            if (meaningful.length) {
                const p = document.createElement('p');
                surface.insertBefore(p, meaningful[0]);
                meaningful.forEach((n) => p.appendChild(n));
            }

            run = [];
        };

        for (const node of [...surface.childNodes]) {
            if (isInlineNode(node)) {
                run.push(node);
            } else {
                flush();
            }
        }

        flush();
    }

    /* toolbar */

    toolbar?.addEventListener('mousedown', (event) => {
        // Keep the surface selection alive while a button/menu gets the click.
        if (event.target.closest('button')) event.preventDefault();
        saveRange();
    });

    toolbar?.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;

        if (button.dataset.cmd) {
            exec(button.dataset.cmd);
        } else if (button.dataset.align) {
            if (selectedImage) {
                selectedImage.classList.remove(...ALIGN_CLASSES);
                selectedImage.classList.add(button.dataset.align);
                sync();
            } else {
                exec(button.dataset.justify);
            }
        } else if (button.dataset.action === 'link') {
            createLink();
        } else if (button.dataset.action === 'unlink') {
            exec('unlink');
        } else if (button.dataset.action === 'image') {
            fileInput?.click();
        } else if (button.dataset.action === 'highlight') {
            toggleHighlight();
        } else if (button.dataset.action === 'table') {
            createTable();
        } else if (button.dataset.action === 'remove-image') {
            selectedImage?.remove();
            deselectImage();
            sync();
        }
    });

    blockSelect?.addEventListener('change', () => {
        exec('formatBlock', `<${blockSelect.value}>`);
    });

    sizeSelect?.addEventListener('change', () => {
        if (!selectedImage) return;
        selectedImage.classList.remove(...SIZE_CLASSES);
        selectedImage.classList.add(sizeSelect.value);
        sync();
    });

    fileInput?.addEventListener('change', () => {
        const files = [...(fileInput.files ?? [])].filter((file) => file.type.startsWith('image/'));
        fileInput.value = '';
        if (files.length) uploadFiles(files);
    });

    function createLink() {
        const url = window.prompt('نشانی پیوند (مثلاً https://exemple.ir):', 'https://');

        if (!url) return;

        const href = url.trim();

        if (/^(javascript|data|vbscript):/i.test(href)) return;

        surface.focus();
        restoreRange();
        document.execCommand('createLink', false, href);
        saveRange();
        sync();
        refreshStates();
    }

    /**
     * Highlight is stored as <mark> (allowlisted on both sides) rather than
     * execCommand's inline background-color, which the sanitizer would strip.
     */
    function toggleHighlight() {
        surface.focus();

        if (!restoreRange()) return;

        const selection = document.getSelection();
        const range = selection?.rangeCount ? selection.getRangeAt(0) : null;

        if (!range) return;

        const anchor = range.commonAncestorContainer;
        const holder = anchor.nodeType === Node.TEXT_NODE ? anchor.parentElement : anchor;
        const existing = holder?.closest?.('mark');

        if (existing && surface.contains(existing)) {
            while (existing.firstChild) existing.parentNode.insertBefore(existing.firstChild, existing);
            existing.remove();
        } else if (!range.collapsed) {
            const mark = document.createElement('mark');

            try {
                range.surroundContents(mark);
            } catch {
                mark.appendChild(range.extractContents()); // selection crossed block edges
                range.insertNode(mark);
            }

            const after = document.createRange();
            after.selectNodeContents(mark);
            selection.removeAllRanges();
            selection.addRange(after);
        }

        saveRange();
        sync();
        refreshStates();
    }

    function askCells(label, fallback, max) {
        const raw = window.prompt(label, String(fallback));

        if (raw === null) return null;

        const value = raw.trim() === '' ? fallback : Number(raw);

        return Number.isInteger(value) && value >= 1 && value <= max ? value : null;
    }

    function createTable() {
        const rows = askCells('تعداد ردیف جدول:', '3', 20);

        if (rows === null) return;

        const cols = askCells('تعداد ستون جدول:', '3', 10);

        if (cols === null) return;

        const table = document.createElement('table');
        const tbody = document.createElement('tbody');

        for (let r = 0; r < rows; r += 1) {
            const tr = document.createElement('tr');

            for (let c = 0; c < cols; c += 1) {
                const cell = document.createElement(r === 0 ? 'th' : 'td');
                cell.appendChild(document.createElement('br')); // empty cells stay clickable
                tr.appendChild(cell);
            }

            tbody.appendChild(tr);
        }

        table.appendChild(tbody);

        // Never land a block inside the paragraph the caret sits in: drop it
        // under the surface right after that paragraph instead.
        const ref = savedRange ? savedRange.startContainer : document.getSelection()?.anchorNode;
        const holder = ref && (ref.nodeType === Node.TEXT_NODE ? ref.parentElement : ref);
        const blockParent = holder?.closest?.('p, h2, h3, h4, li, blockquote, pre');

        if (blockParent && surface.contains(blockParent)) {
            blockParent.after(table);
        } else {
            insertNodeAtCaret(table);
        }

        if (!table.nextSibling) {
            // Otherwise there is no way to click past the last row.
            const tail = document.createElement('p');
            tail.appendChild(document.createElement('br'));
            surface.appendChild(tail);
        }

        sync();
    }

    /* image selection */

    surface.addEventListener('click', (event) => {
        const target = event.target;

        if (target instanceof HTMLImageElement) {
            selectImage(target);
        } else {
            deselectImage();
        }
    });

    function selectImage(image) {
        deselectImage();
        selectedImage = image;
        image.classList.add('is-selected');
        wrap.classList.add('has-image');

        if (sizeSelect) {
            const current = SIZE_CLASSES.find((cls) => image.classList.contains(cls));
            sizeSelect.value = current ?? 'size-medium';
        }
    }

    function deselectImage() {
        selectedImage?.classList.remove('is-selected');
        selectedImage = null;
        wrap.classList.remove('has-image');
    }

    /* paste and drag-drop */

    surface.addEventListener('paste', (event) => {
        event.preventDefault(); // Word and web pages paste a lot of markup we would drop anyway

        const html = event.clipboardData?.getData('text/html');
        const text = event.clipboardData?.getData('text/plain');
        const fragment = html ? cleanHtml(html) : '';

        if (fragment && stripTags(fragment)) {
            document.execCommand('insertHTML', false, fragment);
        } else if (text) {
            document.execCommand('insertText', false, text);
        }

        sync();
        refreshStates();
    });

    ['dragenter', 'dragover'].forEach((type) =>
        surface.addEventListener(type, (event) => {
            if (event.dataTransfer?.types?.includes('Files')) {
                event.preventDefault();
                surface.classList.add('is-drop-target');
            }
        })
    );

    surface.addEventListener('dragleave', () => surface.classList.remove('is-drop-target'));

    surface.addEventListener('drop', (event) => {
        surface.classList.remove('is-drop-target');

        const files = [...(event.dataTransfer?.files ?? [])].filter((file) =>
            file.type.startsWith('image/')
        );

        if (!files.length) return;

        event.preventDefault();

        const fromPoint = document.caretRangeFromPoint?.(event.clientX, event.clientY);
        let caret = fromPoint;

        if (!caret && document.caretPositionFromPoint) {
            const position = document.caretPositionFromPoint(event.clientX, event.clientY);

            if (position) {
                caret = document.createRange();
                caret.setStart(position.offsetNode, position.offset);
                caret.collapse(true);
            }
        }

        if (caret && surface.contains(caret.endContainer)) {
            savedRange = caret.cloneRange();
        } else {
            saveRange();
        }

        uploadFiles(files);
    });

    /* upload */

    async function uploadFiles(files) {
        for (const file of files) {
            const marker = document.createElement('span');
            marker.className = 'blog-editor-uploading';
            marker.textContent = 'در حال بارگذاری تصویر…';
            insertNodeAtCaret(marker);

            try {
                const body = new FormData();
                body.append('image', file);
                body.append('_token', wrap.dataset.csrf ?? '');

                const response = await fetch(wrap.dataset.mediaUrl, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok || !payload?.url) {
                    throw new Error(String(response.status));
                }

                const image = document.createElement('img');
                image.src = payload.url;
                image.alt = file.name.replace(/\.[^.]+$/, '');
                image.className = `align-center ${sizeSelect?.value ?? 'size-medium'}`;
                marker.replaceWith(image);
            } catch (error) {
                console.error('[blog-editor]', error);
                marker.remove();
                window.alert('تصویر بارگذاری نشد؛ دوباره تلاش کنید.');
            }

            sync();
        }
    }

    /* keyboard niceties */

    surface.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            saveRange();
            createLink();
        }

        if (event.key === 'Tab') {
            // Tab must walk table cells, not escape into the browser chrome.
            const selection = document.getSelection();
            const anchor = selection?.anchorNode;
            const holder = anchor && (anchor.nodeType === Node.TEXT_NODE ? anchor.parentElement : anchor);
            const cell = holder?.closest?.('td, th');

            if (!cell || !surface.contains(cell)) return;

            event.preventDefault();

            const cells = [...cell.closest('table').querySelectorAll('td, th')];
            const next = cells[cells.indexOf(cell) + (event.shiftKey ? -1 : 1)];

            if (!next) return;

            const range = document.createRange();
            range.selectNodeContents(next);
            selection.removeAllRanges();
            selection.addRange(range);
            saveRange();
        }
    });

    /* toolbar state follows the caret */

    function refreshStates() {
        if (!toolbar) return;

        if (!document.contains(surface)) {
            // The dialog slot was replaced: drop the stale per-editor hook.
            document.removeEventListener('selectionchange', refreshStates);
            return;
        }

        if (!inSurface(document.getSelection()?.anchorNode)) return;

        toolbar.querySelectorAll('[data-state]').forEach((button) => {
            let active = false;

            try {
                active = document.queryCommandState(button.dataset.state);
            } catch {
                active = false;
            }

            button.classList.toggle('is-active', active);
        });

        if (blockSelect) {
            const block = (document.queryCommandValue('formatBlock') || 'p').toLowerCase();
            blockSelect.value = ['h2', 'h3', 'blockquote'].includes(block) ? block : 'p';
        }

        toolbar.querySelectorAll('[data-justify]').forEach((button) => {
            let active = false;

            try {
                active = document.queryCommandState(button.dataset.justify);
            } catch {
                active = false;
            }

            button.classList.toggle('is-active', active);
        });

        const markButton = toolbar.querySelector('[data-action="highlight"]');

        if (markButton) {
            const anchor = document.getSelection()?.anchorNode;
            const holder = anchor && (anchor.nodeType === Node.TEXT_NODE ? anchor.parentElement : anchor);
            markButton.classList.toggle('is-active', !!holder?.closest?.('mark'));
        }
    }

    document.addEventListener('selectionchange', refreshStates);
    surface.addEventListener('keyup', refreshStates);
    surface.addEventListener('mouseup', () => {
        saveRange();
        refreshStates();
    });

    sync();
}

/* ------------------------------------------------------- html filtering */

function stripTags(html) {
    return html.replace(/<[^>]*>/g, '');
}

function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function paragraphize(text) {
    return text
        .split(/\r\n|\r|\n/)
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => `<p>${escapeHtml(line)}</p>`)
        .join('');
}

/**
 * Client mirror of the server allowlist, for what lands in the surface:
 * unknown wrappers are unwrapped, all styling except text-align/class
 * vocabulary goes, and only our own media paths survive as images.
 */
function cleanHtml(html) {
    const parsed = new DOMParser().parseFromString(`<!doctype html><div>${html}</div>`, 'text/html');
    const root = parsed.body.firstElementChild ?? parsed.createElement('div');

    cleanInto(root);

    return root.innerHTML;
}

function cleanInto(parent) {
    [...parent.childNodes].forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) return;

        if (node.nodeType !== Node.ELEMENT_NODE) {
            node.remove();
            return;
        }

        const tag = node.tagName.toLowerCase();

        if (DROP_TAGS.has(tag)) {
            node.remove();
            return;
        }

        if (tag === 'div') {
            cleanInto(node);

            const hasBlock = [...node.children].some((c) => !INLINE_TAGS.has(c.tagName.toLowerCase()));

            if (hasBlock) unwrap(node);
            else toParagraphIn(node, parsed);

            return;
        }

        if (tag === 'figure' || tag === 'section' || tag === 'article') {
            cleanInto(node);
            unwrap(node);
            return;
        }

        if (!KEEP_TAGS.has(tag)) {
            cleanInto(node);
            unwrap(node);
            return;
        }

        [...node.attributes].forEach((attr) => {
            const name = attr.name.toLowerCase();
            const value = attr.value;

            if (name === 'class' && tag === 'img') {
                const kept = value
                    .split(/\s+/)
                    .filter((cls) => [...ALIGN_CLASSES, ...SIZE_CLASSES].includes(cls));
                if (kept.length) node.setAttribute('class', kept.join(' '));
                else node.removeAttribute('class');
            } else if (name === 'colspan' || name === 'rowspan') {
                const span = Number.parseInt(value, 10);
                if (/^\d+$/.test(value.trim()) && span >= 1 && span <= 100) node.setAttribute(name, String(span));
                else node.removeAttribute(attr.name);
            } else if (name === 'style') {
                const match = TEXT_ALIGN_RE.exec(value);
                if (match) node.setAttribute('style', `text-align:${match[1].toLowerCase()}`);
                else node.removeAttribute('style');
            } else if (name === 'href') {
                if (/^(https?|mailto|tel|\/|#)/i.test(value.trim()) && !value.includes('\\') && !value.startsWith('//')) {
                    node.setAttribute('href', value.trim());
                } else {
                    node.removeAttribute('href');
                }
            } else if (name === 'src') {
                if (value.trim().startsWith('/tenants/')) node.setAttribute('src', value.trim());
                else node.removeAttribute('src');
            } else if (name === 'alt') {
                // keep as-is
            } else {
                node.removeAttribute(attr.name);
            }
        });

        if (tag === 'img' && !node.getAttribute('src')) {
            node.remove();
            return;
        }

        cleanInto(node);
    });
}

function unwrap(element) {
    const parent = element.parentNode;
    if (!parent) return;

    while (element.firstChild) parent.insertBefore(element.firstChild, element);
    parent.removeChild(element);
}

/** div -> p inside a parsed document (cleanInto cannot touch `document`). */
function toParagraphIn(element, parsed) {
    const p = parsed.createElement('p');
    const style = element.getAttribute('style');
    if (style) p.setAttribute('style', style);
    while (element.firstChild) p.appendChild(element.firstChild);
    element.replaceWith(p);
}

/* The form fragment is injected into the dialog by consultant-blog-modal.js
   (which calls enhanceBlogEditors on the slot) and rendered whole on the
   form page; self-boot covers the plain page load without the router. */

if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => enhanceBlogEditors(document));
    } else {
        enhanceBlogEditors(document);
    }
}
