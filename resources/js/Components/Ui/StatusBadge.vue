<script setup>
import { computed } from 'vue'
import UiIcon from './UiIcon.vue'

/**
 * StatusBadge — peta status string -> warna semantik + ikon + label ID.
 * Menangani status tagihan & status pembayaran/verifikasi.
 * - status: kunci status (lihat MAP). String tak dikenal -> netral.
 * - label: override teks (opsional).
 * - size: 'sm' | 'md'
 */
const props = defineProps({
    status: { type: String, default: '' },
    label: { type: String, default: null },
    size: { type: String, default: 'md' },
})

// tone: emerald | amber | rose | blue | slate
const MAP = {
    // status tagihan
    belum_bayar: { label: 'Belum dibayar', tone: 'rose', icon: 'x-circle' },
    sebagian: { label: 'Dibayar sebagian', tone: 'amber', icon: 'clock' },
    lunas: { label: 'Lunas', tone: 'emerald', icon: 'check-circle' },
    batal: { label: 'Dibatalkan', tone: 'slate', icon: 'ban' },
    // status pembayaran / verifikasi
    menunggu: { label: 'Menunggu verifikasi', tone: 'amber', icon: 'clock' },
    pending: { label: 'Menunggu verifikasi', tone: 'amber', icon: 'clock' },
    terverifikasi: { label: 'Terverifikasi', tone: 'emerald', icon: 'check-circle' },
    verified: { label: 'Terverifikasi', tone: 'emerald', icon: 'check-circle' },
    ditolak: { label: 'Ditolak', tone: 'rose', icon: 'x-circle' },
    rejected: { label: 'Ditolak', tone: 'rose', icon: 'x-circle' },
    // info / netral
    info: { label: 'Info', tone: 'blue', icon: 'info' },
    aktif: { label: 'Aktif', tone: 'emerald', icon: 'check-circle' },
    nonaktif: { label: 'Nonaktif', tone: 'slate', icon: 'ban' },
    // kehadiran / presensi
    hadir: { label: 'Hadir', tone: 'emerald', icon: 'check-circle' },
    izin: { label: 'Izin', tone: 'blue', icon: 'info' },
    sakit: { label: 'Sakit', tone: 'amber', icon: 'heart' },
    alpa: { label: 'Alpa', tone: 'rose', icon: 'x-circle' },
    alpha: { label: 'Alpa', tone: 'rose', icon: 'x-circle' },
    terlambat: { label: 'Terlambat', tone: 'amber', icon: 'clock' },
    // UKS / kesehatan
    istirahat: { label: 'Istirahat', tone: 'amber', icon: 'clock' },
    pulang: { label: 'Pulang', tone: 'emerald', icon: 'check-circle' },
    rujuk: { label: 'Dirujuk', tone: 'blue', icon: 'arrow-up-right' },
    // disiplin / perizinan / approval
    dicatat: { label: 'Dicatat', tone: 'amber', icon: 'clock' },
    diajukan: { label: 'Diajukan', tone: 'amber', icon: 'clock' },
    disetujui: { label: 'Disetujui', tone: 'emerald', icon: 'check-circle' },
    // dokumen / laporan / publikasi
    draft: { label: 'Draf', tone: 'slate', icon: 'document' },
    draf: { label: 'Draf', tone: 'slate', icon: 'document' },
    terkirim: { label: 'Terkirim', tone: 'blue', icon: 'arrow-up-right' },
    diverifikasi: { label: 'Diverifikasi', tone: 'emerald', icon: 'check-circle' },
    final: { label: 'Final', tone: 'emerald', icon: 'check-circle' },
    published: { label: 'Terbit', tone: 'emerald', icon: 'check-circle' },
    terbit: { label: 'Terbit', tone: 'emerald', icon: 'check-circle' },
    // ujian / sesi
    berlangsung: { label: 'Berlangsung', tone: 'blue', icon: 'clock' },
    selesai: { label: 'Selesai', tone: 'emerald', icon: 'check-circle' },
    // PPDB
    diterima: { label: 'Diterima', tone: 'emerald', icon: 'check-circle' },
    lulus: { label: 'Lulus', tone: 'emerald', icon: 'check-circle' },
    cadangan: { label: 'Cadangan', tone: 'amber', icon: 'clock' },
    tertunggak: { label: 'Tertunggak', tone: 'rose', icon: 'x-circle' },
}

const TONES = {
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
    amber: 'bg-amber-50 text-amber-700 ring-amber-100',
    rose: 'bg-rose-50 text-rose-700 ring-rose-100',
    blue: 'bg-blue-50 text-blue-700 ring-blue-100',
    slate: 'bg-slate-100 text-slate-600 ring-slate-200',
}

const meta = computed(() => MAP[props.status] ?? { label: props.status || '-', tone: 'slate', icon: 'info' })
const toneClass = computed(() => TONES[meta.value.tone] ?? TONES.slate)
const displayLabel = computed(() => props.label ?? meta.value.label)
const sizeClass = computed(() =>
    props.size === 'sm' ? 'px-2.5 py-1 text-[11px] gap-1' : 'px-3 py-1.5 text-xs gap-1.5',
)
const iconSize = computed(() => (props.size === 'sm' ? 'h-3 w-3' : 'h-3.5 w-3.5'))
</script>

<template>
    <span
        class="inline-flex items-center rounded-full font-jost font-semibold tracking-tight ring-1 ring-inset"
        :class="[toneClass, sizeClass]"
    >
        <UiIcon :name="meta.icon" :class="iconSize" />
        {{ displayLabel }}
    </span>
</template>
