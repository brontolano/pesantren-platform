<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    SegmentedControl,
    LoadingState,
    EmptyState,
    ListRow,
    MoneyText,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })

const tab = ref('perubahan_dana')
const loading = ref(true)
const data = ref(null)

const tabs = [
    { key: 'perubahan_dana', label: 'Perubahan Dana' },
    { key: 'posisi_keuangan', label: 'Posisi Keuangan' },
    { key: 'aset_kelolaan', label: 'Aset Kelolaan' },
]

async function muat() {
    loading.value = true
    try { data.value = await api.get(`/laporan/psak109?jenis=${tab.value}`) } catch { /* */ }
    loading.value = false
}

function gantiTab(t) { tab.value = t; muat() }

onMounted(muat)

function danaRows(d) {
    return [
        { label: 'Saldo Awal', nilai: d?.saldo_awal ?? 0 },
        { label: 'Penerimaan', nilai: d?.penerimaan ?? 0, hijau: true },
        { label: 'Penyaluran', nilai: d?.penyaluran ?? 0, merah: true },
        { label: 'Saldo Akhir', nilai: d?.saldo_akhir ?? 0, bold: true },
    ]
}
</script>

<template>
    <div class="space-y-5">
        <PageHeader
            kicker="Laporan"
            title="Laporan PSAK 109"
            subtitle="Akuntansi Zakat, Infak/Sedekah"
        />

        <SegmentedControl
            :model-value="tab"
            :options="tabs.map(t => ({ value: t.key, label: t.label }))"
            @update:model-value="gantiTab"
        />

        <LoadingState v-if="loading" variant="card" :count="4" />

        <!-- Perubahan Dana -->
        <template v-else-if="tab === 'perubahan_dana' && data">
            <EmptyState
                v-if="!Object.keys(data).length"
                icon="chart"
                title="Belum ada data"
                message="Laporan perubahan dana belum tersedia untuk periode ini."
            />
            <SectionCard
                v-for="(dana, key) in data"
                v-else
                :key="key"
                :title="key.replace(/_/g, ' ')"
                flush
            >
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="r in danaRows(dana)" :key="r.label">
                        <template #title>
                            <span :class="r.bold ? 'font-bold text-slate-900' : 'font-medium text-slate-700'">
                                {{ r.label }}
                            </span>
                        </template>
                        <template #trailing>
                            <MoneyText
                                :amount="r.nilai"
                                size="sm"
                                :tone="r.hijau ? 'emerald' : r.merah ? 'rose' : r.bold ? 'default' : 'inherit'"
                                :strong="r.bold"
                            />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </template>

        <!-- Posisi Keuangan -->
        <template v-else-if="tab === 'posisi_keuangan' && data">
            <div class="space-y-5">
                <SectionCard title="Aset" flush>
                    <div class="divide-y divide-slate-100">
                        <ListRow v-for="(v, k) in data.aset" :key="k">
                            <template #title>
                                <span class="font-medium text-slate-700">{{ k.replace(/_/g, ' ') }}</span>
                            </template>
                            <template #trailing>
                                <MoneyText :amount="v" size="sm" tone="default" />
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>

                <SectionCard title="Saldo Dana" flush>
                    <div class="divide-y divide-slate-100">
                        <ListRow v-for="(v, k) in data.saldo_dana" :key="k">
                            <template #title>
                                <span :class="k === 'total' ? 'font-bold text-slate-900' : 'font-medium text-slate-700'">
                                    {{ k.replace(/_/g, ' ') }}
                                </span>
                            </template>
                            <template #trailing>
                                <MoneyText
                                    :amount="v"
                                    size="sm"
                                    :tone="k === 'total' ? 'emerald' : 'default'"
                                    :strong="k === 'total'"
                                />
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>
            </div>
        </template>

        <!-- Aset Kelolaan -->
        <template v-else-if="tab === 'aset_kelolaan' && data">
            <SectionCard title="Perubahan Aset Kelolaan" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="(v, k) in data" :key="k">
                        <template #title>
                            <span :class="k === 'saldo_akhir' ? 'font-bold text-slate-900' : 'font-medium text-slate-700'">
                                {{ k.replace(/_/g, ' ') }}
                            </span>
                        </template>
                        <template #trailing>
                            <MoneyText
                                :amount="v"
                                size="sm"
                                tone="default"
                                :strong="k === 'saldo_akhir'"
                            />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </template>
    </div>
</template>
