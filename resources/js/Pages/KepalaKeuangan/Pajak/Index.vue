<script setup>
import { onMounted, ref, computed } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull, formatTanggal } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    StatCard,
    Chip,
    UiButton,
    UiField,
    MoneyText,
    IconCircle,
    UiIcon,
    EmptyState,
    LoadingState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })

const tab = ref('spt-masa')
const loading = ref(true)
const data = ref(null)
const masaPajak = ref(new Date().toISOString().slice(0, 7))
const tahun = ref(String(new Date().getFullYear()))

async function muat() {
    loading.value = true
    data.value = null
    try {
        if (tab.value === 'spt-masa') {
            data.value = await api.get(`/pajak/spt-masa?masa=${masaPajak.value}`)
        } else if (tab.value === 'ebupot') {
            data.value = await api.get(`/pajak/ebupot?masa=${masaPajak.value}`)
        } else if (tab.value === 'spt-badan') {
            data.value = await api.get(`/pajak/spt-badan?tahun=${tahun.value}`)
        } else if (tab.value === 'sisa-lebih') {
            data.value = await api.get('/pajak/sisa-lebih')
        }
    } catch { /* */ }
    loading.value = false
}

function gantiTab(t) {
    tab.value = t
    muat()
}

onMounted(muat)

const tabs = [
    { key: 'spt-masa', label: 'SPT Masa PPh 21' },
    { key: 'ebupot', label: 'e-Bupot 21' },
    { key: 'spt-badan', label: 'SPT Badan' },
    { key: 'sisa-lebih', label: 'Sisa Lebih' },
]
</script>

