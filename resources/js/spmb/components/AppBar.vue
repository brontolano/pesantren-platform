<script setup>
// Material top app bar — fixed, edge-to-edge, aman dari notch (safe-area-inset-top).
// Mode brand (logo + nama) untuk Beranda, atau mode back (panah + judul) untuk sub-halaman.
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    showBack: { type: Boolean, default: false },
    backHref: { type: String, default: '/spmb' },
    brandHeader: { type: Boolean, default: false },
    logoUrl: { type: String, default: '' },
    brandName: { type: String, default: 'Pesantren' },
    elevated: { type: Boolean, default: false },
});
</script>

<template>
    <header
        class="fixed inset-x-0 top-0 z-30 bg-[var(--spmb-primary)] text-white transition-shadow"
        :class="elevated ? 'shadow-lg shadow-black/20' : ''"
        style="padding-top: env(safe-area-inset-top)"
    >
        <div class="mx-auto flex h-14 w-full max-w-screen-sm items-center gap-2 px-2">
            <!-- Tombol back (sub-halaman) -->
            <Link
                v-if="showBack"
                :href="backHref"
                class="-ml-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white/90 transition active:bg-white/20"
                aria-label="Kembali"
            >
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </Link>

            <!-- Logo brand (Beranda) -->
            <div
                v-else-if="brandHeader"
                class="ml-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/15"
            >
                <img v-if="logoUrl" :src="logoUrl" :alt="`Logo ${brandName}`" class="h-full w-full rounded-xl bg-white object-contain p-0.5">
                <span v-else class="text-base font-bold" aria-hidden="true">{{ (brandName || 'P').charAt(0).toUpperCase() }}</span>
            </div>

            <div class="min-w-0 flex-1" :class="showBack ? 'pl-1' : 'pl-2'">
                <p class="truncate text-[15px] font-semibold leading-tight">{{ title || brandName }}</p>
                <p v-if="subtitle" class="truncate text-[11px] leading-tight text-white/75">{{ subtitle }}</p>
            </div>

            <div class="flex shrink-0 items-center gap-1 pr-1">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
