<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const branding = computed(() => page.props.branding ?? {})
const guru = computed(() => page.props.guru ?? {})
const brandName = computed(() => branding.value.name || 'Pesantren')
const faviconUrl = computed(() => branding.value.faviconUrl || branding.value.icon192Url || branding.value.logoUrl || '/favicon.ico')
const isOffline = ref(false)

const navItems = computed(() => [
    { key: 'beranda', label: 'Hari Ini', href: '/guru', active: page.component === 'PortalGuru/Beranda' || page.component === 'PortalGuru/Presensi' },
    { key: 'setoran', label: 'Setoran', href: '/guru/setoran', active: page.component.startsWith('PortalGuru/Setoran') },
])

function syncOfflineState() {
    if (typeof navigator === 'undefined') {
        return
    }

    isOffline.value = !navigator.onLine
}

onMounted(async () => {
    syncOfflineState()

    if (typeof window !== 'undefined') {
        window.addEventListener('online', syncOfflineState)
        window.addEventListener('offline', syncOfflineState)
    }

    if (typeof window === 'undefined' || !window.location.pathname.startsWith('/guru')) {
        return
    }

    const { registerGuruPwa } = await import('@/guru/registerPwa.js')
    void registerGuruPwa()
})

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return
    }

    window.removeEventListener('online', syncOfflineState)
    window.removeEventListener('offline', syncOfflineState)
})
</script>

<template>
    <Head>
        <link rel="manifest" href="/guru/manifest.json">
        <meta name="theme-color" :content="branding.primaryColor || '#0f172a'">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" :content="brandName">
        <link rel="icon" :href="faviconUrl">
        <link rel="apple-touch-icon" :href="branding.icon192Url || faviconUrl">
    </Head>

    <div class="font-jost min-h-screen bg-[#fffaf3] text-[#111] antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col">
            <header class="relative z-20">
                <div class="rounded-b-[2rem] bg-slate-950 px-5 pb-5 pt-[calc(env(safe-area-inset-top)+1.25rem)] text-white shadow-lg">
                    <div
                        v-if="isOffline"
                        class="mb-3 rounded-2xl bg-white/15 px-4 py-2.5 text-sm font-medium text-amber-100 ring-1 ring-inset ring-white/20"
                        role="status"
                    >
                        Anda sedang offline. Aksi simpan akan dicoba ulang saat jaringan kembali.
                    </div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-200/90">
                        PWA Guru
                    </p>
                    <h1 class="mt-1 truncate text-xl font-semibold">{{ brandName }}</h1>
                    <p class="mt-0.5 truncate text-sm text-white/70">{{ guru.name }}</p>
                </div>
            </header>

            <main class="flex-1 px-4 pb-24 pt-4">
                <slot />
            </main>

            <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur">
                <div class="mx-auto flex max-w-md items-stretch justify-around pb-[env(safe-area-inset-bottom)]">
                    <Link
                        v-for="item in navItems"
                        :key="item.key"
                        :href="item.href"
                        class="flex-1 px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider"
                        :class="item.active ? 'text-emerald-700' : 'text-slate-500'"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
