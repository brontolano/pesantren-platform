<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, PageHeader, StatCard, SectionCard, ListRow, StatusBadge, Chip, IconCircle } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    obat: { type: Array, default: () => [] },
})

const menipis = (stok) => stok <= 5

const totalItem = computed(() => props.obat.length)
const jumlahMenipis = computed(() => props.obat.filter((o) => menipis(o.stok)).length)
</script>

<template>
    <Head title="Stok Obat UKS" />

    <AppShell :nav-items="navigation" brand-name="Kesehatan UKS">
        <div class="space-y-5">
            <PageHeader kicker="Apotek UKS" title="Stok Obat" subtitle="Persediaan obat & alat kesehatan" />

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="receipt" tone="blue" label="Total Item" :value="String(totalItem)" hint="jenis obat" />
                <StatCard icon="info" tone="amber" label="Perlu Restock" :value="String(jumlahMenipis)" hint="stok ≤ 5" />
            </div>

            <SectionCard flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="o in obat"
                        :key="o.id"
                        :title="o.nama"
                    >
                        <template #leading>
                            <IconCircle icon="receipt" :tone="menipis(o.stok) ? 'amber' : 'teal'" size="md" />
                        </template>
                        <template #subtitle>
                            <span class="inline-flex items-center gap-2">
                                <Chip :active="false" tone="soft" size="sm">{{ o.satuan }}</Chip>
                            </span>
                        </template>
                        <template #trailing>
                            <div class="flex flex-col items-end gap-1.5">
                                <span class="text-lg font-extrabold tabular-nums text-slate-900">{{ o.stok }}</span>
                                <StatusBadge v-if="menipis(o.stok)" status="info" label="Menipis" size="sm" />
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Obat dengan stok 5 atau kurang ditandai "Menipis" — segera ajukan pembelian ke bendahara.
            </p>
        </div>
    </AppShell>
</template>
