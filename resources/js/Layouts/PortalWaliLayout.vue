<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import UiIcon from '@/Components/Ui/UiIcon.vue'
import FlashToast from '@/Components/Ui/FlashToast.vue'
import Chip from '@/Components/Ui/Chip.vue'

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
            <header class="relative z-20">
                <!-- HERO PREMIUM: gradient brand + dekorasi glow, sudut bawah membulat besar -->
                <div class="relative overflow-hidden rounded-b-[2.25rem] bg-brand-grad-hero px-4 pb-6 pt-[calc(env(safe-area-inset-top)+1.25rem)] text-white shadow-soft-lg">
                    <!-- Dekorasi radial glow lembut -->
                    <span aria-hidden="true" class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/20 blur-3xl" />
                    <span aria-hidden="true" class="pointer-events-none absolute -bottom-24 -left-14 h-52 w-52 rounded-full bg-teal-300/25 blur-3xl" />

                    <div class="relative">
                        <div
                            v-if="isOffline"
                            class="mb-4 flex items-start gap-2.5 rounded-2xl bg-white/15 px-4 py-3 text-sm font-medium text-white ring-1 ring-inset ring-white/20 backdrop-blur-sm"
                            role="status"
                        >
                            <UiIcon name="wifi-off" class="mt-0.5 h-4 w-4 shrink-0 text-amber-200" />
                            <span>Anda sedang offline. Portal Wali menampilkan data terakhir yang tersimpan; aksi transaksi aktif lagi saat jaringan kembali.</span>
                        </div>

                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-100/90">
                                    Portal Wali
                                </p>
                                <h1 class="mt-1.5 text-[1.7rem] font-extrabold leading-tight tracking-tight">
                                    {{ sectionTitle }}
                                </h1>
                                <p class="mt-1.5 text-sm leading-6 text-white/80">
                                    {{ sectionKicker }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2 rounded-full bg-white/15 px-3 py-2 ring-1 ring-inset ring-white/20 backdrop-blur-sm">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/20">
                                    <UiIcon name="user" class="h-4 w-4 text-white" />
                                </span>
                                <p class="max-w-[5.5rem] truncate text-xs font-bold text-white">
                                    {{ wali.name || 'Akun Wali' }}
                                </p>
                            </div>
                        </div>

                        <!-- Anak aktif (kartu glass) -->
                        <div
                            v-if="activeChild"
                            class="mt-5 rounded-2xl bg-white/15 px-4 py-3.5 ring-1 ring-inset ring-white/20 backdrop-blur-sm"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-base font-extrabold text-white">
                                        {{ (activeChild.nama || '?').charAt(0).toUpperCase() }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-base font-bold text-white">
                                            {{ activeChild.nama }}
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-white/70">
                                            {{ activeChild.kelas?.nama || 'Belum ada kelas' }} · {{ activeChild.hubungan || 'wali' }}
                                        </p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full bg-white/20 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-50">
                                    {{ activeChild.status || 'aktif' }}
                                </span>
                            </div>
                        </div>

                        <!-- Switcher anak (pill premium glass) -->
                        <div
                            v-if="children.length > 1"
                            class="-mx-1 mt-3 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                        >
                            <Link
                                v-for="child in children"
                                :key="child.id"
                                :href="resolveChildHref(child.id)"
                                class="shrink-0 rounded-full px-4 py-2 text-sm font-bold tracking-tight transition-all duration-200 active:scale-[.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                :class="child.id === activeChildId
                                    ? 'bg-white text-emerald-700 shadow-soft'
                                    : 'bg-white/15 text-white/85 ring-1 ring-inset ring-white/20 hover:bg-white/25'"
                            >
                                {{ child.nama }}
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Tab anak sekunder (profil/akademik/...) — pill premium di bg terang -->
                <div
                    v-if="secondaryChildItems.length"
                    class="-mx-1 mt-3 flex gap-2 overflow-x-auto px-5 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                >
                    <Chip
                        v-for="item in secondaryChildItems"
                        :key="item.key"
                        :href="item.href"
                        :active="item.active"
                        tone="brand"
                        size="sm"
                    >
                        {{ item.label }}
                    </Chip>
                </div>
            </header>

            <main class="flex-1 px-4 pb-32 pt-5">
                <slot />
            </main>

            <!-- Bottom nav: FLOATING GLASS — melayang, blur, indikator pill gradient -->
            <footer class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-md px-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <nav class="grid grid-cols-5 gap-1 rounded-[1.6rem] border border-white/60 bg-white/80 p-1.5 shadow-soft-lg backdrop-blur-xl">
                    <Link
                        v-for="item in navigationItems"
                        :key="item.key || item.label"
                        :href="item.href"
                        class="group relative flex flex-col items-center gap-1 rounded-2xl px-1 py-2 transition-all duration-200 active:scale-[.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500"
                        :class="item.active ? 'text-emerald-700' : 'text-slate-400 hover:text-slate-600'"
                        :aria-current="item.active ? 'page' : undefined"
                    >
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-2xl transition-all duration-200"
                            :class="item.active ? 'bg-emerald-50 text-emerald-600 shadow-[0_6px_16px_-8px_rgba(5,150,105,0.6)]' : 'bg-transparent group-hover:bg-slate-50'"
                        >
                            <UiIcon :name="navIcons[item.key] || 'home'" class="h-5 w-5" />
                        </span>
                        <span
                            class="text-[10px] leading-none tracking-tight transition-all duration-200"
                            :class="item.active ? 'font-extrabold' : 'font-semibold'"
                        >
                            {{ item.label }}
                        </span>
                        <!-- Titik indikator gradient untuk tab aktif -->
                        <span
                            v-if="item.active"
                            aria-hidden="true"
                            class="absolute -bottom-0.5 h-1 w-1 rounded-full bg-brand-grad"
                        />
                    </Link>
                </nav>
            </footer>
        </div>
    </div>
</template>
