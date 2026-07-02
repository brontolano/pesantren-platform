<script setup>
// Cangkang Portal SPMB — didesain seperti APLIKASI ANDROID NATIVE (untuk TWA):
// top app bar + bottom navigation fixed, konten edge-to-edge, aman dari notch &
// gesture bar (env safe-area). Tema white-label dari page.props.branding
// (middleware ShareSpmbPageData): warna lewat CSS var --spmb-primary/secondary.
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppBar from '@/spmb/components/AppBar.vue';
import BottomNav from '@/spmb/components/BottomNav.vue';

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    showBack: { type: Boolean, default: false },
    backHref: { type: String, default: '/spmb' },
    bottomNav: { type: Boolean, default: true },
    brandHeader: { type: Boolean, default: false },
});

const page = usePage();
const branding = computed(() => page.props.branding ?? {});
const flash = computed(() => page.props.flash ?? {});

const brandName = computed(() => branding.value.name || 'Pesantren');
const themeVars = computed(() => ({
    // Default Pixio (emerald). Tenant white-label tetap menimpa via branding.
    '--spmb-primary': branding.value.primaryColor || '#059669',
    '--spmb-secondary': branding.value.secondaryColor || '#047857',
}));
const faviconUrl = computed(() => branding.value.faviconUrl || branding.value.icon192Url || branding.value.logoUrl || '/favicon.ico');

// Elevasi app bar saat konten di-scroll (efek Material).
const elevated = ref(false);
const onScroll = () => { elevated.value = window.scrollY > 4; };

// Banner flash bisa ditutup; muncul lagi saat ada pesan baru.
const dismissed = ref(false);
watch(
    () => [flash.value.success, flash.value.error, flash.value.info],
    () => { dismissed.value = false; },
);

onMounted(async () => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (typeof window === 'undefined' || !window.location.pathname.startsWith('/spmb')) {
        return;
    }
    const { registerSpmbPwa } = await import('@/spmb/registerPwa.js');
    void registerSpmbPwa();
});

onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <Head>
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
        <link rel="manifest" href="/spmb/manifest.json">
        <meta name="theme-color" :content="branding.primaryColor || '#059669'">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="icon" :href="faviconUrl">
        <link rel="apple-touch-icon" :href="branding.icon192Url || faviconUrl">
    </Head>

    <div
        :style="themeVars"
        class="spmb-app min-h-screen bg-[#fffaf3] text-stone-900 antialiased"
    >
        <AppBar
            :title="title"
            :subtitle="subtitle"
            :show-back="showBack"
            :back-href="backHref"
            :brand-header="brandHeader"
            :logo-url="branding.logoUrl || ''"
            :brand-name="brandName"
            :elevated="elevated"
        >
            <template #actions>
                <slot name="app-bar-actions" />
            </template>
        </AppBar>

        <!-- Konten — diberi padding agar tak tertutup app bar (atas) & bottom nav (bawah). -->
        <main
            class="mx-auto w-full max-w-screen-sm px-4"
            :style="{
                paddingTop: 'calc(3.5rem + env(safe-area-inset-top) + 0.75rem)',
                paddingBottom: bottomNav
                    ? 'calc(3.5rem + env(safe-area-inset-bottom) + 1rem)'
                    : 'calc(env(safe-area-inset-bottom) + 1.5rem)',
            }"
        >
            <!-- Snackbar flash (success / error / info) -->
            <Transition name="spmb-flash">
                <div
                    v-if="!dismissed && (flash.success || flash.error || flash.info)"
                    class="mb-4"
                >
                    <div
                        :class="[
                            'flex items-start gap-3 rounded-2xl border p-3.5 text-sm leading-relaxed shadow-sm',
                            flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : '',
                            flash.error ? 'border-red-200 bg-red-50 text-red-900' : '',
                            flash.info && !flash.success && !flash.error ? 'border-sky-200 bg-sky-50 text-sky-900' : '',
                        ]"
                        role="status"
                    >
                        <p class="flex-1">{{ flash.success || flash.error || flash.info }}</p>
                        <button
                            type="button"
                            class="-mr-1 -mt-1 rounded-full p-1 text-current/60 transition active:bg-black/10"
                            aria-label="Tutup pesan"
                            @click="dismissed = true"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </Transition>

            <slot />
        </main>

        <BottomNav v-if="bottomNav" />
    </div>
</template>

<style>
/* Rasa native: font sistem (Roboto di Android), nonaktifkan highlight tap biru,
   scroll momentum, dan cegah overscroll "bounce" yang membongkar cangkang. */
.spmb-app {
    font-family: system-ui, 'Roboto', -apple-system, 'Segoe UI', sans-serif;
    -webkit-tap-highlight-color: transparent;
    overscroll-behavior-y: contain;
}
.spmb-flash-enter-active,
.spmb-flash-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.spmb-flash-enter-from,
.spmb-flash-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
