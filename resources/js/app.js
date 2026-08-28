import './theme';

document.addEventListener('DOMContentLoaded', () => {
    // Per-row "Actions" dropdown on the student table.
    const openDropdown = (dropdown) => {
        document
            .querySelectorAll('.actions-dropdown.open')
            .forEach((el) => {
                if (el !== dropdown) el.classList.remove('open');
            });
        dropdown.classList.toggle('open');
    };

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('.actions-toggle');

        if (toggle) {
            const dropdown = toggle.parentElement.querySelector('.actions-dropdown');
            if (dropdown) {
                event.stopPropagation();
                openDropdown(dropdown);
            }
            return;
        }

        if (!event.target.closest('.actions-dropdown')) {
            document
                .querySelectorAll('.actions-dropdown.open')
                .forEach((el) => el.classList.remove('open'));
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document
                .querySelectorAll('.actions-dropdown.open')
                .forEach((el) => el.classList.remove('open'));
        }
    });
});
