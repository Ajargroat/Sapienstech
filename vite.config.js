import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * Every tenant lives on its own registrable domain (see IdentifyTenant), so the
 * dev server has to accept more than `localhost`. Vite >= 6 rejects unknown
 * `Host` headers, which otherwise shows up as a silent 403 on every asset for a
 * newly-added tenant.
 *
 * Set VITE_ALLOWED_HOSTS in .env to a comma-separated list. A leading dot makes
 * an entry a subdomain wildcard, e.g. `.sapienstech.test`.
 */
const allowedHosts = (process.env.VITE_ALLOWED_HOSTS ?? '')
    .split(',')
    .map((host) => host.trim())
    .filter(Boolean);

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/features/consultant-schedule.js',
                'resources/js/features/consultant-exams.js',
                'resources/js/features/consultant-exam-runner.js',
                'resources/js/features/student-dashboard.js',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: allowedHosts.length ? { allowedHosts } : {},
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
