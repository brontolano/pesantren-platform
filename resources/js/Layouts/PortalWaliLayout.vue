<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import UiIcon from '@/Components/Ui/UiIcon.vue'
import FlashToast from '@/Components/Ui/FlashToast.vue'

// Peta ikon bottom-nav (presentasional; selaras dengan key dari backend).
const navIcons = {
    beranda: 'home',
    tagihan: 'receipt',
    pembayaran: 'card',
    tabungan: 'wallet',
    akun: 'user',
}

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
})

const page = usePage()
const branding = computed(() => page.props.branding ?? {})

const pageUrl = computed(() => page.url || '/wali')
const pageQuery = computed(() => {
    try {
        return new URL(pageUrl.value, 'https://portal-wali.local').searchParams
    } catch {
        return new URLSearchParams()
    }
})

const children = computed(() => Array.isArray(page.props.children) ? page.props.children : [])
const navigationItems = computed(() => Array.isArray(page.props.navigation) ? page.props.navigation : [])
const wali = computed(() => page.props.wali ?? page.props.auth?.user ?? {})
const brandName = computed(() => branding.value.name || 'Pesantren')
const faviconUrl = computed(() => branding.value.faviconUrl || branding.value.icon192Url || branding.value.logoUrl || '/favicon.ico')
const currentSection = computed(() => pageQuery.value.get('section') || 'tagihan')
const activeChildId = computed(() => page.props.child?.id ?? page.props.santri?.id ?? children.value[0]?.id ?? null)
const activeChild = computed(() => children.value.find((child) => child.id === activeChildId.value) ?? children.value[0] ?? null)
const activeTab = computed(() => navigationItems.value.find((item) => item.active) ?? null)
const isOffline = ref(false)

const pageMetaMap = {
    'PortalWali/Dashboard': {
        title: 'Beranda Wali',
        kicker: 'Pantau semua anak dalam satu genggaman.',
    },
    'PortalWali/Financial': {
        title: 'Keuangan Santri',
        kicker: 'Tagihan, pembayaran, dan saldo tabungan anak.',
    },
    'PortalWali/Profile': {
        title: 'Data Anak',
        kicker: 'Profil dan riwayat penempatan santri.',
    },
    'PortalWali/Academic': {
        title: 'Ringkasan Anak',
        kicker: 'Akademik dan rapor santri yang sedang aktif.',
    },
    'PortalWali/Hafalan': {
        title: 'Ringkasan Anak',
        kicker: 'Pantau perkembangan hafalan santri.',
    },
    'PortalWali/Kesehatan': {
        title: 'Ringkasan Anak',
        kicker: 'Riwayat kesehatan dan konseling santri.',
    },
    'PortalWali/Kegiatan': {
        title: 'Ringkasan Anak',
        kicker: 'Kegiatan, izin, dan kunjungan wali.',
    },
    'PortalWali/Notifications': {
        title: 'Pembayaran Saya',
        kicker: 'Pantau status pembayaran, verifikasi, dan notifikasi penting wali.',
    },
    'PortalWali/Izin': {
        title: 'Akun Wali',
        kicker: 'Pengajuan izin dan aktivitas layanan wali.',
    },
}

const sectionTitle = computed(() => {
    if (props.title) {
        return props.title
    }

    if (page.component === 'PortalWali/Financial' && currentSection.value === 'tabungan') {
        return 'Tabungan Santri'
    }

    if (page.component === 'PortalWali/Financial') {
        return 'Tagihan Santri'
    }

    return pageMetaMap[page.component]?.title || activeTab.value?.label || 'Portal Wali'
})

const sectionKicker = computed(() => {
    if (page.component === 'PortalWali/Financial' && currentSection.value === 'tabungan') {
        return 'Lihat saldo, mutasi terakhir, dan akses tabungan anak.'
    }

    if (page.component === 'PortalWali/Financial') {
        return 'Cek kewajiban, pembayaran masuk, dan status tagihan anak.'
    }

    return pageMetaMap[page.component]?.kicker || 'Pantau perkembangan anak tanpa keluar dari satu portal.'
})

const tabMonograms = {
    beranda: 'BR',
    tagihan: 'TG',
    pembayaran: 'BY',
    tabungan: 'TB',
    akun: 'AK',
}

const secondaryChildItems = computed(() => {
    if (!activeChildId.value) {
        return []
    }

    return [
        {
            key: 'profil',
            label: 'Profil',
            href: `/wali/santri/${activeChildId.value}`,
            active: page.component === 'PortalWali/Profile',
        },
        {
            key: 'akademik',
            label: 'Akademik',
            href: `/wali/santri/${activeChildId.value}/akademik`,
            active: page.component === 'PortalWali/Academic',
        },
        {
            key: 'hafalan',
            label: 'Hafalan',
            href: `/wali/santri/${activeChildId.value}/hafalan`,
            active: page.component === 'PortalWali/Hafalan',
        },
        {
            key: 'kesehatan',
            label: 'Kesehatan',
            href: `/wali/santri/${activeChildId.value}/kesehatan`,
            active: page.component === 'PortalWali/Kesehatan',
        },
        {
            key: 'kegiatan',
            label: 'Kegiatan',
            href: `/wali/santri/${activeChildId.value}/kegiatan`,
            active: page.component === 'PortalWali/Kegiatan',
        },
    ]
})

