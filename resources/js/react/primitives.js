/*
 * Shared, presentation-only React primitives for tenant surfaces.
 *
 * They carry no colour, spacing or direction literals. Visual meaning comes
 * from the semantic class the caller passes (those CSS rules resolve the
 * tenant token sheet), or — where no class exists — from a named role in
 * ./tokens.js via `tone`. A primitive that ever needs a hex value is the wrong
 * primitive.
 *
 * Kept as .js + createElement on purpose: `npm run test:studio` runs the raw
 * Node test runner, which cannot parse JSX. JSX is enabled for future .jsx
 * islands (see vite.config.js) but the primitives that the jsdom suite imports
 * must stay plain JavaScript.
 */

import { createElement } from 'react';
import { token } from './tokens.js';

function attributes({ className, tone, style, rest }) {
    const next = {};
    if (className) next.className = className;
    Object.assign(next, rest);
    const resolvedStyle = tone ? { color: token(tone), ...style } : style;
    if (resolvedStyle) next.style = resolvedStyle;
    return next;
}

/** A text element (default <p>) with no built-in visual opinion. */
export function Text({ as: Tag = 'p', className, tone, style, children, ...rest }) {
    return createElement(Tag, attributes({ className, tone, style, rest }), children);
}

/** A block element (default <div>) for stacking island content. */
export function Stack({ as: Tag = 'div', className, tone, style, children, ...rest }) {
    return createElement(Tag, attributes({ className, tone, style, rest }), children);
}

/** The muted "nothing here yet" line used by island threads. */
export function EmptyState({ className, tone, style, children, ...rest }) {
    return createElement('p', attributes({ className, tone, style, rest }), children);
}
