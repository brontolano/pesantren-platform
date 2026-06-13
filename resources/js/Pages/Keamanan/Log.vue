<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SegmentedControl, SectionCard, ListRow, StatusBadge, IconCircle, EmptyState } from '@/Shared'

// Log — timeline keluar/masuk gerbang dengan filter SegmentedControl.
const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    log: { type: Array, default: () => [] },
})

const filter = ref('semua')
const opsi = [
    { value: 'semua', label: 'Semua' },
    { value: 'keluar', label: 'Keluar', icon: 'arrow-up-right' },
    { value: 'masuk', label: 'Masuk', icon: 'arrow-down-left' },
    { value: 'tanpa-izin', label: 'Tanpa Izin', icon: 'ban' },
]

const terfilter = computed(() => {
    if (filter.value === 'semua') return props.log
    if (filter.value === 'tanpa-izin') return props.log.filter((l) => !l.izin)
    return props.log.filter((l) => l.arah === filter.value)
})

function badgeStatus(l) {
    if (!l.izin) return 'ditolak'
    return l.arah === 'keluar' ? 'info' : 'aktif'
}
function badgeLabel(l) {
    if (!l.izin) return 'Tanpa izin'
    return l.arah === 'keluar' ? 'Keluar' : 'Masuk'
}
</script>

<template>
    <Head title="Log Gerbang · Pos Keamanan" />

    <AppShell :nav-items="navigation" brand-name="Pos Keamanan">
        <div class="space-y-4">
            <PageHeader kicker="Riwayat" title="Log Gerbang" subtitle="Aktivitas keluar-masuk hari ini" />

            <SegmentedControl v-model="filter" :options="opsi" />

            <EmptyState
                v-if="!terfilter.length"
                icon="clock"
                title="Tidak ada catatan"
                message="Belum ada aktivitas pada filter ini."
            />

            <SectionCard v-else flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="l in terfilter"
                        :key="l.id"
                        :title="l.nama"
                        :subtitle="`${l.kelas} · ${l.ket}`"
                    >
                        <template #leading>
                            <IconCircle
                                :icon="!l.izin ? 'ban' : l.arah === 'keluar' ? 'arrow-up-right' : 'arrow-down-left'"
                                :tone="!l.izin ? 'rose' : l.arah === 'keluar' ? 'amber' : 'emerald'"
                                size="md"
                            />
                        </template>
                        <template #trailing>
                            <div class="flex flex-col items-end gap-1.5">
                                <span class="font-jost text-xs font-semibold tabular-nums text-slate-400">{{ l.jam }}</span>
                                <StatusBadge :status="badgeStatus(l)" :label="badgeLabel(l)" size="sm" />
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Mode peragaan — data log adalah contoh untuk demonstrasi.
            </p>
        </div>
    </AppShell>
</template>
