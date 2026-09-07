import './theme';
import './landing';
import { onPageRender } from './page-router';

// Closing the popover on an outside click is delegated once for the whole
// shell, so it keeps working across router swaps without stacking listeners.
document.addEventListener('click', (event) => {
    if (event.target.closest?.('.filter-wrap')) return;
    document.querySelectorAll('.filter-toggle-input:checked').forEach((input) => {
        input.checked = false;
    });
});

/*
 * Topnav user dropdown (settings hub). Delegated at document level like the
 * filter popover: the nav lives outside the router region and persists across
 * page swaps, so one listener covers every page.
 */
const closeTopnavDropdown = (wrap) => {
    wrap.classList.remove('is-open');
    wrap.querySelector('button')?.setAttribute('aria-expanded', 'false');
};

document.addEventListener('click', (event) => {
    const toggle = event.target.closest?.('[data-topnav-dropdown] > button');
    const openWrap = document.querySelector('[data-topnav-dropdown].is-open');

    if (openWrap && !openWrap.contains(event.target)) closeTopnavDropdown(openWrap);

    if (toggle) {
        const wrap = toggle.closest('[data-topnav-dropdown]');
        if (wrap.classList.contains('is-open')) {
            closeTopnavDropdown(wrap);
        } else {
            wrap.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        }
    } else if (openWrap && event.target.closest('.topnav-dropdown-link')) {
        closeTopnavDropdown(openWrap);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('[data-topnav-dropdown].is-open').forEach(closeTopnavDropdown);
});

/*
 * Success flashes are transient notifications, not permanent page
 * furniture: they fade out and remove themselves a few seconds after
 * appearing. Error flashes stay put — role="alert" means the user has to
 * act on them. Armed once at load (covers native-navigation pages like the
 * student portal) and through the router (covers swapped-in panel pages).
 */
const dismissFlash = (flash) => {
    if (flash.dataset.flashArmed) return;
    flash.dataset.flashArmed = '1';
    setTimeout(() => {
        flash.classList.add('is-leaving');
        setTimeout(() => flash.remove(), 500);
    }, 4000);
};

const armFlashDismissal = (root) =>
    root.querySelectorAll('.settings-flash--success').forEach(dismissFlash);

armFlashDismissal(document);
onPageRender(armFlashDismissal);

/*
 * Filter popover carousel: general / exam / report-card / schedule pages.
 * Registered through the router so it boots on the first page and on every
 * swapped-in page; the returned cleanup detaches the window listeners.
 */
onPageRender((region) => {
    const carousel = region.querySelector('[data-filter-carousel]');
    if (!carousel) return;

    const track = carousel.querySelector('.filter-carousel-track');
    const pages = Array.from(carousel.querySelectorAll('.filter-page'));
    const title = region.querySelector('[data-filter-title]');
    const prevBtn = region.querySelector('[data-filter-prev]');
    const nextBtn = region.querySelector('[data-filter-next]');
    const dots = Array.from(region.querySelectorAll('[data-filter-dot]'));
    let current = 0;

    const activate = (index) => {
        pages.forEach((page, i) => {
            const active = i === index;
            page.setAttribute('aria-hidden', String(!active));
            page.querySelectorAll('select, input, button, a').forEach((el) => {
                el.tabIndex = active ? 0 : -1;
            });
        });
    };

    const size = () => {
        carousel.style.height = pages[current].offsetHeight + 'px';
    };

    const setPage = (index) => {
        const target = Math.max(0, Math.min(index, pages.length - 1));
        if (target === current) return;
        current = target;

        track.style.setProperty('--filter-page', current);
        size();
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
        prevBtn.classList.toggle('is-disabled', current === 0);
        nextBtn.classList.toggle('is-disabled', current === pages.length - 1);

        // Crossfade the title while the track slides underneath it.
        title.classList.add('is-swapping');
        setTimeout(() => {
            title.textContent = pages[current].dataset.filterName;
            title.classList.remove('is-swapping');
        }, 140);

        activate(current);
    };

    prevBtn.addEventListener('click', () => setPage(current - 1));
    nextBtn.addEventListener('click', () => setPage(current + 1));
    dots.forEach((dot) =>
        dot.addEventListener('click', () => setPage(Number(dot.dataset.filterDot)))
    );

    // Wheel over the open menu flips between filter pages.
    const popover = carousel.closest('.filter-popover');
    let wheelLock = false;

    const onWheel = (event) => {
        const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY)
            ? event.deltaX
            : event.deltaY;

        const atEdge =
            (delta > 0 && current === pages.length - 1) ||
            (delta < 0 && current === 0);

        // Below threshold, mid-animation, or at a carousel edge →
        // let the page scroll through normally.
        if (Math.abs(delta) < 6 || atEdge || wheelLock) return;

        event.preventDefault();
        wheelLock = true;
        window.setTimeout(() => { wheelLock = false; }, 420);
        setPage(current + (delta > 0 ? 1 : -1));
    };

    popover.addEventListener('wheel', onWheel, { passive: false });
    window.addEventListener('resize', size);

    size();
    activate(0);

    return () => {
        popover.removeEventListener('wheel', onWheel);
        window.removeEventListener('resize', size);
    };
});
