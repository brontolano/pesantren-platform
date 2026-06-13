<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    StatCard,
    StatusBadge,
    UiCard,
    SegmentedControl,
    MoneyText,
    LoadingState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })

const tab = ref('neraca')
const loading = ref(true)
const neraca = ref(null)
const lr = ref(null)

onMounted(async () => {
    try {
        const [n, l] = await Promise.all([
            api.get('/laporan/neraca'),
            api.get('/laporan/laba-rugi'),
        ])
        neraca.value = n
        lr.value = l
    } catch { /* */ }
    loading.value = false
})
</script>

<template>
    <div class="space-y-5">
        <PageHeader kicker="Laporan" title="Neraca & Laba Rugi" />

        <SegmentedControl
            v-model="tab"
            :options="[
                { value: 'neraca', label: 'Neraca' },
                { value: 'laba-rugi', label: 'Laba Rugi' },
            ]"
        />

        <LoadingState v-if="loading" variant="list" :count="6" />

        <!-- Neraca -->
        <template v-else-if="tab === 'neraca' && neraca">
            <SectionCard
                v-for="section in [
                    { nama: 'Aset', items: neraca.aset, total: neraca.total_aset },
                    { nama: 'Kewajiban', items: neraca.kewajiban },
                    { nama: 'Ekuitas', items: neraca.ekuitas },
                ]"
                :key="section.nama"
                :title="section.nama"
                flush
            >
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="row in section.items ?? []" :key="row.kode" :title="row.nama">
                        <template #trailing>
                            <MoneyText :amount="row.saldo" size="sm" />
                        </template>
                    </ListRow>
                    <ListRow v-if="section.total != null" :title="`Total ${section.nama}`">
                        <template #trailing>
                            <MoneyText :amount="section.total" size="sm" tone="emerald" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <StatCard
                v-if="neraca.surplus_berjalan != null"
                icon="chart"
                :tone="neraca.surplus_berjalan >= 0 ? 'emerald' : 'rose'"
                label="Surplus Berjalan"
            >
                <template #value>
                    <MoneyText
                        :amount="neraca.surplus_berjalan"
                        size="xl"
                        :tone="neraca.surplus_berjalan >= 0 ? 'emerald' : 'rose'"
                    />
                </template>
            </StatCard>

            <UiCard variant="hero-dark">
                <div class="flex justify-between text-xs text-white/70">
                    <span>Total Aset</span>
                    <span>Total Kewajiban + Ekuitas</span>
                </div>
                <div class="mt-1 flex items-center justify-between gap-3">
                    <MoneyText :amount="neraca.total_aset" size="lg" tone="inherit" />
                    <MoneyText :amount="neraca.total_kewajiban_ekuitas" size="lg" tone="inherit" />
                </div>
            </UiCard>

            <div class="flex justify-center">
                <StatusBadge
                    :status="neraca.seimbang ? 'aktif' : 'ditolak'"
                    :label="neraca.seimbang ? 'Neraca Seimbang' : 'Neraca Tidak Seimbang'"
                />
            </div>
        </template>

        <!-- Laba Rugi -->
        <template v-else-if="tab === 'laba-rugi' && lr">
            <SectionCard
                v-for="section in [
                    { nama: 'Pendapatan', items: lr.pendapatan, total: lr.total_pendapatan, tone: 'emerald' },
                    { nama: 'Beban', items: lr.beban, total: lr.total_beban, tone: 'rose' },
                ]"
                :key="section.nama"
                :title="section.nama"
                flush
            >
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="row in section.items ?? []" :key="row.kode" :title="row.nama">
                        <template #trailing>
                            <MoneyText :amount="row.saldo" size="sm" />
                        </template>
                    </ListRow>
                    <ListRow :title="`Total ${section.nama}`">
                        <template #trailing>
                            <MoneyText :amount="section.total" size="sm" :tone="section.tone" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <UiCard variant="hero-dark">
                <div class="text-center">
                    <div class="text-[11px] uppercase tracking-wider text-white/70">
                        {{ (lr.surplus_defisit ?? 0) >= 0 ? 'Surplus' : 'Defisit' }}
                    </div>
                    <div class="mt-1">
                        <MoneyText
                            :amount="Math.abs(lr.surplus_defisit ?? 0)"
                            size="xl"
                            tone="inherit"
                            :class="(lr.surplus_defisit ?? 0) < 0 ? 'text-rose-300' : 'text-white'"
                        />
                    </div>
                </div>
            </UiCard>
        </template>
    </div>
</template>
