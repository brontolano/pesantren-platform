<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
})

const page = usePage()

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
const currentSection = computed(() => pageQuery.value.get('section') || 'tagihan')
const activeChildId = computed(() => page.props.child?.id ?? page.props.santri?.id ?? children.value[0]?.id ?? null)
const activeChild = computed(() => children.value.find((child) => child.id === activeChildId.value) ?? children.value[0] ?? null)
const activeTab = computed(() => navigationItems.value.find((item) => item.active) ?? null)

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
        title: 'Akun Wali',
        kicker: 'Notifikasi penting dan status akun wali.',
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
    tabungan: 'TB',
    anak: 'AN',
    akun: 'AK',
}

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
</script>

<template>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,#f4eee3_0%,#f8f5ed_34%,#fffdf9_100%)] text-slate-900 antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col">
            <header class="sticky top-0 z-20 px-4 pb-3 pt-4 backdrop-blur">
                <div class="overflow-hidden rounded-[2rem] bg-slate-950 px-4 py-4 text-white shadow-[0_28px_80px_-48px_rgba(15,23,42,0.95)]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.32em] text-amber-300/90">
                                Portal Wali
                            </p>
                            <h1 class="mt-2 font-serif text-[1.85rem] leading-tight">
                                {{ sectionTitle }}
                            </h1>
                            <p class="mt-2 text-sm leading-6 text-white/72">
                                {{ sectionKicker }}
                            </p>
                        </div>
                        <div class="shrink-0 rounded-[1.35rem] border border-white/10 bg-white/10 px-3 py-2 text-right">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-white/55">
                                Wali
                            </p>
                            <p class="mt-1 max-w-[7rem] truncate text-sm font-semibold text-white">
                                {{ wali.name || 'Akun Wali' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="activeChild"
                        class="mt-4 rounded-[1.5rem] border border-white/10 bg-white/8 px-4 py-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold text-white">
                                    {{ activeChild.nama }}
                                </p>
                                <p class="mt-1 truncate text-xs uppercase tracking-[0.18em] text-white/60">
                                    {{ activeChild.kelas?.nama || 'Belum ada kelas' }} · {{ activeChild.hubungan || 'wali' }}
                                </p>
                            </div>
                            <div class="rounded-full bg-amber-300/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-200">
                                {{ activeChild.status || 'aktif' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="children.length > 1"
                    class="mt-3 overflow-x-auto pb-1"
                >
                    <div class="flex min-w-max gap-2">
                        <Link
                            v-for="child in children"
                            :key="child.id"
                            :href="resolveChildHref(child.id)"
                            class="rounded-[1.4rem] border px-4 py-3 text-left transition"
                            :class="child.id === activeChildId
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-900 shadow-[0_18px_45px_-38px_rgba(5,150,105,0.9)]'
                                : 'border-black/5 bg-white/90 text-slate-600 shadow-[0_18px_45px_-42px_rgba(15,23,42,0.5)]'"
                        >
                            <p class="text-sm font-semibold">
                                {{ child.nama }}
                            </p>
                            <p class="mt-1 text-[11px] uppercase tracking-[0.18em] text-current/70">
                                {{ child.kelas?.nama || 'Belum ada kelas' }}
                            </p>
                        </Link>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-5 pb-28">
                <slot />
            </main>

            <footer class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-md px-4 pb-4">
                <nav class="grid grid-cols-5 gap-2 rounded-[2rem] border border-black/5 bg-white/95 p-2 shadow-[0_24px_50px_-35px_rgba(15,23,42,0.45)] backdrop-blur">
                    <Link
                        v-for="item in navigationItems"
                        :key="item.key || item.label"
                        :href="item.href"
                        class="rounded-[1.25rem] px-2 py-2 text-center transition"
                        :class="item.active ? 'bg-slate-950 text-white' : 'text-slate-500'"
                    >
                        <span
                            class="mx-auto flex h-8 w-8 items-center justify-center rounded-full text-[10px] font-bold uppercase tracking-[0.18em]"
                            :class="item.active ? 'bg-white/12 text-white' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ tabMonograms[item.key] || item.label.slice(0, 2) }}
                        </span>
                        <span class="mt-1 block text-[10px] font-semibold uppercase tracking-[0.16em]">
                            {{ item.label }}
                        </span>
                    </Link>
                </nav>
            </footer>
        </div>
    </div>
</template>
