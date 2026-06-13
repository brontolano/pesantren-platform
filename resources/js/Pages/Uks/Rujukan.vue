<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, PageHeader, StatCard, SectionCard, ListRow, StatusBadge, MoneyText, IconCircle, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    rujukan: { type: Array, default: () => [] },
})

const statusLabel = { menunggu: 'Menunggu', terverifikasi: 'Selesai' }

const totalBiaya = computed(() => props.rujukan.reduce((sum, r) => sum + (r.biaya || 0), 0))
const terbuka = computed(() => props.rujukan.filter((r) => r.status === 'menunggu').length)
</script>

<template>
    <Head title="Rujukan UKS" />

    <AppShell :nav-items="navigation" brand-name="Kesehatan UKS">
        <div class="space-y-5">
            <PageHeader kicker="Layanan UKS" title="Rujukan" subtitle="Santri dirujuk ke faskes luar" />

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="inbox" tone="rose" label="Rujukan Terbuka" :value="String(terbuka)" hint="menunggu jemputan" />
                <StatCard icon="cash" tone="emerald" label="Total Biaya">
                    <template #value>
                        <MoneyText :amount="totalBiaya" size="lg" tone="emerald" />
                    </template>
                </StatCard>
            </div>

            <SectionCard flush>
                <EmptyState
                    v-if="!rujukan.length"
                    icon="inbox"
                    title="Tidak ada rujukan"
                    message="Santri yang dirujuk ke Puskesmas atau RS akan tampil di sini."
                />
                <div v-else class="divide-y divide-slate-100">
                    <ListRow
                        v-for="r in rujukan"
                        :key="r.id"
                        :title="r.nama"
                        :subtitle="`${r.kelas} • ${r.tujuan}`"
                    >
                        <template #leading>
                            <IconCircle icon="bank" tone="blue" size="md" />
                        </template>
                        <template #trailing>
                            <MoneyText :amount="r.biaya" size="sm" tone="default" />
                        </template>

                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-sm text-slate-500">{{ r.keluhan }}</span>
                            <StatusBadge :status="r.status" :label="statusLabel[r.status]" size="sm" />
                        </div>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Biaya rujukan tercatat untuk klaim ke wali / dana kesehatan pesantren.
            </p>
        </div>
    </AppShell>
</template>
