<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import UiIcon from '@/Components/Ui/UiIcon.vue'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    wali: { type: Object, required: true },
    children: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
    announcements: { type: Array, default: () => [] },
    recentActivities: { type: Array, default: () => [] },
})

const activeChild = computed(() => props.children[0] ?? null)

const shortcuts = computed(() => {
    const childId = activeChild.value?.id
    if (!childId) return []
    return [
        { key: 'tagihan', label: 'Tagihan', icon: 'receipt', bg: 'bg-violet-100', color: 'text-violet-700', href: `/wali/santri/${childId}/keuangan?section=tagihan#tagihan` },
        { key: 'tabungan', label: 'Tabungan', icon: 'wallet', bg: 'bg-violet-100', color: 'text-violet-700', href: `/wali/santri/${childId}/tabungan` },
        { key: 'izin', label: 'Izin', icon: 'document', bg: 'bg-amber-100', color: 'text-amber-700', href: '/wali/izin', badge: props.summary.pendingIzin > 0 },
        { key: 'jenguk', label: 'Jenguk', icon: 'calendar', bg: 'bg-orange-100', color: 'text-orange-700', href: '/wali/jenguk' },
        { key: 'tahfidz', label: 'Tahfidz', icon: 'book', bg: 'bg-teal-100', color: 'text-teal-700', href: `/wali/santri/${childId}/hafalan` },
        { key: 'rapor', label: 'Rapor', icon: 'chart', bg: 'bg-blue-100', color: 'text-blue-700', href: `/wali/santri/${childId}/akademik` },
        { key: 'kesehatan', label: 'Kesehatan', icon: 'heart', bg: 'bg-rose-100', color: 'text-rose-700', href: `/wali/santri/${childId}/kesehatan` },
        { key: 'kegiatan', label: 'Kegiatan', icon: 'bell', bg: 'bg-slate-100', color: 'text-slate-600', href: `/wali/santri/${childId}/kegiatan` },
    ]
})

const activityIconMap = {
    izin: 'check-circle',
    hafalan: 'book',
    pembayaran: 'card',
    tagihan: 'receipt',
    default: 'info',
}

function activityIcon(type) {
    return activityIconMap[type] || activityIconMap.default
}
</script>

<template>
    <div class="space-y-4">
        <Head>
            <title>Beranda Wali</title>
        </Head>

        <!-- Shortcut Grid — 3 kolom, ikon bulat penuh lebih besar, kartu lebih "terangkat" -->
        <div v-if="shortcuts.length" class="grid grid-cols-3 gap-3">
            <Link
                v-for="s in shortcuts"
                :key="s.key"
                :href="s.href"
                class="group relative flex flex-col items-center gap-2 rounded-[20px] bg-white px-2 py-4 shadow-lift transition duration-200 hover:shadow-lift-hover active:scale-[.96]"
            >
                <span v-if="s.badge" class="absolute right-3 top-3 h-[7px] w-[7px] rounded-full bg-amber-500 ring-2 ring-white" />
                <span
                    class="flex h-12 w-12 items-center justify-center rounded-full transition-transform duration-200 group-active:scale-95"
                    :class="[s.bg, s.color]"
                >
                    <UiIcon :name="s.icon" class="h-[21px] w-[21px]" />
                </span>
                <span class="font-jost text-[11.5px] font-semibold leading-tight text-slate-800">{{ s.label }}</span>
            </Link>
        </div>

        <!-- Pengumuman Banner -->
        <div
            v-if="announcements.length"
            class="flex items-center gap-3 rounded-[20px] bg-[#f7efdc] px-[18px] py-3.5 shadow-[inset_0_0_0_1px_#e7d3a1]"
        >
            <span class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-full bg-white text-[#a07c2c]">
                <UiIcon name="bell" class="h-[19px] w-[19px]" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-[#7a5e1e]">{{ announcements[0].title }}</p>
                <p class="mt-px text-xs text-[#a07c2c]">{{ announcements[0].excerpt }}</p>
            </div>
            <UiIcon name="chevron-right" class="h-[15px] w-[15px] shrink-0 text-[#a07c2c]" />
        </div>

        <!-- Fallback: jika tidak ada pengumuman dari backend, tampilkan kartu statis -->
        <div
            v-else
            class="flex items-center gap-3 rounded-[20px] bg-[#f7efdc] px-[18px] py-3.5 shadow-[inset_0_0_0_1px_#e7d3a1]"
        >
            <span class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-full bg-white text-[#a07c2c]">
                <UiIcon name="bell" class="h-[19px] w-[19px]" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-[#7a5e1e]">Pengumuman pesantren</p>
                <p class="mt-px text-xs text-[#a07c2c]">Belum ada pengumuman terbaru</p>
            </div>
        </div>

        <!-- Terbaru dari pondok (Activity Feed) -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between">
                <h3 class="font-jost text-base font-semibold text-[#111]">Terbaru dari pondok</h3>
                <Link href="/wali/notifikasi" class="font-jost text-[13px] font-semibold text-violet-700">Semua</Link>
            </div>

            <div v-if="recentActivities.length" class="rounded-[22px] bg-white px-[18px] shadow-lift">
                <div
                    v-for="(activity, i) in recentActivities.slice(0, 5)"
                    :key="i"
                    class="flex items-center gap-[13px] py-3"
                    :class="i < recentActivities.length - 1 && i < 4 ? 'border-b border-[#f6f0e6]' : ''"
                >
                    <span class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-700">
                        <UiIcon :name="activityIcon(activity.type)" class="h-[19px] w-[19px]" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[13.5px] font-semibold text-slate-800">{{ activity.title }}</p>
                        <p class="text-xs text-slate-500">{{ activity.subtitle }}</p>
                    </div>
                    <span class="shrink-0 text-[11px] text-slate-400">{{ activity.time }}</span>
                </div>
            </div>

            <!-- Fallback: no activities -->
            <div v-else class="rounded-[22px] bg-white px-[18px] py-4 text-center shadow-[inset_0_0_0_1px_#efe7da]">
                <p class="text-sm text-slate-500">Belum ada aktivitas terbaru</p>
            </div>
        </div>

    </div>
</template>
