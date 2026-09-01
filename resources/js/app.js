import './theme';

document.addEventListener('DOMContentLoaded', () => {
    // Close the filter popover when clicking anywhere outside it.
    const filterToggle = document.getElementById('filter-toggle');

    if (filterToggle) {
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.filter-wrap')) {
                filterToggle.checked = false;
            }
        });
    }
});
