<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import UiIcon from '@/Components/Ui/UiIcon.vue'
import FlashToast from '@/Components/Ui/FlashToast.vue'

// Peta ikon bottom-nav (presentasional; selaras dengan key dari backend).
const navIcons = {
    beranda: 'home',
    keuangan: 'wallet',
    izin: 'document',
    santri: 'book',
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
const isOffline = ref(false)


const isHomePage = computed(() => page.component === 'PortalWali/Dashboard')
const homeSummary = computed(() => page.props.summary ?? null)

const _currFmt = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
function fmtCurrency(v) { return _currFmt.format(v || 0) }



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

    <div class="font-jost min-h-screen bg-[var(--wali-bg,#fffaf3)] text-[#111] antialiased">
        <FlashToast />

        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col">
            <header class="relative z-20">
                <!-- Offline banner (all pages) -->
                <div
                    v-if="isOffline"
                    class="mx-6 mb-2 mt-[calc(env(safe-area-inset-top)+0.5rem)] flex items-start gap-2.5 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 ring-1 ring-inset ring-amber-200"
                    role="status"
                >
                    <UiIcon name="wifi-off" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>Anda sedang offline. Data terakhir yang tersimpan ditampilkan.</span>
                </div>

                <!-- HOME: emerald gradient header with avatar, child switcher, and financial stats -->
                <div v-if="isHomePage" class="relative overflow-hidden rounded-b-[30px] bg-brand-grad-hero px-6 pb-6 pt-[calc(env(safe-area-inset-top)+1.25rem)] text-white shadow-soft">
                    <div class="relative z-10">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-full bg-white/20 text-[17px] font-bold text-white ring-1 ring-inset ring-white/30">
                                    {{ (wali.name || '?').charAt(0).toUpperCase() }}
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[12.5px] text-white/75">Assalamu'alaikum,</p>
                                    <p class="truncate font-jost text-base font-semibold text-white">{{ wali.name || 'Wali' }}</p>
                                </div>
                            </div>
                            <Link
                                :href="`/wali/notifikasi`"
                                class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/16 ring-1 ring-inset ring-white/25"
                            >
                                <UiIcon name="bell" class="h-[19px] w-[19px] text-white" />
                                <span class="absolute right-[9px] top-2 h-2 w-2 rounded-full bg-amber-400 ring-2 ring-violet-600" />
                            </Link>
                        </div>

                        <!-- Child switcher pills -->
                        <div
                            v-if="children.length > 1"
                            class="-mx-1 mt-4 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                        >
                            <Link
                                v-for="child in children"
                                :key="child.id"
                                :href="resolveChildHref(child.id)"
                                class="shrink-0 rounded-full px-5 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[.97]"
                                :class="child.id === activeChildId
                                    ? 'bg-white text-violet-700 shadow-soft'
                                    : 'bg-transparent text-white/90 ring-1 ring-inset ring-white/40 hover:bg-white/10'"
                            >
                                {{ child.nama }}
                            </Link>
                        </div>

                        <!-- Financial summary: tagihan + tabungan -->
                        <div v-if="homeSummary" class="mt-4 grid grid-cols-2 gap-[11px]">
                            <div class="rounded-[20px] bg-white/15 px-4 py-[14px] ring-1 ring-inset ring-white/20 backdrop-blur-sm">
                                <p class="font-jost text-[10.5px] font-semibold uppercase tracking-[0.14em] text-white/70">Tagihan aktif</p>
                                <p class="mt-1 font-jost text-[21px] font-bold tabular-nums text-white">{{ fmtCurrency(homeSummary.totalOutstanding) }}</p>
                                <Link
                                    v-if="activeChildId"
                                    :href="`/wali/santri/${activeChildId}/keuangan?section=tagihan#tagihan`"
                                    class="mt-2 inline-flex rounded-full bg-white/20 px-3 py-1 text-[11px] font-semibold text-white"
                                >
                                    Bayar →
                                </Link>
                            </div>
                            <div class="rounded-[20px] bg-white/15 px-4 py-[14px] ring-1 ring-inset ring-white/20 backdrop-blur-sm">
                                <p class="font-jost text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#e7d3a1]">Tabungan</p>
                                <p class="mt-1 font-jost text-[21px] font-bold tabular-nums text-white">{{ fmtCurrency(homeSummary.totalSavings) }}</p>
                                <Link
                                    v-if="activeChildId"
                                    :href="`/wali/santri/${activeChildId}/tabungan`"
                                    class="mt-2 inline-flex rounded-full bg-white/20 px-3 py-1 text-[11px] font-semibold text-white"
                                >
                                    Lihat →
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NON-HOME: no gradient, just safe-area spacing -->
                <div v-else class="pt-[calc(env(safe-area-inset-top)+0.5rem)]" />
            </header>

            <main class="flex-1 px-6 pb-32 pt-5">
                <slot />
            </main>

            <!-- Bottom nav PIXIO: floating ringan, putih bersih, tab aktif = LINGKARAN emerald solid (ikon putih) -->
            <footer class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-md px-[18px] pb-[max(28px,env(safe-area-inset-bottom))]">
                <nav class="flex items-center justify-around rounded-full bg-white px-2 py-[9px] shadow-[inset_0_0_0_1px_#efe7da,0_16px_44px_-24px_rgba(17,17,17,0.4)]">
                    <Link
                        v-for="item in navigationItems"
                        :key="item.key || item.label"
                        :href="item.href"
                        class="group relative flex flex-col items-center gap-1 rounded-full px-1 py-1.5 transition-all duration-200 active:scale-[.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-violet-500"
                        :class="item.active ? 'text-violet-700' : 'text-slate-400 hover:text-slate-600'"
                        :aria-current="item.active ? 'page' : undefined"
                    >
                        <span
                            class="flex h-[34px] w-[34px] items-center justify-center rounded-full transition-all duration-200"
                            :class="item.active
                                ? 'bg-violet-50 text-violet-700'
                                : 'bg-transparent text-slate-400 group-hover:bg-slate-900/[0.04]'"
                        >
                            <UiIcon :name="navIcons[item.key] || 'home'" class="h-[19px] w-[19px]" />
                        </span>
                        <span
                            class="font-jost text-[9.5px] leading-none tracking-tight transition-all duration-200"
                            :class="item.active ? 'font-semibold text-violet-700' : 'font-medium'"
                        >
                            {{ item.label }}
                        </span>
                    </Link>
                </nav>
            </footer>
        </div>
    </div>
</template>
