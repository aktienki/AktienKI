import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',

                'resources/css/welcome.css',
                'resources/css/welcome-sections.css',
                'resources/css/welcome-animations.css',

                'resources/js/app.js',

                'resources/js/welcome.js',
                'resources/js/welcome-sections.js',

                // Passkeys
                'resources/js/passkeys.js',
            ],

            refresh: true,

            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),

        tailwindcss(),
    ],

    server: {
        cors: true,

        watch: {
            ignored: [
                '**/storage/framework/views/**',
            ],
        },
    },
});
