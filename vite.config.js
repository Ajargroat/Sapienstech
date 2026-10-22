import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

/**
 * Every tenant lives on its own registrable domain (see IdentifyTenant), so the
 * dev server has to accept more than `localhost`. Vite >= 6 rejects unknown
 * `Host` headers, which otherwise shows up as a silent 403 on every asset for a
 * newly-added tenant.
 *
 * Set VITE_ALLOWED_HOSTS in .env to a comma-separated list. A leading dot makes
 * an entry a subdomain wildcard, e.g. `.sapienstech.test`.
 *
 * Vite does NOT put .env values on process.env - they are only readable through
 * loadEnv(). Reading process.env here yielded undefined, which silently dropped
 * allowedHosts entirely and 403'd every tenant asset.
 */
export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    const allowedHosts = (env.VITE_ALLOWED_HOSTS ?? '')
        .split(',')
        .map((host) => host.trim())
        .filter(Boolean);

    /*
     * The origin written into public/hot, i.e. the URL every @vite asset
     * resolves to in dev. A leading dot on a VITE_ALLOWED_HOSTS entry is a
     * subdomain wildcard, so the first entry is only usable as an origin once
     * its dot is dropped. Override with VITE_DEV_ORIGIN for another tenant.
     */
    const devOrigin =
        (env.VITE_DEV_ORIGIN || undefined) ??
        (allowedHosts[0] ? `http://${allowedHosts[0].replace(/^\./, '')}:5173` : undefined);

    /*
     * Which page origins may pull assets from the dev server. The page runs on
     * the tenant host and Laravel's port (see APP_URL), not on the Vite port,
     * so this has to name the pages - not the asset server. A leading dot in
     * VITE_ALLOWED_HOSTS is a subdomain wildcard, so `.x.test` becomes
     * `http://x.test:<port>`; extras may be listed in VITE_CORS_ORIGINS.
     */
    const appPort = new URL(env.APP_URL || 'http://localhost:8000').port || '80';
    const corsOrigins = [
        ...allowedHosts
            .filter((host) => !host.startsWith('.'))
            .map((host) => `http://${host}:${appPort}`),
        /^https?:\/\/.*\.test(:\d+)?$/, // any Valet/Herd-style .test tenant
        ...(env.VITE_CORS_ORIGINS ?? '')
            .split(',')
            .map((origin) => origin.trim())
            .filter(Boolean),
    ];

    return defineConfig({
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/features/consultant-schedule.js',
                    'resources/js/features/consultant-schedule-assistant.js',
                    'resources/js/features/consultant-exams.js',
                    'resources/js/features/consultant-blog-sort.js',
                    'resources/js/features/consultant-blog-modal.js',
                    'resources/js/features/blog-editor.js',
                    'resources/js/features/consultant-report-cards.js',
                    'resources/js/features/consultant-password-reveal.js',
                    'resources/js/features/student-exam-runner.js',
                    'resources/js/features/bulk-actions.js',
                    'resources/js/features/direct-chat.js',
                    'resources/js/features/theme-studio.js',
                    'resources/js/features/studio-standalone.js',

                    'resources/js/features/profile-edit.js',
                    'resources/js/features/student-dashboard.js',
                    'resources/js/features/student-report-card.js',
                    'resources/js/features/teacher-panel.js',
                    // Dev-only Agentation toolbar; guarded by import.meta.env.DEV,
                    // so it loads nothing in a production build.
                    'resources/js/features/agentation-dev.js',
                    'resources/js/app.js',

                    /*
                     * Lesson library (PLAN-a7c3): staff import/editor and the
                     * student reader. An unlisted entry never reaches the Vite
                     * manifest, and @vite then throws at render time instead of
                     * degrading â€” so a view that uses a file must add it here.
                     */
                    'resources/js/features/consultant-lesson-import.js',
                    'resources/js/features/consultant-lesson-editor.js',
                    'resources/js/features/student-lesson-reader.js',
                    // Operations console (/ops): our own PDF pipeline. Its chrome
                    // is platform UI, not tenant UI, so it ships its own sheet.
                    'resources/css/features/ops.css',
                ],
                refresh: true,
            }),
            /*
             * React islands (PLAN react-01). The laravel input array stays the
             * single source of truth for manifest entries: an island reuses the
             * existing feature entry, it is not a new @vite() target. Scoped to
             * .jsx/.tsx so the existing .js bundles are parsed exactly as
             * before; the shared primitives are .js (createElement) because the
             * Node test runner consumes them without a JSX transform.
             */
            react({ include: ['**/*.jsx', '**/*.tsx'] }),
            tailwindcss(),
        ],
        server: {
            // Binding all interfaces matters as much as `allowedHosts`: with the
            // default `localhost` bind, Vite listens on ::1 only, Laravel writes
            // `http://[::1]:5173` into public/hot, and every asset request then
            // arrives with `Host: [::1]` - which matches no allowed host and gets
            // a silent 403 for the whole tenant site.
            host: true,
            // laravel-vite-plugin writes `server.origin` verbatim into public/hot
            // (dist/index.js:114), so it must name a host the browser can reach.
            ...(devOrigin ? { origin: devOrigin } : {}),
            // `origin` ALSO becomes the default CORS allow-list (dist/index.js:58-59),
            // which would reject the page's own origin (the tenant on :8000) and block
            // every asset with ERR_FAILED. CORS must allow the tenant page origins.
            ...(allowedHosts.length ? { cors: { origin: corsOrigins } } : {}),
            ...(allowedHosts.length ? { allowedHosts } : {}),
        },
        build: {
            rollupOptions: {
                // The page router loads feature bundles with dynamic import() and
                // calls their default export. Vite's client builds default to
                // preserveEntrySignatures: false, which tree-shakes those exports
                // out of standalone entries and leaves every page without its init.
                preserveEntrySignatures: 'exports-only',
            },
        },
    });
});
