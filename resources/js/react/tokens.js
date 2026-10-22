/*
 * Theme-token roles for React islands.
 *
 * The values are the CSS custom properties declared by
 * `resources/views/partials/theme-vars.blade.php` (resolved from
 * App\Support\ThemeTokens::VARS). A React island must never carry a literal
 * colour: it either wears an existing semantic class (whose CSS resolves a
 * token) or references a role from this map. That is the same brand contract
 * the Blade surface follows, so a tenant reskin reaches an island for free.
 *
 * Direction is not set here: the shell's <html dir="rtl"> owns it.
 */

export const TOKENS = Object.freeze({
    // colours
    text: 'var(--c-text)',
    heading: 'var(--c-heading)',
    muted: 'var(--c-muted)',
    subtle: 'var(--c-subtle)',
    surface: 'var(--c-surface)',
    surfaceAlt: 'var(--c-surface-alt)',
    border: 'var(--c-border)',
    primary: 'var(--c-primary)',
    onPrimary: 'var(--c-on-primary)',
    success: 'var(--c-success)',
    danger: 'var(--c-danger)',

    // shape / type
    radiusMd: 'var(--radius-md)',
    radiusLg: 'var(--radius-lg)',
    fontBody: 'var(--font-body)',
});

/**
 * Resolve a token role name to its CSS custom-property reference.
 * Throws on an unknown role so a typo fails loudly instead of rendering an
 * invalid declaration that silently falls back.
 */
export function token(name) {
    const value = TOKENS[name];
    if (!value) throw new Error(`Unknown theme token role: ${name}`);
    return value;
}
