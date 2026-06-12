<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import UiIcon from '@/Components/Ui/UiIcon.vue'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
    santri: { type: Object, required: true },
    hafalan: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
})

function nilaiColor(nilai) {
    if (!nilai) return 'bg-slate-100 text-slate-600'
    const v = String(nilai).toLowerCase()
    if (['mumtaz', 'lancar', 'a'].includes(v)) return 'bg-emerald-50 text-emerald-700'
    if (['jayyid', 'baik', 'b'].includes(v)) return 'bg-amber-50 text-amber-700'
    return 'bg-slate-100 text-slate-600'
}

function jenisIconBg(jenis) {
    const j = String(jenis || '').toLowerCase()
    if (j.includes('muraja')) return 'bg-[#f7efdc] text-[#a07c2c]'
    return 'bg-emerald-50 text-emerald-700'
}

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' }) : '-'
</script>

<template>
    <div class="space-y-4">
        <Head><title>Tahfidz · {{ santri.nama }}</title></Head>

        <div>
            <p class="font-jost text-[11px] font-semibold uppercase tracking-[0.16em] text-[#a07c2c]">{{ santri.nama }} · {{ santri.halaqah || 'Halaqah' }}</p>
            <h1 class="mt-1 font-jost text-[26px] font-bold tracking-tight text-[#111]">Tahfidz</h1>
        </div>

        <!-- Tab pills -->
        <div class="flex gap-2">
            <span class="rounded-full bg-emerald-600 px-4 py-2 font-jost text-[13px] font-semibold text-white">Tahfidz</span>
            <Link :href="`/wali/santri/${santri.id}/akademik`" class="rounded-full bg-white px-4 py-2 font-jost text-[13px] font-medium text-slate-600 shadow-[inset_0_0_0_1px_#efe7da]">Rapor</Link>
            <Link :href="`/wali/santri/${santri.id}/kesehatan`" class="rounded-full bg-white px-4 py-2 font-jost text-[13px] font-medium text-slate-600 shadow-[inset_0_0_0_1px_#efe7da]">Kesehatan</Link>
        </div>

        <!-- Progress card -->
        <div class="rounded-[26px] bg-gradient-to-br from-[#0b8f63] via-emerald-600 to-emerald-700 p-5 text-white shadow-[0_16px_44px_-24px_rgba(17,17,17,0.4)]">
            <p class="font-jost text-[11px] font-semibold uppercase tracking-[0.16em] text-[#e7d3a1]">Hafalan saat ini</p>
            <p class="mt-[7px] font-jost text-[21px] font-bold">{{ ringkasan.posisiTerakhir || 'Belum ada data' }}</p>
            <div v-if="ringkasan.persentase" class="mt-[14px]">
                <div class="h-2 overflow-hidden rounded-full bg-white/[.22]">
                    <div class="h-full rounded-full bg-[#e7d3a1]" :style="{ width: `${ringkasan.persentase}%` }" />
                </div>
                <div class="mt-2 flex items-center justify-between text-xs text-white/[.78]">
                    <span>{{ ringkasan.selesai ?? 0 }} juz selesai</span>
                    <span v-if="ringkasan.target">target {{ ringkasan.target }}</span>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-[11px]">
            <div class="rounded-[20px] bg-white px-4 py-[14px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
                <p class="font-jost text-[21px] font-bold tabular-nums text-emerald-700">{{ ringkasan.ziyadahPekan ?? 0 }} hal</p>
                <p class="mt-0.5 text-xs text-slate-500">Ziyadah pekan ini</p>
            </div>
            <div class="rounded-[20px] bg-white px-4 py-[14px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
                <p class="font-jost text-[21px] font-bold tabular-nums text-[#a07c2c]">{{ ringkasan.murajaahPekan ?? 0 }} sesi</p>
                <p class="mt-0.5 text-xs text-slate-500">Muraja'ah pekan ini</p>
            </div>
        </div>

        <!-- Setoran terakhir -->
        <div class="space-y-[10px]">
            <h3 class="font-jost text-[15.5px] font-semibold text-[#111]">Setoran terakhir</h3>

            <div v-if="hafalan.length" class="rounded-[22px] bg-white px-[17px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
                <div
                    v-for="(item, i) in hafalan.slice(0, 5)"
                    :key="item.id || i"
                    class="flex items-center gap-3 py-3"
                    :class="i < Math.min(hafalan.length, 5) - 1 ? 'border-b border-[#f6f0e6]' : ''"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" :class="jenisIconBg(item.jenis || item.penilaian?.[0]?.jenis)">
                        <UiIcon :name="String(item.jenis || item.penilaian?.[0]?.jenis || '').toLowerCase().includes('muraja') ? 'book' : 'check-circle'" class="h-[18px] w-[18px]" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-semibold text-slate-800">{{ item.surah }} {{ item.ayat_awal }}–{{ item.ayat_akhir }}</p>
                        <p class="text-[11.5px] text-slate-500">{{ formatDate(item.tanggal || item.created_at) }} · {{ item.jenis || item.penilaian?.[0]?.jenis || 'ziyadah' }}</p>
                    </div>
                    <span
                        v-if="item.penilaian?.[0]?.nilai || item.nilai"
                        class="shrink-0 rounded-full px-[11px] py-1 font-jost text-[10.5px] font-semibold"
                        :class="nilaiColor(item.penilaian?.[0]?.nilai || item.nilai)"
                    >
                        {{ item.penilaian?.[0]?.nilai || item.nilai }}
                    </span>
                </div>
            </div>

            <div v-else class="rounded-[22px] bg-white px-5 py-6 text-center shadow-[inset_0_0_0_1px_#efe7da]">
                <p class="text-sm text-slate-500">Belum ada data hafalan tercatat.</p>
            </div>
        </div>
    </div>
</template>
