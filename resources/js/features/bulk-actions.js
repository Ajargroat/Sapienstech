// Bulk Actions picker: "check all visible", the live selected count, and the
// select-all mode that dims the manual checkboxes. Filtering itself is plain
// links (server re-renders), so nothing here touches the query string.

export default function init() {
    document.querySelectorAll('[data-bulk-picker]').forEach((picker) => {
        const checks = [...picker.querySelectorAll('[data-student-check]')];
        const checkAll = picker.querySelector('[data-check-all]');
        const countEl = picker.querySelector('[data-picker-count]');
        const modeAll = picker.querySelector('[data-mode-all]');
        const modeExplicit = picker.querySelector('[data-mode-explicit]');
        const total = checks.length;

        const syncCheckAll = () => {
            if (!checkAll) return;
            const selected = checks.filter((c) => c.checked).length;
            checkAll.checked = selected > 0 && selected === total;
            checkAll.indeterminate = selected > 0 && selected < total;
        };

        checks.forEach((c) => c.addEventListener('change', syncCheckAll));

        checkAll?.addEventListener('change', () => {
            checks.forEach((c) => { c.checked = checkAll.checked; });
            syncCheckAll();
        });

        const setMode = (all) => picker.classList.toggle('is-select-all', all);

        modeAll?.addEventListener('change', () => setMode(true));
        modeExplicit?.addEventListener('change', () => setMode(false));

        // Restore the mode on reload (old() may pre-check select_all).
        if (modeAll?.checked) setMode(true);

        // Show how many are actually selected in the count badge.
        const updateCount = () => {
            if (!countEl) return;
            const selected = checks.filter((c) => c.checked).length;
            countEl.textContent = modeAll?.checked
                ? String(total)
                : String(selected);
        };

        checks.forEach((c) => c.addEventListener('change', updateCount));
        modeAll?.addEventListener('change', updateCount);
        modeExplicit?.addEventListener('change', updateCount);
    });
}
