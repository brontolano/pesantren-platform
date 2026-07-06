<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, StatCard, ListRow, EmptyState } from '@/Shared'

const props = defineProps({
    tanggal: { type: String, required: true },
    ringkas: { type: Object, default: () => ({ hadir: 0, izin: 0, sakit: 0, alpa: 0 }) },
    rows: { type: Array, default: () => [] },
})

const nav = [
    { key: 'beranda', label: 'Beranda', href: '/staf', active: false },
    { key: 'kelas', label: 'Kelas', href: '/akademik-app/kelas', active: false },
    { key: 'presensi', label: 'Presensi', href: '/akademik-app/presensi', active: true },
]

const tgl = ref(props.tanggal)
function muat() {
    router.get('/akademik-app/presensi', { tanggal: tgl.value }, { preserveState: true, preserveScroll: true })
}

const statusClass = {
    hadir: 'bg-emerald-100 text-emerald-700',
    izin: 'bg-sky-100 text-sky-700',
    sakit: 'bg-amber-100 text-amber-700',
    alpa: 'bg-rose-100 text-rose-700',
}
</script>

<template>
    <AppShell :nav-items="nav" brand-name="Akademik">
        <Head><title>Rekap Presensi</title></Head>
        <div class="space-y-5">
            <PageHeader kicker="Pembelajaran" title="Rekap Presensi Santri" subtitle="Rekap per tanggal — input presensi dilakukan guru di Aplikasi Guru." />

            <input
                v-model="tgl" type="date"
                class="w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-[var(--brand-primary)] focus:ring-2 focus:ring-[var(--brand-primary)]/20"
                @change="muat"
            />

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <StatCard tone="emerald" label="Hadir" :value="ringkas.hadir" compact />
                <StatCard tone="blue" label="Izin" :value="ringkas.izin" compact />
                <StatCard tone="amber" label="Sakit" :value="ringkas.sakit" compact />
                <StatCard tone="rose" label="Alpa" :value="ringkas.alpa" compact />
            </div>

            <EmptyState v-if="rows.length === 0" icon="check-circle" title="Belum ada presensi" message="Belum ada catatan presensi pada tanggal ini." />

            <SectionCard v-else title="Rincian" :subtitle="`${rows.length} catatan`" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="r in rows" :key="r.id" :title="r.nama" :subtitle="`${r.nis || '-'} · ${r.kelas || '-'}`">
                        <template #trailing>
                            <span class="rounded-full px-3 py-1 text-xs font-bold capitalize" :class="statusClass[r.status] || 'bg-slate-100 text-slate-600'">{{ r.status }}</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </div>
    </AppShell>
</template>
