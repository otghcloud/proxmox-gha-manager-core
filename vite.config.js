import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/backend.scss',
                'resources/js/app.js',
                'resources/js/base/datatables.js',
            ],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            '@tabler': path.resolve(import.meta.dirname, 'node_modules/@tabler'),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
