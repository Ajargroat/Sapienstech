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
 * Upload tiles (consultant/partials/upload-tile): the native file input is
 * a transparent layer over the dashed box, so echo the picked file's name
 * into the tile's text line and mark the box as filled. Delegated at
 * document level: tiles appear on settings-studio pages and inside the
 * blog dialog's injected fragments alike, none of which re-run page bundles.
 */
document.addEventListener('change', (event) => {
    const input = event.target.closest?.('.settings-upload-input');
    if (!input) return;

    const tile = input.closest('.settings-upload');
    const label = tile?.querySelector('[data-upload-name]');
    if (!label) return;

    const file = input.files?.[0];
    label.textContent = file ? file.name : (label.dataset.default ?? '');
    tile.classList.toggle('has-file', Boolean(file));
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
 * The filter popover's forms mirror each other.
 *
 * Every panel form (and the dashboard search box) carries the whole filter
 * stack, so applying on one page never drops filters set on another and a
 * bulk assignment POST re-derives exactly the set the UI displayed. Popover
 * DOM survives the router's partial swaps, so server-rendered hidden values
 * go stale as soon as anything is clicked; refresh every mirror from the
 * live control state right before submit. Delegated once for the whole
 * shell, like the outside-click dismissal above.
 */
const FILTER_FIELDS = [
    'search', 'grade', 'gender', 'major', 'sort',
    'exam_status', 'exam_lesson', 'exam_type',
    'report_source', 'report_status',
    'schedule_day', 'schedule_done',
];

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches?.('form[data-filter-sync]')) return;

    const scope = form.closest('[data-filter-popover]') ?? document;
    const query = new URLSearchParams(window.location.search);

    form.querySelectorAll('input[type="hidden"]').forEach((input) => {
        if (!FILTER_FIELDS.includes(input.name)) return;
        // Real (non-hidden) controls own their own field.
        if (form.querySelector(`input:not([type="hidden"])[name="${input.name}"]`)) return;

        if (input.name === 'search') {
            const searchInput = document.querySelector('.search-reveal-input');
            input.value = searchInput
                ? searchInput.value.trim()
                : (query.get('search') ?? '');
            return;
        }

        const checked = scope.querySelector(
            `input[name="${input.name}"]:checked:not([type="hidden"])`
        );
        input.value = checked ? checked.value : (query.get(input.name) ?? '');
    });
}, true); // capture: must run before the page-router serializes the form

/*
 * Filter popover carousel: general / exam / report-card / schedule pages.
 * Registered through the router so it boots on the first page and on every
 * swapped-in page; the returned cleanup detaches the window listeners.
 *
 * The popover itself lives outside the swapped regions, so this behavior
 * re-runs against the same DOM on every partial swap: the active page is
 * read back from data-filter-current instead of resetting to 0.
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
    const applyBtn = region.querySelector('[data-filter-apply]');
    const pageCount = pages.length;
    let current = Math.max(0, Math.min(
        Number(carousel.dataset.filterCurrent ?? carousel.dataset.filterPage ?? 0) || 0,
        pageCount - 1,
    ));

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

    // One shared "اعمال" button drives whichever panel's GET filter form is
    // currently visible (HTML5 form attribute on a button outside the form).
    const setApplyTarget = () => {
        if (!applyBtn) return;
        const form = pages[current].querySelector('form[data-filter-form]');
        applyBtn.setAttribute('form', form ? form.id : '');
        applyBtn.hidden = !form;
    };

    const setPage = (index) => {
        const target = Math.max(0, Math.min(index, pageCount - 1));
        if (target === current) return;
        current = target;
        carousel.dataset.filterCurrent = current;

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
        setApplyTarget();
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
    activate(current);
    dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
    prevBtn.classList.toggle('is-disabled', current === 0);
    nextBtn.classList.toggle('is-disabled', current === pageCount - 1);
    title.textContent = pages[current].dataset.filterName;
    setApplyTarget();

    return () => {
        popover.removeEventListener('wheel', onWheel);
        window.removeEventListener('resize', size);
    };
});
