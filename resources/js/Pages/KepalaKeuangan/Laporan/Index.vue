<script setup>
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const groups = [
    {
        title: 'Operasional',
        items: [
            { label: 'Laporan Harian', href: '/keuangan/laporan/operasional?tab=harian', desc: 'Ringkasan penerimaan & pengeluaran harian' },
            { label: 'Laporan Mingguan', href: '/keuangan/laporan/operasional?tab=mingguan', desc: 'Rekap mingguan per divisi' },
            { label: 'Laporan Bulanan', href: '/keuangan/laporan/operasional?tab=bulanan', desc: 'Konsolidasi bulanan lengkap' },
        ],
    },
    {
        title: 'Akuntansi',
        items: [
            { label: 'Neraca', href: '/keuangan/laporan/neraca-lr', desc: 'Posisi keuangan (Aset = Kewajiban + Aset Neto)' },
            { label: 'Laba Rugi', href: '/keuangan/laporan/neraca-lr', desc: 'Surplus/defisit berjalan' },
            { label: 'Arus Kas', href: '/keuangan/laporan/arus-kas', desc: 'Operasi · Investasi · Pendanaan' },
            { label: 'Neraca Saldo', href: '/keuangan/laporan/neraca-saldo', desc: 'Saldo semua akun per tanggal' },
        ],
    },
    {
        title: 'Dana & Realisasi',
        items: [
            { label: 'BOS / BOP', href: '/keuangan/laporan/dana?tab=bos-bop', desc: 'Realisasi dana bantuan operasional' },
            { label: 'Dana Kebajikan', href: '/keuangan/laporan/dana?tab=kebajikan', desc: 'Penerimaan & penyaluran dana syubhat' },
            { label: 'Dana Terikat', href: '/keuangan/laporan/dana?tab=terikat', desc: 'Infaq/sedekah terikat program' },
            { label: 'Realisasi per Divisi', href: '/keuangan/laporan/dana?tab=divisi', desc: 'Anggaran vs realisasi per unit' },
        ],
    },
    {
        title: 'Rekap Tahunan',
        items: [
            { label: 'Laporan Keuangan Tahunan', href: '/keuangan/laporan/rekap-tahunan', desc: 'Penerimaan → Penyaluran → Surplus/Defisit (format PDF)' },
        ],
    },
    {
        title: 'Standar Akuntansi',
        items: [
            { label: 'PSAK 109 — Zakat & Infak', href: '/keuangan/laporan/psak109', desc: 'Perubahan dana, posisi keuangan, aset kelolaan' },
            { label: 'ISAK 35 — Nonlaba', href: '/keuangan/laporan/isak35', desc: 'Aset neto tanpa/dengan pembatasan' },
        ],
    },
    {
        title: 'Perpajakan',
        items: [
            { label: 'Pusat Pajak', href: '/keuangan/pajak', desc: 'SPT Masa PPh 21, e-Bupot, SPT Badan, Sisa Lebih' },
        ],
    },
]
</script>

<template>
    <div>
        <h1 class="mb-5 font-['Marcellus',serif] text-lg text-[#0A5C36]">Pusat Laporan</h1>

        <div v-for="g in groups" :key="g.title" class="mb-5">
            <h2 class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">{{ g.title }}</h2>
            <div class="space-y-2">
                <component
                    :is="item.href ? 'a' : 'div'"
                    v-for="item in g.items"
                    :key="item.label"
                    :href="item.href"
                    class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3 transition"
                    :class="item.href ? 'hover:border-[#C9A24A]/50 hover:shadow-sm cursor-pointer' : 'opacity-75'">
                    <div>
                        <div class="text-sm font-medium text-[#3a4d42]">{{ item.label }}</div>
                        <div class="text-xs text-[#8a9690]">{{ item.desc }}</div>
                    </div>
                    <svg v-if="item.href" viewBox="0 0 24 24" class="h-4 w-4 flex-shrink-0 text-[#C9A24A]" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 5l7 7-7 7" />
                    </svg>
                </component>
            </div>
        </div>
    </div>
</template>
