/*
 * Small runtime handle on the active theme.
 *
 * The token map is server-rendered (partials/theme-vars), so this exists only
 * for in-browser experimentation — a theme preview for a prospect, or a
 * debugging aid. Nothing in the shipped pages calls it today; it previously read
 * `data-theme-preset`, an attribute that no longer exists, so it always returned
 * null.
 */

document.addEventListener('DOMContentLoaded', () => {
    const host = document.documentElement;

    window.SapienstechTheme = {
        /** The archetype name the server rendered, or null. */
        getArchetype: () => host.dataset.archetype || null,

        /** @deprecated kept so any old call site keeps resolving. */
        getPreset: () => host.dataset.archetype || null,

        setVariable: (name, value) => host.style.setProperty(`--${name}`, value),

        getVariable: (name) => getComputedStyle(host).getPropertyValue(`--${name}`).trim(),

        /**
         * Repaint the whole theme from a server-rendered variable map.
         * @param {Record<string, string>} vars custom property name => value
         */
        applyVars: (vars) => {
            Object.entries(vars).forEach(([name, value]) => host.style.setProperty(`--${name}`, value));
        },
    };
});
