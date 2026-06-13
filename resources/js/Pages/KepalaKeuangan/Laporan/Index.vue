<script setup>
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { PageHeader, SectionCard, ListRow, IconCircle } from '@/Shared'

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
    <div class="space-y-5">
        <PageHeader kicker="Keuangan" title="Pusat Laporan" subtitle="Operasional, akuntansi, dana & standar pelaporan" />

        <SectionCard v-for="g in groups" :key="g.title" :title="g.title" flush>
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="item in g.items"
                    :key="item.label"
                    :href="item.href || undefined"
                    :title="item.label"
                    :subtitle="item.desc"
                    :chevron="!!item.href"
                    :class="item.href ? '' : 'opacity-75'">
                    <template #leading>
                        <IconCircle
                            :icon="{
                                'Operasional': 'calendar',
                                'Akuntansi': 'chart',
                                'Dana & Realisasi': 'wallet',
                                'Rekap Tahunan': 'document',
                                'Standar Akuntansi': 'book',
                                'Perpajakan': 'receipt',
                            }[g.title] || 'document'"
                            :tone="item.href ? 'emerald' : 'slate'"
                            size="sm" />
                    </template>
                </ListRow>
            </div>
        </SectionCard>
    </div>
</template>
