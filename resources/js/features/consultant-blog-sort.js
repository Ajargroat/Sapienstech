// Blog list drag-and-drop ordering (replaces the old «ترتیب نمایش» field).
//
// The grip button beside each row starts a pointer-driven sort so the row
// never fights the browser's native text selection. Rows are re-inserted
// live by midpoint rule, then translated back to the cursor — recomputing
// from the layout position each move keeps the math stateless. On release
// (or ArrowUp/ArrowDown with the grip focused) the visible ids are PUT to
// the reorder route; a failed save reloads the page to the server truth.
//
// The slice sent may be status-filtered or paginated: the controller
// permutes those ids across exactly the global slots they occupy, so the
// drag only ever shuffles what the user can actually see.

export default function init() {
    const list = document.querySelector('[data-blog-sort]');
    if (!list || list.dataset.sortBooted) return;
    list.dataset.sortBooted = '1';

    const rows = () => Array.from(list.querySelectorAll('[data-post-id]'));
    const order = () => rows().map((row) => Number(row.dataset.postId));

    let lastSaved = order();

    async function persist() {
        const posts = order();
        if (posts.join() === lastSaved.join()) return;

        try {
            const response = await fetch(list.dataset.sortUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': list.dataset.csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ posts }),
            });
            if (!response.ok) throw new Error(String(response.status));
            lastSaved = posts;
        } catch {
            window.location.reload();
        }
    }

    function startDrag(row, grip, down) {
        down.preventDefault();

        const others = rows().filter((r) => r !== row);
        // Where the pointer grabbed the row — keeps the row from "jumping"
        // so its top is under the cursor at the start.
        const grabOffset = down.clientY - row.getBoundingClientRect().top;
        let moved = false;

        row.classList.add('is-dragging');
        list.classList.add('is-sorting');

        const move = (event) => {
            event.preventDefault();
            moved = true;

            // Snap back to the layout position, slide into the DOM slot the
            // cursor points at, then push the row to follow the cursor.
            row.style.transform = '';

            const target = others.find((other) => {
                const rect = other.getBoundingClientRect();
                return event.clientY >= rect.top && event.clientY <= rect.bottom;
            });

            if (target) {
                const rect = target.getBoundingClientRect();
                const after = event.clientY > rect.top + rect.height / 2;
                const reference = after ? target.nextSibling : target;
                if (row !== reference && row.nextSibling !== reference) {
                    list.insertBefore(row, reference);
                }
            }

            const top = row.getBoundingClientRect().top;
            row.style.transform = `translateY(${event.clientY - grabOffset - top}px)`;
        };

        const finish = () => {
            document.removeEventListener('pointermove', move);
            document.removeEventListener('pointerup', finish);
            document.removeEventListener('pointercancel', finish);

            row.style.transform = '';
            row.classList.remove('is-dragging');
            list.classList.remove('is-sorting');
            grip.focus();

            if (moved) persist();
        };

        document.addEventListener('pointermove', move);
        document.addEventListener('pointerup', finish);
        document.addEventListener('pointercancel', finish);
    }

    list.addEventListener('pointerdown', (event) => {
        const grip = event.target.closest('.blog-row-grip');
        if (!grip) return;

        const row = grip.closest('[data-post-id]');
        if (row) startDrag(row, grip, event);
    });

    // Keyboard parity: the grip is a focusable button, arrows nudge the row.
    list.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;

        const grip = event.target.closest('.blog-row-grip');
        if (!grip) return;

        const row = grip.closest('[data-post-id]');
        const sibling = event.key === 'ArrowUp'
            ? row.previousElementSibling
            : row.nextElementSibling;

        if (!sibling || !sibling.matches('[data-post-id]')) return;

        event.preventDefault();
        list.insertBefore(row, event.key === 'ArrowUp' ? sibling : sibling.nextSibling);
        grip.focus();
        persist();
    });
}

if (!window.sapienstechRouter) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}
