<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, IconCircle, EmptyState } from '@/Shared'

defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: 'Santri Pengabdian' },
    kegiatanHariIni: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="Presensi Khidmah" />

    <AppShell :nav-items="navigation" brand-name="Pengabdian Santri">
        <div class="space-y-5">
            <PageHeader kicker="Kehadiran" title="Presensi Khidmah" subtitle="Kegiatan hari ini & status kehadiranmu" />

            <p class="rounded-2xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500 ring-1 ring-inset ring-slate-100">
                Presensi dicatat oleh pembimbing/panitia kegiatan — halaman ini menampilkan status kehadiranmu.
            </p>

            <SectionCard v-if="kegiatanHariIni.length" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="item in kegiatanHariIni"
                        :key="item.id"
                        :title="item.judul"
                        :subtitle="`${item.waktu} · ${item.lokasi}`"
                    >
                        <template #leading>
                            <IconCircle icon="check-circle" tone="emerald" size="md" />
                        </template>
                        <template #trailing>
                            <StatusBadge v-if="item.status" :status="item.status" size="sm" />
                            <span v-else class="text-xs font-semibold text-slate-400">Belum diisi</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <EmptyState
                v-else
                icon="check-circle"
                title="Tidak ada kegiatan hari ini"
                message="Kegiatan khidmah hari ini akan tampil di sini."
            />
        </div>
    </AppShell>
</template>
