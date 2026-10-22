/*
 * Agentation: development-only visual feedback toolbar.
 *
 * A floating bubble in the bottom-right corner. Click it, click any element on
 * the page, leave a note — then ask the agent to read it over MCP
 * (`agentation` server, started with `npx -y agentation-mcp server`).
 *
 * Why a separate module instead of inlining it in app.js:
 *  - app.js is a Vite entry that every shell loads. It has no imports of its
 *    own beyond the shell modules, and this repo's build tree-shakes entry
 *    exports (preserveEntrySignatures: 'exports-only'). Keeping react and
 *    agentation behind a dynamic import in a *separate* file means app.js
 *    stays a thin aggregator and the toolbar's deps never join the entry.
 *  - vite.config.js lists it as an input so @vite can resolve it, but the
 *    guard below returns before anything loads in any build.
 *
 * The toolbar is editor chrome, so it must never consume tenant theme tokens
 * or appear for a tenant: `import.meta.env.DEV` is true only on the Vite dev
 * server, so a production build is a no-op even if this file is imported.
 */
if (import.meta.env.DEV && !document.getElementById('agentation-root')) {
    Promise.all([
        import('react'),
        import('react-dom/client'),
        import('agentation'),
    ])
        .then(([React, ReactDOM, AgentationModule]) => {
            const container = document.createElement('div');
            container.id = 'agentation-root';
            document.body.appendChild(container);

            /*
             * Agentation anchors its portal at `inset: 0 auto auto 0` and lets
             * the toolbar inherit that position, relying on `margin-left: auto`
             * to push its 337px body to the far end. That only lands bottom-
             * right in an LTR page: this app renders `<html dir="rtl">`, which
             * flips the inline axis and parks the bubble at the TOP-LEFT, behind
             * the sticky header. Pin the portalled toolbar with physical
             * right/bottom offsets instead, so writing direction cannot move it.
             */
            const style = document.createElement('style');
            style.textContent = `
                [data-agentation-portal] > * {
                    position: fixed !important;
                    inset: auto 20px 20px auto !important;
                    z-index: 2147483000 !important;
                }
            `;
            document.head.appendChild(style);

            ReactDOM.createRoot(container).render(
                React.createElement(AgentationModule.Agentation, {
                    // Must match the HTTP port the `agentation-mcp` server
                    // listens on. Without this the toolbar only saves notes to
                    // localStorage and the agent can never read them.
                    endpoint: 'http://localhost:4747',
                })
            );
        })
        .catch(console.error);
}
