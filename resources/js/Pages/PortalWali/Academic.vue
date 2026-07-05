<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import UiIcon from '@/Components/Ui/UiIcon.vue'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
    child: { type: Object, required: true },
    latestRapor: { type: Object, default: null },
    raporDiniyah: { type: Object, default: null },
    grades: { type: Array, default: () => [] },
    schedule: { type: Array, default: () => [] },
    attendance: { type: Object, required: true },
})

function barColor(nilai) {
    if (nilai >= 90) return 'bg-emerald-500'
    return 'bg-[#c9a24a]'
}

function scoreColor(nilai) {
    if (nilai >= 90) return 'text-emerald-700'
    return 'text-amber-700'
}
</script>

<template>
    <div class="space-y-4">
        <Head><title>Rapor · {{ child.nama }}</title></Head>

        <div>
            <p class="font-jost text-[11px] font-semibold uppercase tracking-[0.16em] text-[#a07c2c]">{{ child.nama }} · {{ latestRapor?.periode || 'Semester' }}</p>
            <h1 class="mt-1 font-jost text-[26px] font-bold tracking-tight text-[#111]">Rapor Akademik</h1>
        </div>

        <!-- Tab pills -->
        <div class="flex gap-2">
            <Link :href="`/wali/santri/${child.id}/hafalan`" class="rounded-full bg-white px-4 py-2 font-jost text-[13px] font-medium text-slate-600 shadow-[inset_0_0_0_1px_#efe7da]">Tahfidz</Link>
            <span class="rounded-full bg-violet-600 px-4 py-2 font-jost text-[13px] font-semibold text-white">Rapor</span>
            <Link :href="`/wali/santri/${child.id}/kesehatan`" class="rounded-full bg-white px-4 py-2 font-jost text-[13px] font-medium text-slate-600 shadow-[inset_0_0_0_1px_#efe7da]">Kesehatan</Link>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-[11px]">
            <div class="rounded-[20px] bg-white px-4 py-[14px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
                <p class="font-jost text-[24px] font-bold tabular-nums text-violet-700">{{ latestRapor?.ipk ?? '-' }}</p>
                <p class="mt-0.5 text-xs text-slate-500">Rata-rata nilai</p>
            </div>
            <div class="rounded-[20px] bg-white px-4 py-[14px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
                <p class="font-jost text-[24px] font-bold tabular-nums text-[#a07c2c]">
                    {{ latestRapor?.rankKelas ?? '-' }}
                    <span v-if="latestRapor?.jumlahSiswa" class="text-sm font-medium text-slate-400">/ {{ latestRapor.jumlahSiswa }}</span>
                </p>
                <p class="mt-0.5 text-xs text-slate-500">Peringkat kelas</p>
            </div>
        </div>

        <!-- Nilai per mata pelajaran -->
        <div v-if="grades.length" class="rounded-[24px] bg-white px-5 py-[18px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
            <p class="font-jost text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Nilai per mata pelajaran</p>
            <div class="mt-[14px] space-y-[10px]">
                <div v-for="grade in grades" :key="`${grade.kode}-${grade.periode}`" class="space-y-[6px]">
                    <div class="flex items-center justify-between text-[13.5px]">
                        <span class="font-semibold text-slate-800">{{ grade.pelajaran }}</span>
                        <span class="font-jost font-bold tabular-nums" :class="scoreColor(grade.nilaiAkhir)">{{ grade.nilaiAkhir ?? '-' }}</span>
                    </div>
                    <div v-if="grade.nilaiAkhir" class="h-[6px] overflow-hidden rounded-full bg-[#f6f0e6]">
                        <div class="h-full rounded-full" :class="barColor(grade.nilaiAkhir)" :style="{ width: `${grade.nilaiAkhir}%` }" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Catatan wali kelas -->
        <div v-if="latestRapor?.catatanWaliKelas" class="flex gap-[10px] rounded-[18px] bg-[#f6f0e6] px-4 py-[13px] shadow-[inset_0_0_0_1px_#efe7da]">
            <UiIcon name="info" class="mt-px h-[17px] w-[17px] shrink-0 text-[#a07c2c]" />
            <p class="text-xs leading-relaxed text-slate-600"><strong>Catatan wali kelas:</strong> {{ latestRapor.catatanWaliKelas }}</p>
        </div>

        <!-- Download -->
        <div class="space-y-3">
            <Link
                v-if="latestRapor"
                :href="`/wali/santri/${child.id}/rapor/${latestRapor.id}/download`"
                class="block rounded-full bg-white py-[14px] text-center font-jost text-[14.5px] font-semibold text-violet-700 shadow-[inset_0_0_0_1px_#efe7da]"
            >
                Unduh rapor (PDF)
            </Link>
            <Link
                v-if="raporDiniyah"
                :href="`/wali/santri/${child.id}/rapor-diniyah/${raporDiniyah.id}/download`"
                class="block rounded-full bg-white py-[14px] text-center font-jost text-[14.5px] font-semibold text-violet-700 shadow-[inset_0_0_0_1px_#efe7da]"
            >
                Unduh rapor diniyah (PDF)
            </Link>
        </div>

        <!-- Empty -->
        <div v-if="!grades.length && !latestRapor" class="rounded-[22px] bg-white px-5 py-6 text-center shadow-[inset_0_0_0_1px_#efe7da]">
            <p class="text-sm text-slate-500">Belum ada data rapor tersedia.</p>
        </div>
    </div>
</template>
