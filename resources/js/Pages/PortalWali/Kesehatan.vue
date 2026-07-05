<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import UiIcon from '@/Components/Ui/UiIcon.vue'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
    santri: { type: Object, required: true },
    riwayat: { type: Array, default: () => [] },
    konseling: { type: Array, default: () => [] },
})

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-'
</script>

<template>
    <div class="space-y-4">
        <Head><title>Kesehatan · {{ santri.nama }}</title></Head>

        <div>
            <p class="font-jost text-[11px] font-semibold uppercase tracking-[0.16em] text-[#a07c2c]">{{ santri.nama }} · dicatat oleh UKS pondok</p>
            <h1 class="mt-1 font-jost text-[26px] font-bold tracking-tight text-[#111]">Kesehatan</h1>
        </div>

        <!-- Tab pills -->
        <div class="flex gap-2">
            <Link :href="`/wali/santri/${santri.id}/hafalan`" class="rounded-full bg-white px-4 py-2 font-jost text-[13px] font-medium text-slate-600 shadow-[inset_0_0_0_1px_#efe7da]">Tahfidz</Link>
            <Link :href="`/wali/santri/${santri.id}/akademik`" class="rounded-full bg-white px-4 py-2 font-jost text-[13px] font-medium text-slate-600 shadow-[inset_0_0_0_1px_#efe7da]">Rapor</Link>
            <span class="rounded-full bg-violet-600 px-4 py-2 font-jost text-[13px] font-semibold text-white">Kesehatan</span>
        </div>

        <!-- Status card -->
        <div class="flex items-center gap-[14px] rounded-[24px] bg-white px-5 py-[18px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
            <span class="flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-[18px] bg-violet-50 text-violet-700">
                <UiIcon name="heart" class="h-[26px] w-[26px]" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-jost text-[17px] font-bold text-[#111]">Kondisi: {{ santri.kondisiKesehatan || 'Sehat' }}</p>
                <p class="mt-0.5 text-[12.5px] text-slate-500">Pemeriksaan rutin terakhir: {{ santri.pemeriksaanTerakhir || '-' }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 font-jost text-[11px] font-semibold text-emerald-700">Aktif</span>
        </div>

        <!-- Fisik stats -->
        <div class="grid grid-cols-2 gap-[11px]">
            <div class="rounded-[20px] bg-white px-4 py-[14px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
                <p class="font-jost text-[21px] font-bold tabular-nums text-[#111]">{{ santri.tinggiBadan || '-' }}</p>
                <p class="mt-0.5 text-xs text-slate-500">Tinggi badan</p>
            </div>
            <div class="rounded-[20px] bg-white px-4 py-[14px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
                <p class="font-jost text-[21px] font-bold tabular-nums text-[#111]">{{ santri.beratBadan || '-' }}</p>
                <p class="mt-0.5 text-xs text-slate-500">Berat badan</p>
            </div>
        </div>

        <!-- Riwayat kunjungan UKS -->
        <div class="space-y-[10px]">
            <h3 class="font-jost text-[15.5px] font-semibold text-[#111]">Riwayat kunjungan UKS</h3>

            <div v-if="riwayat.length" class="rounded-[22px] bg-white px-[17px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
                <div
                    v-for="(item, i) in riwayat.slice(0, 5)"
                    :key="item.id || i"
                    class="flex items-center gap-3 py-[13px]"
                    :class="i < Math.min(riwayat.length, 5) - 1 ? 'border-b border-[#f6f0e6]' : ''"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <UiIcon name="check-circle" class="h-[18px] w-[18px]" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-semibold text-slate-800">{{ item.keluhan || 'Pemeriksaan rutin' }} — {{ item.kondisi || 'sembuh' }}</p>
                        <p class="text-[11.5px] text-slate-500">{{ formatDate(item.tanggal) }}{{ item.tindakan ? ` · ${item.tindakan}` : '' }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-[22px] bg-white px-5 py-6 text-center shadow-[inset_0_0_0_1px_#efe7da]">
                <p class="text-sm text-slate-500">Tidak ada riwayat kunjungan UKS.</p>
            </div>
        </div>

        <!-- Info box -->
        <div class="flex gap-[10px] rounded-[18px] bg-[#f7efdc] px-4 py-[13px] shadow-[inset_0_0_0_1px_#e7d3a1]">
            <UiIcon name="info" class="mt-px h-[17px] w-[17px] shrink-0 text-[#a07c2c]" />
            <p class="text-xs leading-relaxed text-[#7a5e1e]">Jika ananda dirawat di UKS, Anda otomatis menerima notifikasi dan bisa langsung mengajukan <strong>izin jenguk</strong>.</p>
        </div>
    </div>
</template>
