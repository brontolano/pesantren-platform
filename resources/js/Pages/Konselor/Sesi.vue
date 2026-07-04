<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, IconCircle, StatusBadge, SegmentedControl, EmptyState } from '@/Shared'

const props = defineProps({
    sesi: { type: Array, default: () => [] },
    statusAktif: { type: String, default: 'semua' },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])

const statusOptions = [
    { value: 'semua', label: 'Semua' },
    { value: 'terjadwal', label: 'Terjadwal' },
    { value: 'selesai', label: 'Selesai' },
    { value: 'dibatalkan', label: 'Dibatalkan' },
]

function pilihStatus(value) {
    router.get('/konselor/sesi', { status: value }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Konselor — Sesi" />

    <AppShell :nav-items="navItems" brand-name="Konselor">
        <div class="space-y-5">
            <PageHeader kicker="Konseling" title="Sesi" subtitle="Daftar sesi konseling santri" />

            <SegmentedControl :model-value="statusAktif" :options="statusOptions" @update:model-value="pilihStatus" />

            <SectionCard flush>
                <div v-if="sesi.length" class="divide-y divide-slate-100">
                    <ListRow v-for="s in sesi" :key="s.id" :title="s.santri ?? '—'" :subtitle="s.topik">
                        <template #leading>
                            <IconCircle icon="chat" tone="emerald" size="sm" />
                        </template>
                        <template #trailing>
                            <div class="flex flex-col items-end gap-1">
                                <StatusBadge :status="s.status" size="sm" />
                                <span class="text-xs text-slate-400">{{ s.tanggal }}</span>
                            </div>
                        </template>
                    </ListRow>
                </div>
                <EmptyState v-else icon="chat" title="Tidak ada sesi" message="Belum ada sesi konseling pada filter ini." />
            </SectionCard>
        </div>
    </AppShell>
</template>
