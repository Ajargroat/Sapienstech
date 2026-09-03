/**
 * App-shell page router for the consultant area.
 *
 * Every consultant screen is its own Blade document, so moving between them
 * used to mean a full reload: white flash, topnav rebuild, theme variables
 * re-applied, feature bundles executed from scratch. This keeps the shell
 * alive and swaps only <main id="app-content">, animating the change with the
 * View Transitions API where the browser supports it and with a CSS enter
 * animation (plus a staggered card reveal) where it does not.
 *
 * Responses are prefetched on hover/focus, so a click normally renders from
 * cache and there is nothing to wait for.
 *
 * Contract with the rest of the app:
 *   - A page bundle `export default function init(region)` and may return a
 *     cleanup function. The router imports it once and calls it again every
 *     time its page renders.
 *   - `window.sapienstechRouter` exists before any body script runs, which is
 *     how bundles know they must not self-initialize.
 *   - Anything can opt out with `data-router="off"`. Links outside the first
 *     path segment (login, public site, student portal) are never intercepted,
 *     and any response without an app shell falls back to a real navigation.
 */

const REGION_SELECTOR = '#app-content';
const CACHE_LIMIT = 10;
const PROGRESS_DELAY = 140;
const ENTER_DURATION = 420;
const PREFETCH_SELECTOR =
    '[data-prefetch], .pager-next:not(.is-disabled), .pager-prev:not(.is-disabled)';

const [scope] = window.location.pathname.split('/').filter(Boolean);

const pages = new Map();    // page url -> Promise<page>
const bundles = new Map();  // bundle url -> exported init (null when it has none)
const injected = new Set(); // classic script urls already added to <head>
const teardown = [];        // cleanup callbacks for the live page
const shared = [];          // behaviors registered through onPageRender()

let current = new URL(window.location.href);
let queue = Promise.resolve();
let bar = null;
let barTimer = null;

window.sapienstechRouter = {
    preload: (href) => preload(href),
    navigate: (href) => navigate(() => load(new URL(href, window.location.href))),
};

/** Thrown when a response cannot be swapped in; the caller reloads for real. */
class BailOut extends Error {
    constructor(href, reason) {
        super(`page-router: falling back to a normal navigation (${reason})`);
        this.href = href;
    }
}

/* ------------------------------------------------------------------ guards */

const reducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
const hasViewTransitions = () => typeof document.startViewTransition === 'function';

function inScope(url) {
    if (!url || !scope || url.origin !== window.location.origin) return false;
    return url.pathname === `/${scope}` || url.pathname.startsWith(`/${scope}/`);
}

function resolveLink(raw) {
    if (!raw) return null;
    try {
        return new URL(raw, window.location.href);
    } catch {
        return null;
    }
}

function call(fn) {
    try {
        return fn();
    } catch (error) {
        console.error('[page-router]', error);
        return null;
    }
}

/* ------------------------------------------------------------- fetch/parse */

const readScripts = (el) =>
    [...el.querySelectorAll('script')].map((s) => ({
        src: s.getAttribute('src'),
        module: s.type === 'module',
    }));

async function toPage(response, fallbackHref) {
    const href = response.url || fallbackHref;

    if (!response.ok) throw new BailOut(href, `HTTP ${response.status}`);
    if (!(response.headers.get('content-type') || '').includes('text/html')) {
        throw new BailOut(href, 'non-HTML response');
    }

    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
    const region = doc.querySelector(REGION_SELECTOR);
    if (!region) throw new BailOut(href, 'target has no app shell');

    return {
        url: new URL(href),
        html: region.innerHTML,
        title: doc.title,
        scripts: readScripts(region),
        // The server already resolved which nav item is active for this route;
        // copying that beats re-implementing `request()->routeIs()` in JS.
        activeNav: [...doc.querySelectorAll('.topnav-link.active')]
            .map((a) => resolveLink(a.getAttribute('href'))?.href)
            .filter(Boolean),
    };
}

function load(url) {
    const key = url.href;

    if (!pages.has(key)) {
        const pending = fetch(key, {
            credentials: 'same-origin',
            redirect: 'follow',
            headers: { Accept: 'text/html' },
        }).then((response) => toPage(response, key));

        pages.set(key, pending);
        pending.catch(() => pages.delete(key));
        while (pages.size > CACHE_LIMIT) pages.delete(pages.keys().next().value);
    }

    return pages.get(key);
}

/* ---------------------------------------------------------------- bundles */

