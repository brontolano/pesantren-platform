<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, IconCircle, EmptyState } from '@/Shared'

defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: 'Santri Pengabdian' },
    laporan: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="Laporan Khidmah" />

    <AppShell :nav-items="navigation" brand-name="Pengabdian Santri">
        <div class="space-y-5">
            <PageHeader kicker="Dokumentasi" title="Laporan Khidmah" subtitle="Riwayat kehadiran & keterangan kegiatan" />

            <SectionCard flush>
                <div v-if="laporan.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="lap in laporan"
                        :key="lap.id"
                        :title="lap.judul"
                        :subtitle="lap.tanggal"
                    >
                        <template #leading>
                            <IconCircle icon="document" tone="emerald" size="md" />
                        </template>
                        <template #trailing>
                            <StatusBadge :status="lap.status" size="sm" />
                        </template>
                        <p class="text-sm leading-6 text-slate-500">{{ lap.ringkasan }}</p>
                    </ListRow>
                </div>
                <EmptyState
                    v-else
                    icon="document"
                    title="Belum ada laporan"
                    message="Kehadiran kegiatan khidmah yang tercatat akan tampil di sini."
                />
            </SectionCard>
        </div>
    </AppShell>
</template>
