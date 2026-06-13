<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatCard, StatusBadge, IconCircle, SegmentedControl } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    absensi: { type: Array, default: () => [] },
})

// SegmentedControl 7 hari (demo: hanya rekap hari terpilih, data tetap).
const hari = ref('sen')
const hariOptions = [
    { value: 'sen', label: 'Sen' },
    { value: 'sel', label: 'Sel' },
    { value: 'rab', label: 'Rab' },
    { value: 'kam', label: 'Kam' },
    { value: 'jum', label: 'Jum' },
    { value: 'sab', label: 'Sab' },
    { value: 'min', label: 'Min' },
]

const STATUS_META = {
    hadir: { badge: 'aktif', label: 'Hadir', icon: 'check-circle', tone: 'emerald' },
    izin: { badge: 'info', label: 'Izin', icon: 'document', tone: 'amber' },
    alpa: { badge: 'ditolak', label: 'Alpa', icon: 'x-circle', tone: 'rose' },
}
const metaFor = (s) => STATUS_META[s] ?? STATUS_META.hadir

const labelHari = computed(() => hariOptions.find((o) => o.value === hari.value)?.label ?? '')
</script>

<template>
    <Head title="Absensi" />

    <AppShell :nav-items="navigation" brand-name="HRD Kepegawaian">
        <div class="space-y-5">
            <PageHeader kicker="Kehadiran" title="Absensi Pegawai" :subtitle="`Rekap hari ${labelHari}`" />

            <SegmentedControl v-model="hari" :options="hariOptions" />

            <div class="grid grid-cols-3 gap-3">
                <StatCard icon="check-circle" tone="emerald" label="Hadir" :value="String(ringkasan.hadir ?? 0)" compact />
                <StatCard icon="document" tone="amber" label="Izin" :value="String(ringkasan.izin ?? 0)" compact />
                <StatCard icon="x-circle" tone="rose" label="Alpa" :value="String(ringkasan.alpa ?? 0)" compact />
            </div>

            <SectionCard kicker="Daftar" title="Status Kehadiran" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="(a, i) in absensi"
                        :key="i"
                        :title="a.nama"
                        :subtitle="a.jabatan"
                    >
                        <template #leading><IconCircle :icon="metaFor(a.status).icon" :tone="metaFor(a.status).tone" /></template>
                        <template #trailing>
                            <StatusBadge :status="metaFor(a.status).badge" :label="metaFor(a.status).label" size="sm" />
                            <p class="mt-1 text-xs font-medium tabular-nums text-slate-400">{{ a.jam }}</p>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </div>
    </AppShell>
</template>
