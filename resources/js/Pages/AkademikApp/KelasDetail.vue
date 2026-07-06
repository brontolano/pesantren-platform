<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, ListRow, EmptyState } from '@/Shared'

defineProps({
    kelas: { type: Object, required: true },
    santri: { type: Array, default: () => [] },
})

const nav = [
    { key: 'beranda', label: 'Beranda', href: '/staf', active: false },
    { key: 'kelas', label: 'Kelas', href: '/akademik-app/kelas', active: true },
    { key: 'presensi', label: 'Presensi', href: '/akademik-app/presensi', active: false },
]

const statusClass = {
    aktif: 'bg-emerald-100 text-emerald-700',
    lulus: 'bg-sky-100 text-sky-700',
    keluar: 'bg-slate-100 text-slate-600',
}
</script>

<template>
    <AppShell :nav-items="nav" brand-name="Akademik">
        <Head><title>{{ kelas.nama }}</title></Head>
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                <Link href="/akademik-app/kelas" class="text-slate-400 transition hover:text-slate-700" aria-label="Kembali">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <PageHeader kicker="Isi Kelas" :title="kelas.nama" :subtitle="[kelas.tingkat ? `Tingkat ${kelas.tingkat}` : null, kelas.wali ? `Wali: ${kelas.wali}` : null].filter(Boolean).join(' · ')" class="flex-1" />
            </div>

            <EmptyState v-if="santri.length === 0" icon="user" title="Belum ada santri" message="Santri yang ditempatkan di kelas ini akan tampil di sini." />

            <SectionCard v-else title="Santri" :subtitle="`${santri.length} santri`" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="s in santri" :key="s.id" :title="s.nama" :subtitle="s.nis">
                        <template #trailing>
                            <span class="rounded-full px-3 py-1 text-xs font-bold capitalize" :class="statusClass[s.status] || 'bg-slate-100 text-slate-600'">{{ s.status }}</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </div>
    </AppShell>
</template>
