<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, IconCircle, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    pengajuan: { type: Array, default: () => [] },
})

const JENIS_META = {
    'Cuti Tahunan': { icon: 'calendar', tone: 'blue' },
    'Cuti Sakit': { icon: 'heart', tone: 'rose' },
    'Cuti Melahirkan': { icon: 'heart', tone: 'amber' },
}
const metaFor = (j) => JENIS_META[j] ?? { icon: 'calendar', tone: 'slate' }
</script>

<template>
    <Head title="Pengajuan Cuti" />

    <AppShell :nav-items="navigation" brand-name="HRD Kepegawaian">
        <div class="space-y-5">
            <PageHeader kicker="Kepegawaian" title="Pengajuan Cuti" subtitle="Daftar pengajuan cuti pegawai (read-only)" />

            <p class="rounded-2xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500 ring-1 ring-inset ring-slate-100">
                Persetujuan/penolakan cuti dilakukan lewat panel Admin (alur approval lintas-modul) —
                halaman ini hanya untuk memantau status.
            </p>

            <SectionCard v-if="pengajuan.length" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="c in pengajuan" :key="c.id" :title="c.nama" :subtitle="`${c.jenis} · ${c.tanggal_mulai} – ${c.tanggal_selesai}`">
                        <template #leading><IconCircle :icon="metaFor(c.jenis).icon" :tone="metaFor(c.jenis).tone" /></template>
                        <template #trailing>
                            <StatusBadge :status="c.status" size="sm" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <EmptyState
                v-else
                icon="calendar"
                title="Tidak ada pengajuan"
                message="Belum ada pengajuan cuti tercatat."
            />
        </div>
    </AppShell>
</template>
