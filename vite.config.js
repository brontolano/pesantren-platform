import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/pesantren/theme.css',
                // Tema EKSTERNAL (multi-build): tiap tema = build CSS terpisah,
                // dipilih per-tenant saat runtime (PanelTheme::cssEntry()).
                'resources/css/filament/pesantren/theme-brisk.css',
                'resources/css/filament/pesantren/theme-nord.css',
                'resources/css/filament/pesantren/theme-moonlight.css',
                'resources/css/filament/pesantren/theme-neobrutalism.css',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
