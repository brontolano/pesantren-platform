import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { clearSensitiveStorageIfGuest } from './Shared/authStorage';

// Hygiene HP bersama (F0): tiap render halaman TANPA user login (pasca-logout,
// full page load setelah POST /keluar, atau sesi kedaluwarsa) → hapus kunci
// localStorage sensitif (token API disiplin, cache izin gerbang).
router.on('navigate', (event) => {
    clearSensitiveStorageIfGuest(event.detail.page?.props);
});

createInertiaApp({
    title: (title) => title || '',
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
