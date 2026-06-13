<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, StatCard, ListRow, MoneyText, IconCircle, SegmentedControl, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    saldoTotal: { type: Number, default: 0 },
    jumlahSantri: { type: Number, default: 0 },
    mutasi: { type: Array, default: () => [] },
})

const filter = ref('semua')
const filterOptions = [
    { value: 'semua', label: 'Semua' },
    { value: 'topup', label: 'Top Up', icon: 'arrow-down-left' },
    { value: 'belanja', label: 'Belanja', icon: 'arrow-up-right' },
]

const mutasiTampil = computed(() =>
    filter.value === 'semua' ? props.mutasi : props.mutasi.filter((m) => m.jenis === filter.value),
)
</script>

<template>
    <Head title="POS Kantin — Tabungan" />

    <AppShell :nav-items="navigation" brand-name="POS Kantin">
        <div class="space-y-5">
            <PageHeader kicker="Titipan Santri" title="Tabungan" subtitle="Saldo titipan & mutasi kantin" />

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="wallet" tone="emerald" label="Saldo Titipan" compact>
                    <template #value>
                        <MoneyText :amount="saldoTotal" size="lg" tone="emerald" />
                    </template>
                </StatCard>
                <StatCard icon="user" tone="teal" label="Santri Aktif" :value="String(jumlahSantri)" compact />
            </div>

            <SegmentedControl v-model="filter" :options="filterOptions" />

            <SectionCard kicker="Aktivitas" title="Mutasi Terbaru" flush>
                <div v-if="mutasiTampil.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="m in mutasiTampil"
                        :key="m.id"
                        :title="m.santri"
                        :subtitle="m.waktu"
                    >
                        <template #leading>
                            <IconCircle
                                :icon="m.jenis === 'topup' ? 'arrow-down-left' : 'arrow-up-right'"
                                :tone="m.jenis === 'topup' ? 'emerald' : 'rose'"
                                variant="tint"
                                size="md"
                            />
                        </template>
                        <template #trailing>
                            <MoneyText
                                :amount="m.nominal"
                                size="sm"
                                :tone="m.jenis === 'topup' ? 'emerald' : 'rose'"
                                :sign="m.jenis === 'topup' ? 'plus' : 'minus'"
                            />
                            <p class="mt-0.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                {{ m.jenis === 'topup' ? 'Top Up' : 'Belanja' }}
                            </p>
                        </template>
                    </ListRow>
                </div>
                <div v-else class="p-2">
                    <EmptyState icon="wallet" title="Belum ada mutasi" message="Tidak ada aktivitas untuk filter ini." />
                </div>
            </SectionCard>
        </div>
    </AppShell>
</template>
