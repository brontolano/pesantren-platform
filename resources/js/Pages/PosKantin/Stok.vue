<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, StatCard, ListRow, MoneyText, StatusBadge, IconCircle, EmptyState } from '@/Shared'

defineProps({
    navigation: { type: Array, default: () => [] },
    stats: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
})

const RENDAH = 5
</script>

<template>
    <Head title="POS Kantin — Stok" />

    <AppShell :nav-items="navigation" brand-name="POS Kantin">
        <div class="space-y-5">
            <PageHeader kicker="Inventaris" title="Stok Barang" subtitle="Pantau ketersediaan produk kantin" />

            <div class="grid grid-cols-3 gap-3">
                <StatCard
                    v-for="s in stats"
                    :key="s.key"
                    :icon="s.icon"
                    :tone="s.tone"
                    :label="s.label"
                    :value="s.value"
                    compact
                />
            </div>

            <SectionCard kicker="Daftar" title="Semua Produk" flush>
                <div v-if="items.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="it in items"
                        :key="it.id"
                        :title="it.nama"
                        :subtitle="it.kategori"
                    >
                        <template #leading>
                            <IconCircle
                                icon="inbox"
                                :tone="it.stok <= RENDAH ? 'amber' : 'emerald'"
                                variant="tint"
                                size="md"
                            />
                        </template>
                        <template #trailing>
                            <div class="flex flex-col items-end gap-1">
                                <StatusBadge
                                    v-if="it.stok <= RENDAH"
                                    status="info"
                                    label="Menipis"
                                    size="sm"
                                />
                                <span class="text-sm font-bold tabular-nums text-slate-800">{{ it.stok }} pcs</span>
                                <MoneyText :amount="it.harga" size="sm" tone="muted" />
                            </div>
                        </template>
                    </ListRow>
                </div>
                <div v-else class="p-2">
                    <EmptyState icon="inbox" title="Belum ada produk" message="Tambahkan item stok kantin." />
                </div>
            </SectionCard>
        </div>
    </AppShell>
</template>
