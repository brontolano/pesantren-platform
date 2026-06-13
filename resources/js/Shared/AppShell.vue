<script setup>
import { Head } from '@inertiajs/vue3'
import FlashToast from '@/Components/Ui/FlashToast.vue'
import UiIcon from '@/Components/Ui/UiIcon.vue'
import BottomNav from './BottomNav.vue'
import { useOnline } from './useOnline.js'

/**
 * AppShell — kerangka aplikasi PWA bersama (ekstrak dari PortalWaliLayout).
 * Mobile-first max-w-md terpusat + FlashToast + banner offline + header(slot) +
 * main + BottomNav. Dipakai layout tiap peran (Pimpinan/Kasir/Guru).
 *
 * Slot:
 *   #header — area atas (HeroHeader untuk beranda; kosong/spacer untuk non-home)
 *   default — konten halaman
 *
 * Bottom-nav membaca page.props.navigation (atau prop navItems); ikon via navIcons.
 */
defineProps({
    navItems: { type: Array, default: null },
    navIcons: { type: Object, default: () => ({}) },
    manifestHref: { type: String, default: null },
    themeColor: { type: String, default: '#059669' },
    brandName: { type: String, default: 'Pesantren' },
    faviconUrl: { type: String, default: null },
    appleTouchIcon: { type: String, default: null },
    offlineMessage: {
        type: String,
        default: 'Anda sedang offline. Data terakhir yang tersimpan ditampilkan.',
    },
})

const { online } = useOnline()
</script>

<template>
    <Head>
        <link v-if="manifestHref" rel="manifest" :href="manifestHref">
        <meta name="theme-color" :content="themeColor">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" :content="brandName">
        <link v-if="faviconUrl" rel="icon" :href="faviconUrl">
        <link v-if="appleTouchIcon" rel="apple-touch-icon" :href="appleTouchIcon">
    </Head>

    <div class="font-jost min-h-screen bg-[var(--wali-bg,#fffaf3)] text-[#111] antialiased">
        <FlashToast />

        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col">
            <header class="relative z-20">
                <div
                    v-if="!online"
                    class="mx-6 mb-2 mt-[calc(env(safe-area-inset-top)+0.5rem)] flex items-start gap-2.5 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 ring-1 ring-inset ring-amber-200"
                    role="status"
                >
                    <UiIcon name="wifi-off" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ offlineMessage }}</span>
                </div>

                <slot name="header">
                    <div class="pt-[calc(env(safe-area-inset-top)+0.5rem)]" />
                </slot>
            </header>

            <main class="flex-1 px-6 pb-32 pt-5">
                <slot />
            </main>

            <BottomNav :items="navItems" :icon-map="navIcons" />
        </div>
    </div>
</template>
