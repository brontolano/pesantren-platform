<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const branding = computed(() => page.props.branding ?? {})
const userName = computed(() => page.props.userName ?? '')
const brandName = computed(() => branding.value.name || 'Pesantren')
const faviconUrl = computed(() => branding.value.faviconUrl || branding.value.icon192Url || '/favicon.ico')
const isOffline = ref(false)

const navItems = computed(() => [
    {
        key: 'beranda',
        label: 'Beranda',
        href: '/keuangan',
        icon: 'home',
        active: page.component === 'KepalaKeuangan/Beranda',
    },
    {
        key: 'penerimaan',
        label: 'Penerimaan',
        href: '/keuangan/penerimaan/tagihan',
        icon: 'arrow-down',
        active: page.component.startsWith('KepalaKeuangan/Penerimaan'),
    },
    {
        key: 'pengeluaran',
        label: 'Pengeluaran',
        href: '/keuangan/pengeluaran/approval',
        icon: 'arrow-up',
        active: page.component.startsWith('KepalaKeuangan/Pengeluaran'),
    },
    {
        key: 'ledger',
        label: 'Ledger',
        href: '/keuangan/ledger/jurnal',
        icon: 'scale',
        active: page.component.startsWith('KepalaKeuangan/Ledger'),
    },
    {
        key: 'laporan',
        label: 'Laporan',
        href: '/keuangan/laporan',
        icon: 'document',
        active: page.component.startsWith('KepalaKeuangan/Laporan'),
    },
])

function syncOfflineState() {
    if (typeof navigator !== 'undefined') {
        isOffline.value = !navigator.onLine
    }
}

onMounted(async () => {
    syncOfflineState()
    if (typeof window !== 'undefined') {
        window.addEventListener('online', syncOfflineState)
        window.addEventListener('offline', syncOfflineState)
    }

    if (typeof window !== 'undefined' && window.location.pathname.startsWith('/keuangan')) {
        try {
            const { registerKeuanganPwa } = await import('@/keuangan-pwa/registerPwa.js')
            void registerKeuanganPwa()
        } catch {
            // registerPwa belum ada — skip
        }
    }
})

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('online', syncOfflineState)
        window.removeEventListener('offline', syncOfflineState)
    }
})
</script>

<template>
    <Head>
        <link rel="manifest" href="/keuangan/manifest.json">
        <meta name="theme-color" content="#0A5C36">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" :content="brandName + ' · Keuangan'">
        <link rel="icon" :href="faviconUrl">
        <link rel="apple-touch-icon" :href="branding.icon192Url || faviconUrl">
    </Head>

    <div class="min-h-screen bg-[#FDFBF7] font-['Plus_Jakarta_Sans',ui-sans-serif,system-ui,sans-serif] text-[#16261f] antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col">
            <!-- Header -->
            <header class="relative z-20">
                <div class="bg-gradient-to-br from-[#0A5C36] to-[#06342b] px-5 pb-4 pt-[calc(env(safe-area-inset-top)+1rem)] text-white"
                     style="border-bottom: 2px solid #C9A24A;">
                    <!-- Offline banner -->
                    <div
                        v-if="isOffline"
                        class="mb-3 rounded-2xl bg-white/15 px-4 py-2.5 text-sm font-medium text-amber-100 ring-1 ring-inset ring-white/20"
                        role="status"
                    >
                        Anda sedang offline. Data mungkin tidak terkini.
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#e7d3a1] to-[#C9A24A]">
                            <svg viewBox="0 0 24 24" class="h-5 w-5 text-[#06342b]" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2c.5 1.5 2 2.3 2 4 0 1.2-.9 2-2 2s-2-.8-2-2c0-1.7 1.5-2.5 2-4z" />
                                <path d="M4 21v-7a8 8 0 0 1 16 0v7M3 21h18M9 21v-4a3 3 0 0 1 6 0v4" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-['Marcellus',serif] text-lg leading-tight">Keuangan</div>
                            <div class="truncate text-[10px] uppercase tracking-[0.1em] opacity-85">
                                Pesantren<span class="text-[#e7d3a1]">.</span>online · {{ brandName }}
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 rounded-full border border-[rgba(201,162,74,0.5)] bg-white/10 px-3 py-1.5 text-[11px]">
                            <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="#e7d3a1" stroke-width="2">
                                <circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 4-6 8-6s8 2 8 6" />
                            </svg>
                            <span class="truncate max-w-[80px]">{{ userName }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 px-4 pb-24 pt-4">
                <slot />
            </main>

            <!-- Bottom navigation -->
            <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-[#ece3cf] bg-white/97 backdrop-blur">
                <div class="mx-auto flex max-w-md items-stretch justify-around pb-[env(safe-area-inset-bottom)]">
                    <Link
                        v-for="item in navItems"
                        :key="item.key"
                        :href="item.href"
                        class="flex flex-1 flex-col items-center gap-1 py-2.5 text-center"
                        :class="item.active ? 'text-[#0A5C36] font-bold' : 'text-[#8a9690] font-semibold'"
                    >
                        <!-- Home icon -->
                        <svg v-if="item.icon === 'home'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 11l9-8 9 8M5 9v11h5v-6h4v6h5V9" />
                        </svg>
                        <!-- Arrow down (Penerimaan) -->
                        <svg v-else-if="item.icon === 'arrow-down'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" />
                        </svg>
                        <!-- Arrow up (Pengeluaran) -->
                        <svg v-else-if="item.icon === 'arrow-up'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 21V9m0 0l-4 4m4-4l4 4M4 7V4a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v3" />
                        </svg>
                        <!-- Scale (Ledger) -->
                        <svg v-else-if="item.icon === 'scale'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v18M5 7h14M7 7l-3 6a3 3 0 0 0 6 0L7 7zm10 0l-3 6a3 3 0 0 0 6 0l-3-6zM6 21h12" />
                        </svg>
                        <!-- Document (Laporan) -->
                        <svg v-else-if="item.icon === 'document'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h5" />
                        </svg>
                        <span class="text-[9.5px] uppercase tracking-wider">{{ item.label }}</span>
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
