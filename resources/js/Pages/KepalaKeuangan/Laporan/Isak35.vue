<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SegmentedControl,
    SectionCard,
    UiCard,
    MoneyText,
    UiIcon,
    LoadingState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })

const tab = ref('perubahan_aset_neto')
const loading = ref(true)
const data = ref(null)

const tabs = [
    { key: 'perubahan_aset_neto', label: 'Perubahan Aset Neto' },
    { key: 'penghasilan_komprehensif', label: 'Penghasilan Komprehensif' },
    { key: 'posisi_keuangan', label: 'Posisi Keuangan' },
]

async function muat() {
    loading.value = true
    try { data.value = await api.get(`/laporan/isak35?jenis=${tab.value}`) } catch { /* */ }
    loading.value = false
}

function gantiTab(t) { tab.value = t; muat() }
onMounted(muat)
</script>

<template>
    <div class="space-y-5">
        <PageHeader
            kicker="ISAK 35"
            title="Laporan ISAK 35"
            subtitle="Entitas Berorientasi Nonlaba"
        />

        <SegmentedControl
            :model-value="tab"
            :options="tabs.map((t) => ({ value: t.key, label: t.label }))"
            @update:model-value="gantiTab"
        />

        <LoadingState v-if="loading" variant="card" :count="4" />

        <!-- Perubahan Aset Neto -->
        <template v-else-if="tab === 'perubahan_aset_neto' && data">
            <SectionCard
                v-for="(section, key) in data"
                :key="key"
                :title="key.replace(/_/g, ' ')"
                flush
            >
                <div v-if="typeof section === 'object' && section !== null" class="divide-y divide-slate-100">
                    <div v-for="(v, k) in section" :key="k"
                         class="flex items-center justify-between gap-3 px-5 py-3 text-sm"
                         :class="k === 'saldo_akhir' || k === 'perubahan' ? 'font-bold' : ''">
                        <span class="capitalize text-slate-600">{{ k.replace(/_/g, ' ') }}</span>
                        <MoneyText
                            :amount="v"
                            size="sm"
                            :tone="v > 0 && (k === 'surplus' || k === 'perubahan') ? 'emerald' : (v < 0 ? 'rose' : 'default')"
                        />
                    </div>
                </div>
            </SectionCard>
        </template>

        <!-- Penghasilan Komprehensif -->
        <template v-else-if="tab === 'penghasilan_komprehensif' && data">
            <SectionCard
                v-for="key in ['tanpa_pembatasan', 'dengan_pembatasan']"
                :key="key"
                :title="key.replace(/_/g, ' ')"
                flush
            >
                <div class="divide-y divide-slate-100">
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span class="text-slate-600">Total Pendapatan</span>
                        <MoneyText :amount="data[key]?.total_pendapatan ?? 0" size="sm" strong tone="default" />
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span class="text-slate-600">Total Beban</span>
                        <MoneyText :amount="data[key]?.total_beban ?? 0" size="sm" strong tone="default" />
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm font-bold">
                        <span class="text-slate-600">Surplus / Defisit</span>
                        <MoneyText
                            :amount="data[key]?.surplus ?? 0"
                            size="sm"
                            :tone="(data[key]?.surplus ?? 0) >= 0 ? 'emerald' : 'rose'"
                        />
                    </div>
                </div>
            </SectionCard>

            <UiCard variant="hero-dark" padding="md">
                <div class="text-center">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-white/80">Total Surplus</div>
                    <div class="mt-1">
                        <MoneyText :amount="data.surplus_total ?? 0" size="xl" tone="inherit" />
                    </div>
                </div>
            </UiCard>
        </template>

        <!-- Posisi Keuangan -->
        <template v-else-if="tab === 'posisi_keuangan' && data">
            <SectionCard title="Aset" flush>
                <div class="divide-y divide-slate-100">
                    <div v-for="item in (data.aset ?? [])" :key="item.kode"
                         class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span class="text-slate-600">{{ item.nama ?? item.kode }}</span>
                        <MoneyText :amount="item.saldo ?? 0" size="sm" strong tone="default" />
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm font-bold">
                        <span class="text-slate-600">Total Aset</span>
                        <MoneyText :amount="data.total_aset ?? 0" size="sm" tone="default" />
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Aset Neto" flush>
                <div class="divide-y divide-slate-100">
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span class="text-slate-600">Tanpa Pembatasan</span>
                        <MoneyText :amount="data.aset_neto?.tanpa_pembatasan ?? 0" size="sm" strong tone="default" />
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span class="text-slate-600">Dengan Pembatasan</span>
                        <MoneyText :amount="data.aset_neto?.dengan_pembatasan ?? 0" size="sm" strong tone="default" />
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm font-bold">
                        <span class="text-slate-600">Total Aset Neto</span>
                        <MoneyText :amount="data.aset_neto?.total ?? 0" size="sm" tone="emerald" />
                    </div>
                </div>
            </SectionCard>

            <div class="flex items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-semibold ring-1 ring-inset"
                 :class="data.seimbang ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-rose-50 text-rose-700 ring-rose-100'">
                <UiIcon :name="data.seimbang ? 'check-circle' : 'x-circle'" class="h-4 w-4" />
                {{ data.seimbang ? 'Neraca Seimbang' : 'Neraca Tidak Seimbang' }}
            </div>
        </template>
    </div>
</template>