<template>
    <div class="space-y-5">
        <PageHeader kicker="Perpajakan" title="Pusat Pajak" subtitle="SPT Masa, e-Bupot, SPT Badan & sisa lebih dana pendidikan" />

        <!-- Tabs -->
        <div class="flex flex-wrap gap-2">
            <Chip
                v-for="t in tabs"
                :key="t.key"
                :active="tab === t.key"
                :icon="{ 'spt-masa': 'receipt', 'ebupot': 'document', 'spt-badan': 'bank', 'sisa-lebih': 'wallet' }[t.key]"
                size="sm"
                @click="gantiTab(t.key)"
            >
                {{ t.label }}
            </Chip>
        </div>

        <!-- Filter -->
        <UiField v-if="tab !== 'sisa-lebih' && tab !== 'spt-badan'" label="Masa pajak">
            <template #default="{ fieldId, describedby }">
                <input
                    :id="fieldId"
                    :aria-describedby="describedby"
                    v-model="masaPajak"
                    type="month"
                    @change="muat"
                    class="w-full min-h-[48px] rounded-2xl bg-white px-4 py-3 text-sm font-semibold tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                >
            </template>
        </UiField>
        <UiField v-if="tab === 'spt-badan'" label="Tahun pajak">
            <template #default="{ fieldId, describedby }">
                <input
                    :id="fieldId"
                    :aria-describedby="describedby"
                    v-model="tahun"
                    type="number"
                    min="2020"
                    max="2030"
                    inputmode="numeric"
                    @change="muat"
                    class="w-28 min-h-[48px] rounded-2xl bg-white px-4 py-3 text-sm font-semibold tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                >
            </template>
        </UiField>

        <LoadingState v-if="loading" variant="list" :count="4" />

        <!-- SPT Masa -->
        <template v-else-if="tab === 'spt-masa' && data">
            <SectionCard kicker="SPT Masa PPh 21" :title="`Ringkasan ${data.masa_pajak}`" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow title="Pegawai Tetap">
                        <template #trailing>
                            <span class="text-[15px] font-bold tabular-nums text-slate-900">{{ data.bagian_a?.pegawai_tetap?.jumlah_penerima ?? 0 }} orang</span>
                        </template>
                    </ListRow>
                    <ListRow title="Bruto Pegawai Tetap">
                        <template #trailing>
                            <MoneyText :amount="data.bagian_a?.pegawai_tetap?.bruto ?? 0" size="sm" tone="default" />
                        </template>
                    </ListRow>
                    <ListRow title="PPh 21 Pegawai Tetap">
                        <template #trailing>
                            <MoneyText :amount="data.bagian_a?.pegawai_tetap?.pph21 ?? 0" size="sm" tone="emerald" />
                        </template>
                    </ListRow>
                    <ListRow>
                        <template #title>
                            <span class="font-extrabold text-slate-900">Total PPh 21 Disetor</span>
                        </template>
                        <template #subtitle>MAP {{ data.bagian_b?.kode_map }} / KJS {{ data.bagian_b?.kode_kjs }}</template>
                        <template #trailing>
                            <MoneyText :amount="data.bagian_b?.pph21_disetor ?? 0" size="md" tone="violet" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <SectionCard v-if="data.lampiran?.length" kicker="Rincian" title="Lampiran" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="(r, i) in data.lampiran" :key="i" :title="r.nama" :subtitle="`${r.kode_objek} · ${r.npwp || 'Tanpa NPWP'}`">
                        <template #trailing>
                            <div class="space-y-0.5 text-right">
                                <MoneyText :amount="r.bruto" size="sm" tone="default" />
                                <div class="text-xs font-semibold text-violet-700">PPh {{ formatRupiahFull(r.pph21) }}</div>
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </template>

        <!-- e-Bupot -->
        <template v-else-if="tab === 'ebupot' && data">
            <SectionCard kicker="e-Bupot 21" title="Bukti Potong PPh 21" flush>
                <template #right>
                    <UiButton
                        :href="`/keuangan/api/pajak/ebupot/csv?masa=${masaPajak}`"
                        as="a"
                        variant="primary"
                        size="sm"
                        icon-left="upload"
                    >
                        Download CSV
                    </UiButton>
                </template>
                <div class="divide-y divide-slate-100">
                    <ListRow title="Jumlah Bukti Potong">
                        <template #trailing>
                            <span class="text-[15px] font-bold tabular-nums text-slate-900">{{ data.summary?.jumlah_bukti_potong ?? 0 }}</span>
                        </template>
                    </ListRow>
                    <ListRow title="Total Bruto">
                        <template #trailing>
                            <MoneyText :amount="data.summary?.total_bruto ?? 0" size="sm" tone="default" />
                        </template>
                    </ListRow>
                    <ListRow>
                        <template #title>
                            <span class="font-extrabold text-slate-900">Total PPh 21</span>
                        </template>
                        <template #trailing>
                            <MoneyText :amount="data.summary?.total_pph21 ?? 0" size="md" tone="emerald" />
                        </template>
                    </ListRow>
                    <ListRow v-for="(r, i) in data.rows" :key="i" :title="r.nama" :subtitle="`NIK ${r.nik || '—'} · NPWP ${r.npwp || '—'}`">
                        <template #trailing>
                            <div class="space-y-0.5 text-right text-xs">
                                <div class="tabular-nums text-slate-500">Bruto {{ formatRupiahFull(r.bruto) }}</div>
                                <div class="font-semibold text-violet-700">PPh {{ formatRupiahFull(r.pph21_dipotong) }}</div>
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </template>

        <!-- SPT Badan -->
        <template v-else-if="tab === 'spt-badan' && data">
            <SectionCard kicker="SPT Badan" :title="`Pemisahan Pendapatan ${data.tahun}`" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow title="Pendapatan Pendidikan">
                        <template #trailing>
                            <div class="space-y-0.5 text-right">
                                <MoneyText :amount="data.pendapatan?.pendidikan?.nominal ?? 0" size="sm" tone="default" />
                                <div class="text-xs font-semibold text-emerald-600">{{ data.pendapatan?.pendidikan?.status_pajak }}</div>
                            </div>
                        </template>
                    </ListRow>
                    <ListRow title="Pendapatan Komersial">
                        <template #trailing>
                            <div class="space-y-0.5 text-right">
                                <MoneyText :amount="data.pendapatan?.komersial?.nominal ?? 0" size="sm" tone="default" />
                                <div class="text-xs font-semibold text-rose-600">{{ data.pendapatan?.komersial?.status_pajak }}</div>
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <SectionCard kicker="Kalkulasi" title="PPh Badan" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow title="Laba Fiskal Komersial">
                        <template #trailing>
                            <MoneyText :amount="data.laba_fiskal?.komersial ?? 0" size="sm" tone="default" />
                        </template>
                    </ListRow>
                    <ListRow title="PKP">
                        <template #trailing>
                            <MoneyText :amount="data.penghasilan_kena_pajak ?? 0" size="sm" tone="default" />
                        </template>
                    </ListRow>
                    <ListRow>
                        <template #title>
                            <span class="font-extrabold text-slate-900">PPh Badan ({{ data.tarif_pph_badan }}%)</span>
                        </template>
                        <template #trailing>
                            <MoneyText :amount="data.pph_badan_terutang ?? 0" size="md" tone="emerald" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <div class="flex items-start gap-2 rounded-3xl bg-slate-50 px-4 py-4 text-xs leading-5 text-slate-500 ring-1 ring-slate-100">
                <UiIcon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ data.koreksi_fiskal?.catatan }}</span>
            </div>
        </template>

        <!-- Sisa Lebih -->
        <template v-else-if="tab === 'sisa-lebih' && data">
            <EmptyState
                v-if="!data.length"
                icon="wallet"
                title="Belum ada data sisa lebih"
                message="Data terisi otomatis setelah tutup buku tahunan."
            />
            <SectionCard
                v-for="item in data"
                :key="item.tahun_fiskal"
                :kicker="`Tahun ${item.tahun_fiskal}`"
                :title="`Sisa Lebih ${item.tahun_fiskal}`"
            >
                <template #right>
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-tight ring-1 ring-inset"
                        :class="{
                            'bg-emerald-50 text-emerald-700 ring-emerald-100': item.status === 'terpenuhi',
                            'bg-amber-50 text-amber-700 ring-amber-100': item.status === 'terbuka',
                            'bg-rose-50 text-rose-700 ring-rose-100': item.status === 'kena_pajak',
                        }"
                    >
                        {{ item.status === 'terpenuhi' ? 'Terpenuhi' : item.status === 'kena_pajak' ? 'Kena Pajak' : 'Terbuka' }}
                    </span>
                </template>

                <div class="space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Surplus Pendidikan</span>
                        <MoneyText :amount="item.surplus" size="sm" tone="default" />
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Teralokasi</span>
                        <MoneyText :amount="item.teralokasi" size="sm" tone="emerald" />
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Sisa Belum Reinvestasi</span>
                        <span :class="item.sisa > 0 ? 'text-amber-600' : 'text-emerald-700'">
                            <MoneyText :amount="item.sisa" size="sm" tone="inherit" />
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500">Batas Waktu</span>
                        <span
                            class="text-sm tabular-nums"
                            :class="item.hari_tersisa < 365 ? 'font-bold text-rose-600' : 'font-medium text-slate-700'"
                        >
                            {{ item.batas }} ({{ item.hari_tersisa }} hari)
                        </span>
                    </div>
                </div>

                <div v-if="item.alokasi?.length" class="mt-3 border-t border-slate-100 pt-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Riwayat Reinvestasi</p>
                    <div
                        v-for="(a, i) in item.alokasi"
                        :key="i"
                        class="flex items-center justify-between gap-3 py-1 text-xs"
                    >
                        <span class="min-w-0 truncate text-slate-600">{{ a.tanggal }} — {{ a.keterangan }}</span>
                        <span class="shrink-0 font-semibold tabular-nums text-slate-900">{{ formatRupiahFull(a.nominal) }}</span>
                    </div>
                </div>
            </SectionCard>
        </template>

        <EmptyState
            v-else-if="!loading"
            icon="inbox"
            title="Tidak ada data"
            message="Tidak ada data untuk ditampilkan."
        />
    </div>
</template>
