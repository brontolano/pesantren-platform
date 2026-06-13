<script setup>
import { onMounted, ref, watch } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    StatCard,
    MoneyText,
    UiField,
    IconCircle,
    LoadingState,
    EmptyState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })

const tahun = ref(String(new Date().getFullYear()))
const loading = ref(true)
const data = ref(null)

async function muat() {
    loading.value = true
    try { data.value = await api.get(`/laporan/rekap-tahunan?tahun=${tahun.value}`) } catch { /* */ }
    loading.value = false
}

onMounted(muat)
</script>

<template>
    <div class="space-y-5">
        <PageHeader
            kicker="Laporan Keuangan"
            title="Rekap Tahunan"
            subtitle="Rekap Penerimaan & Penyaluran Tahunan"
        />

        <UiField label="Tahun anggaran" hint="Periode Januari — Desember">
            <template #default="{ fieldId, describedby }">
                <div class="flex items-center gap-3">
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
                    <span class="text-sm font-medium text-slate-500">Januari — Desember {{ tahun }}</span>
                </div>
            </template>
        </UiField>

        <LoadingState v-if="loading" variant="list" :count="6" />

        <template v-else-if="data">
            <!-- Penerimaan -->
            <SectionCard kicker="Penerimaan" title="Penerimaan" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="r in data.penerimaan" :key="r.kode" :title="r.nama">
                        <template #trailing>
                            <MoneyText :amount="r.nominal" size="sm" tone="default" />
                        </template>
                    </ListRow>
                    <ListRow>
                        <template #title>
                            <span class="font-extrabold text-slate-900">Total Penerimaan</span>
                        </template>
                        <template #trailing>
                            <MoneyText :amount="data.total_penerimaan" size="md" tone="emerald" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <!-- Penyaluran -->
            <SectionCard kicker="Penyaluran" title="Penggunaan & Penyaluran Dana" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="r in data.penyaluran" :key="r.kode" :title="r.nama">
                        <template #trailing>
                            <MoneyText :amount="r.nominal" size="sm" tone="default" />
                        </template>
                    </ListRow>
                    <ListRow>
                        <template #title>
                            <span class="font-extrabold text-slate-900">Total Penyaluran</span>
                        </template>
                        <template #trailing>
                            <MoneyText :amount="data.total_penyaluran" size="md" tone="rose" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <!-- Surplus / Defisit -->
            <StatCard
                :tone="(data.surplus_defisit ?? 0) < 0 ? 'rose' : 'emerald'"
                :label="`${(data.surplus_defisit ?? 0) >= 0 ? 'Surplus' : 'Defisit'} Tahun ${data.tahun}`"
            >
                <template #value>
                    <MoneyText
                        :amount="Math.abs(data.surplus_defisit ?? 0)"
                        size="xl"
                        :tone="(data.surplus_defisit ?? 0) < 0 ? 'rose' : 'emerald'"
                    />
                </template>

                <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs">
                    <span class="inline-flex items-center gap-1.5 text-slate-500">
                        <IconCircle icon="arrow-down-left" tone="emerald" size="sm" />
                        <span class="tabular-nums">{{ formatRupiahFull(data.total_penerimaan) }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-slate-500">
                        <IconCircle icon="arrow-up-right" tone="rose" size="sm" />
                        <span class="tabular-nums">{{ formatRupiahFull(data.total_penyaluran) }}</span>
                    </span>
                </div>
            </StatCard>

            <!-- Tanda tangan -->
            <SectionCard kicker="Pengesahan" title="Tanda Tangan">
                <div class="flex justify-between text-center text-xs text-slate-500">
                    <div>
                        <div>Mengetahui,</div>
                        <div class="font-semibold text-slate-700">Ketua Yayasan</div>
                        <div class="mt-10 border-t border-slate-200 pt-1">(.....................)</div>
                    </div>
                    <div>
                        <div>&nbsp;</div>
                        <div class="font-semibold text-slate-700">Bendahara</div>
                        <div class="mt-10 border-t border-slate-200 pt-1">(.....................)</div>
                    </div>
                </div>
            </SectionCard>
        </template>

        <EmptyState
            v-else
            icon="chart"
            title="Belum ada data"
            :message="`Tidak ada data untuk tahun ${tahun}`"
        />
    </div>
</template>
