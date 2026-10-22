/*
 * React island mounting helper.
 *
 * Contract with the page router (resources/js/page-router.js): a feature entry
 * still `export default function init(region)` and still returns an optional
 * cleanup. The island is mounted *inside* the server-rendered region; it never
 * owns the page skeleton. The router is untouched by this module.
 *
 * Rendering is flushed synchronously. The vanilla islands this replaces built
 * their DOM with appendChild/replaceChildren in the same tick as the fetch or
 * click that produced the data, and the server-rendered containers are revealed
 * in that same step (the week-notes panel must never appear empty while React
 * schedules a render). `flushSync` keeps that ordering, which also makes the
 * jsdom parity suite deterministic without timers.
 */

import { createElement } from 'react';
import { flushSync } from 'react-dom';
import { createRoot } from 'react-dom/client';

const roots = new WeakMap();

/**
 * Mount (or update) a React component on an existing container.
 *
 * @param {Element|null} container server-rendered mount point
 * @param {Function} Component React component
 * @param {object} props first render props
 * @returns {{update: Function, unmount: Function}|null} null when the
 *          container is absent (an island must degrade, never throw)
 */
export function mountIsland(container, Component, props = {}) {
    if (!container) return null;

    let entry = roots.get(container);
    if (!entry) {
        entry = { root: createRoot(container), Component, props: { ...props }, mounted: true };
        roots.set(container, entry);
    }

    entry.Component = Component;
    entry.props = { ...props };

    const render = (next) => {
        entry.props = { ...entry.props, ...next };
        flushSync(() => entry.root.render(createElement(entry.Component, entry.props)));
    };

    render({});

    return {
        update(next = {}) {
            if (entry.mounted) render(next);
        },
        unmount() {
            if (!entry.mounted) return;
            entry.mounted = false;
            entry.root.unmount();
            roots.delete(container);
        },
    };
}