function resolveChildHref(childId) {
    const financialSuffix = `?section=${currentSection.value}#${currentSection.value}`

    switch (page.component) {
        case 'PortalWali/Academic':
            return `/wali/santri/${childId}/akademik`
        case 'PortalWali/Financial':
            return `/wali/santri/${childId}/keuangan${financialSuffix}`
        case 'PortalWali/Hafalan':
            return `/wali/santri/${childId}/hafalan`
        case 'PortalWali/Kesehatan':
            return `/wali/santri/${childId}/kesehatan`
        case 'PortalWali/Kegiatan':
            return `/wali/santri/${childId}/kegiatan`
        default:
            return `/wali/santri/${childId}`
    }
}

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

    if (typeof window === 'undefined' || !window.location.pathname.startsWith('/wali')) {
        return
    }

    const { registerWaliPwa } = await import('@/wali/registerPwa.js')
    void registerWaliPwa()
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
        <link rel="manifest" href="/wali/manifest.json">
        <meta name="theme-color" :content="branding.primaryColor || '#0f172a'">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" :content="brandName">
        <link rel="icon" :href="faviconUrl">
        <link rel="apple-touch-icon" :href="branding.icon192Url || faviconUrl">
    </Head>

    <div class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <FlashToast />

        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col">
            <header class="sticky top-0 z-20 bg-slate-50/80 px-4 pb-3 pt-[calc(env(safe-area-inset-top)+1rem)] backdrop-blur">
                <div
                    v-if="isOffline"
                    class="mb-3 flex items-start gap-2.5 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 ring-1 ring-amber-100"
                    role="status"
                >
                    <UiIcon name="wifi-off" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                    <span>Anda sedang offline. Portal Wali menampilkan data terakhir yang tersimpan; aksi transaksi aktif lagi saat jaringan kembali.</span>
                </div>

                <!-- Hero gelap: identitas wali + anak aktif -->
                <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-700 to-emerald-900 px-4 py-4 text-white shadow-md ring-1 ring-emerald-900/20">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-200/90">
                                Portal Wali
                            </p>
                            <h1 class="mt-1.5 text-2xl font-bold leading-tight">
                                {{ sectionTitle }}
                            </h1>
                            <p class="mt-1.5 text-sm leading-6 text-white/75">
                                {{ sectionKicker }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2 rounded-full bg-white/10 px-3 py-2 ring-1 ring-white/15">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/15">
                                <UiIcon name="user" class="h-4 w-4 text-white" />
                            </span>
                            <p class="max-w-[5.5rem] truncate text-xs font-semibold text-white">
                                {{ wali.name || 'Akun Wali' }}
                            </p>
                        </div>
                    </div>

                    <!-- Anak aktif + switcher horizontal (jika >1 anak) -->
                    <div
                        v-if="activeChild"
                        class="mt-4 rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-base font-bold text-white">
                                    {{ activeChild.nama }}
                                </p>
                                <p class="mt-0.5 truncate text-xs text-white/65">
                                    {{ activeChild.kelas?.nama || 'Belum ada kelas' }} · {{ activeChild.hubungan || 'wali' }}
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-100">
                                {{ activeChild.status || 'aktif' }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="children.length > 1"
                        class="-mx-1 mt-3 flex gap-2 overflow-x-auto px-1 pb-1"
                    >
                        <Link
                            v-for="child in children"
                            :key="child.id"
                            :href="resolveChildHref(child.id)"
                            class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                            :class="child.id === activeChildId
                                ? 'bg-white text-emerald-800 shadow-sm'
                                : 'bg-white/10 text-white/80 ring-1 ring-white/15 hover:bg-white/15'"
                        >
                            {{ child.nama }}
                        </Link>
                    </div>
                </div>

                <!-- Tab anak sekunder (profil/akademik/...) -->
                <div
                    v-if="secondaryChildItems.length"
                    class="-mx-1 mt-3 flex gap-2 overflow-x-auto px-1 pb-1"
                >
                    <Link
                        v-for="item in secondaryChildItems"
                        :key="item.key"
                        :href="item.href"
                        class="shrink-0 rounded-full px-3.5 py-2 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                        :class="item.active
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </header>

            <main class="flex-1 px-4 py-5 pb-28">
                <slot />
            </main>

            <!-- Bottom nav 5 tab profesional -->
            <footer class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-md px-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)]">
                <nav class="grid grid-cols-5 gap-1 rounded-2xl bg-white/95 p-1.5 shadow-lg ring-1 ring-slate-100 backdrop-blur">
                    <Link
                        v-for="item in navigationItems"
                        :key="item.key || item.label"
                        :href="item.href"
                        class="group flex flex-col items-center gap-1 rounded-xl px-1 py-2 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500"
                        :class="item.active ? 'text-emerald-700' : 'text-slate-400 hover:text-slate-600'"
                        :aria-current="item.active ? 'page' : undefined"
                    >
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-full transition"
                            :class="item.active ? 'bg-emerald-50' : 'bg-transparent group-hover:bg-slate-50'"
                        >
                            <UiIcon :name="navIcons[item.key] || 'home'" class="h-5 w-5" />
                        </span>
                        <span class="text-[10px] font-semibold leading-none tracking-tight">
                            {{ item.label }}
                        </span>
                    </Link>
                </nav>
            </footer>
        </div>
    </div>
</template>
