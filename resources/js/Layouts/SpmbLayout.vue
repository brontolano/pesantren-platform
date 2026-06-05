<script setup>
// F1.2.1 — Layout Portal SPMB (PWA peserta), mobile-first.
// Tema white-label dibaca dari page.props.branding (dibagikan middleware
// ShareSpmbPageData per tenant): nama, tagline, logo, warna primer/sekunder.
// Warna dipakai lewat CSS variable --spmb-primary / --spmb-secondary agar
// komponen anak cukup menulis var(--spmb-primary) tanpa peduli tenant.
import { computed, onMounted, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();

const branding = computed(() => page.props.branding ?? {});
const flash = computed(() => page.props.flash ?? {});

const brandName = computed(() => branding.value.name || 'Pesantren');
const brandInitial = computed(() => brandName.value.trim().charAt(0).toUpperCase() || 'P');

const themeVars = computed(() => ({
    '--spmb-primary': branding.value.primaryColor || '#14532d',
    '--spmb-secondary': branding.value.secondaryColor || '#c2410c',
}));
const faviconUrl = computed(() => branding.value.faviconUrl || branding.value.icon192Url || branding.value.logoUrl || '/favicon.ico');

// Banner flash bisa ditutup; muncul lagi saat ada pesan baru.
const dismissed = ref(false);
watch(
    () => [flash.value.success, flash.value.error, flash.value.info],
    () => {
        dismissed.value = false;
    },
);

onMounted(async () => {
    if (typeof window === 'undefined' || !window.location.pathname.startsWith('/spmb')) {
        return;
    }

    const { registerSpmbPwa } = await import('@/spmb/registerPwa.js');
    void registerSpmbPwa();
});
</script>

<template>
    <Head>
        <link rel="manifest" href="/spmb/manifest.json">
        <meta name="theme-color" :content="branding.primaryColor || '#14532d'">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <link rel="icon" :href="faviconUrl">
        <link rel="apple-touch-icon" :href="branding.icon192Url || faviconUrl">
    </Head>

    <div
        :style="themeVars"
        class="min-h-screen bg-stone-50 text-stone-900 antialiased"
    >
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col">
            <!-- Header white-label -->
            <header class="border-b border-black/5 bg-[var(--spmb-primary)] px-4 pb-4 pt-5 text-white">
                <div class="flex items-center gap-3">
                    <img
                        v-if="branding.logoUrl"
                        :src="branding.logoUrl"
                        :alt="`Logo ${brandName}`"
                        class="h-11 w-11 shrink-0 rounded-xl bg-white object-contain p-1"
                    >
                    <div
                        v-else
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 text-lg font-bold"
                        aria-hidden="true"
                    >
                        {{ brandInitial }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold leading-tight">
                            {{ brandName }}
                        </p>
                        <p class="mt-0.5 truncate text-xs text-white/80">
                            {{ branding.tagline || 'Portal Penerimaan Santri Baru' }}
                        </p>
                    </div>
                </div>
            </header>

            <!-- Pesan flash (success / error / info) -->
            <div
                v-if="!dismissed && (flash.success || flash.error || flash.info)"
                class="px-4 pt-4"
            >
                <div
                    :class="[
                        'flex items-start gap-3 rounded-2xl border p-4 text-sm leading-relaxed',
                        flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : '',
                        flash.error ? 'border-red-200 bg-red-50 text-red-900' : '',
                        flash.info && !flash.success && !flash.error ? 'border-sky-200 bg-sky-50 text-sky-900' : '',
                    ]"
                    role="status"
                >
                    <p class="flex-1">
                        {{ flash.success || flash.error || flash.info }}
                    </p>
                    <button
                        type="button"
                        class="-mr-1 -mt-1 rounded-full p-1 text-current/60 transition hover:bg-black/5"
                        aria-label="Tutup pesan"
                        @click="dismissed = true"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Isi halaman -->
            <main class="flex-1 px-4 py-5">
                <slot />
            </main>

            <footer class="px-4 pb-6 pt-2 text-center text-xs text-stone-400">
                Portal SPMB · {{ brandName }}
            </footer>
        </div>
    </div>
</template>
