import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/features/consultant-schedule.js',
                'resources/js/features/consultant-exams.js',
                'resources/js/features/consultant-exam-runner.js',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
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