async function prepare(scripts) {
    // Classic scripts first: the schedule page renders lucide icons while it
    // boots, so window.lucide has to exist before its init() runs.
    await Promise.all(
        scripts.filter((s) => s.src && !s.module).map((s) => injectClassic(s.src))
    );

    const run = [];
    for (const s of scripts) {
        if (!s.src || !s.module) continue;
        const key = new URL(s.src, window.location.href).href;

        if (!bundles.has(key)) {
            // @vite-ignore: the specifier is a runtime URL from the page's own
            // @vite tag, so Vite must not try to resolve it at build time.
            const mod = await import(/* @vite-ignore */ key);
            bundles.set(key, typeof mod.default === 'function' ? mod.default : null);
        }

        const init = bundles.get(key);
        if (init) run.push(init);
    }
    return run;
}

function injectClassic(src) {
    const key = new URL(src, window.location.href).href;
    if (injected.has(key)) return Promise.resolve();
    injected.add(key);

    return new Promise((resolve) => {
        const el = document.createElement('script');
        el.src = key;
        el.onload = resolve;
        el.onerror = resolve; // a dead CDN must not block the page
        document.head.appendChild(el);
    });
}

/** innerHTML-inserted <script> nodes are inert; clone them to make them run. */
function reviveInlineScripts(region) {
    region.querySelectorAll('script:not([src])').forEach((old) => {
        const el = document.createElement('script');
        for (const attr of old.attributes) el.setAttribute(attr.name, attr.value);
        el.textContent = old.textContent;
        old.replaceWith(el);
    });
}

function runPage(region, run, { animate }) {
    reviveInlineScripts(region);

    for (const init of run) {
        const off = call(() => init(region));
        if (typeof off === 'function') teardown.push(off);
    }
    for (const behavior of shared) {
        const off = call(() => behavior(region));
        if (typeof off === 'function') teardown.push(off);
    }

    if (animate) enter(region);
    prefetchLikely(region);
    document.dispatchEvent(new CustomEvent('page:load', { detail: { url: current.href } }));
}

function stopPage() {
    for (const off of teardown.splice(0)) call(off);
}

/* ----------------------------------------------------------------- render */

function syncNav(page) {
    const active = new Set(page.activeNav);

    for (const link of document.querySelectorAll('.topnav-link')) {
        const on = active.has(link.href);
        link.classList.toggle('active', on);
        if (on) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    }
}

function enter(region) {
    for (const list of region.querySelectorAll('[data-stagger]')) {
        [...list.children]
            .slice(0, 18)
            .forEach((child, i) => child.style.setProperty('--stagger-index', String(i)));
    }

    region.classList.remove('is-page-enter');
    void region.offsetWidth; // restart the animation when re-entering a page
    region.classList.add('is-page-enter');
    window.setTimeout(() => region.classList.remove('is-page-enter'), ENTER_DURATION);
}

async function render(page, { mode = 'push', scroll = null } = {}) {
    const region = document.querySelector(REGION_SELECTOR);
    const run = await prepare(page.scripts);

    const swap = () => {
        if (mode !== 'none') {
            // Remember where the outgoing entry was so Back can return to it.
            window.history.replaceState({ router: true, y: window.scrollY }, '', window.location.href);
        }

        stopPage();
        region.innerHTML = page.html;
        document.title = page.title;
        syncNav(page);
        current = page.url;

        if (mode === 'push') window.history.pushState({ router: true, y: 0 }, '', page.url.href);
        if (mode === 'replace') window.history.replaceState({ router: true, y: 0 }, '', page.url.href);
        window.scrollTo(0, scroll ?? 0);

        // The View Transition snapshots the page once this callback settles, so
        // every DOM mutation (Jalali dates, carousel height, …) has to happen
        // here rather than in a later microtask.
        runPage(region, run, { animate: !hasViewTransitions() });
    };

    if (hasViewTransitions() && !reducedMotion()) {
        await document.startViewTransition(swap).finished;
    } else {
        swap();
    }
}

/* -------------------------------------------------------------- navigation */

function navigate(source, options = {}) {
    showProgress();

    const task = queue
        .then(source, source)
        .then(async (page) => {
            // Re-applying the filters you already have shouldn't re-render.
            if (options.mode === 'push' && page.url.href === current.href) return;
            await render(page, options);
        });

    queue = task.catch(() => {});

    task
        .catch((error) => {
            console.error('[page-router]', error?.message ?? error);
            if (error instanceof BailOut && error.href) window.location.assign(error.href);
        })
        .finally(hideProgress);

    return task;
}

async function postForm(form, action, method, body) {
    const buttons = [...form.querySelectorAll('button[type="submit"], button:not([type])')];
    buttons.forEach((b) => {
        b.dataset.wasDisabled = b.disabled ? '1' : '';
        b.disabled = true;
    });

    try {
        const response = await fetch(action.href, {
            method,
            body,
            credentials: 'same-origin',
            redirect: 'follow',
            headers: { Accept: 'text/html' },
        });

        try {
            return await toPage(response, action.href);
        } catch (error) {
            // A download or a redirect out of the shell: go there with the
            // browser instead of replaying the POST.
            throw new BailOut(response.url || action.href, error.message);
        }
    } catch (error) {
        if (!(error instanceof BailOut)) console.error('[page-router] submit failed:', error);
        throw error;
    } finally {
        buttons.forEach((b) => {
            if (!b.dataset.wasDisabled) b.disabled = false;
            delete b.dataset.wasDisabled;
        });
    }
}

/* ------------------------------------------------------------- interception */

function onClick(event) {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
    ) {
        return;
    }

    const link = event.target.closest?.('a[href]');
    if (!link) return;
    if (link.dataset.router === 'off') return;
    if (link.target && link.target !== '_self') return;
    if (link.hasAttribute('download') || link.relList?.contains('external')) return;

    const url = resolveLink(link.getAttribute('href'));
    if (!url || url.hash || !inScope(url)) return; // in-page anchors stay native

    if (url.href === current.href) {
        event.preventDefault();
        window.scrollTo({ top: 0, behavior: reducedMotion() ? 'auto' : 'smooth' });
        return;
    }

    event.preventDefault();
    navigate(() => load(url));
}

function onSubmit(event) {
    if (event.defaultPrevented) return;

    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.router === 'off') return;

    const action = resolveLink(form.getAttribute('action') || window.location.href);
    if (!inScope(action)) return;

    const method = (form.getAttribute('method') || 'get').toUpperCase();
    const data = new FormData(form);

    event.preventDefault();

    if (method === 'GET') {
        // Search/filter/pager forms. Empty values are the default state, so
        // dropping them keeps the URL bar clean and the back stack short.
        const url = new URL(action.href);
        const params = new URLSearchParams();
        for (const [name, value] of data) {
            if (typeof value === 'string' && value.trim() !== '') params.append(name, value);
        }
        url.search = params.toString();
        navigate(() => load(url));
        return;
    }

    navigate(() => postForm(form, action, method, data), { mode: 'replace' });
}

function onPointer(event) {
    const link = event.target.closest?.('a[href]');
    if (link) preload(link.getAttribute('href'));
}

function onPopState(event) {
    const url = new URL(window.location.href);
    if (!inScope(url)) return;
    navigate(() => load(url), { mode: 'none', scroll: Number(event.state?.y ?? 0) });
}

/* --------------------------------------------------------------- prefetch */

function preload(raw) {
    const url = resolveLink(raw);
    if (!url || url.hash || !inScope(url) || url.href === current.href) return;
    load(url).catch(() => {});
}

function prefetchLikely(region) {
    const run = () => region.querySelectorAll(PREFETCH_SELECTOR).forEach((a) => preload(a.href));

    if ('requestIdleCallback' in window) window.requestIdleCallback(run, { timeout: 2000 });
    else window.setTimeout(run, 600);
}

/* --------------------------------------------------------------- progress */

function showProgress() {
    if (barTimer !== null) return;
    barTimer = window.setTimeout(() => {
        barTimer = null;
        if (!bar) {
            bar = document.createElement('div');
            bar.className = 'page-progress';
            bar.setAttribute('aria-hidden', 'true');
            bar.append(document.createElement('i'));
            document.body.appendChild(bar);
        }
        bar.classList.add('is-active');
    }, PROGRESS_DELAY);
}

function hideProgress() {
    if (barTimer !== null) {
        window.clearTimeout(barTimer);
        barTimer = null;
    }
    bar?.classList.remove('is-active');
}

/* ------------------------------------------------------------------- boot */

/**
 * Register a behavior that must exist on every page (the filter popover is the
 * current example). It runs for the page already on screen and again after
 * each swap; return a function to undo window-level listeners.
 */
export function onPageRender(fn) {
    shared.push(fn);
}

function boot() {
    const region = document.querySelector(REGION_SELECTOR);
    if (!region) return; // public/student/login pages keep native navigation

    document.addEventListener('click', onClick);
    document.addEventListener('submit', onSubmit);
    document.addEventListener('pointerover', onPointer, { passive: true });
    document.addEventListener('focusin', onPointer, { passive: true });
    window.addEventListener('popstate', onPopState);
    window.history.replaceState({ router: true, y: 0 }, '', window.location.href);

    // The browser already executed this page's <script type="module"> tags, so
    // import() resolves from the module map and we simply run their init().
    queue = queue.then(async () => {
        const run = await prepare(readScripts(region));
        runPage(region, run, { animate: !reducedMotion() });
    });
}

boot();
