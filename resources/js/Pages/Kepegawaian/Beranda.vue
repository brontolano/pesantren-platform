<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { AppShell, HeroHeader, StatCard, SectionCard, ListRow, IconCircle, StatusBadge } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: '' },
    stats: { type: Object, default: () => ({}) },
    kontrakSegera: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="HRD Kepegawaian" />

    <AppShell :nav-items="navigation" brand-name="HRD Kepegawaian">
        <template #header>
            <HeroHeader :name="userName || 'Staf HRD'" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <span class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-0.5 text-[11px] font-semibold text-white ring-1 ring-inset ring-white/25">
                        Staf HRD
                    </span>
                </template>
            </HeroHeader>
        </template>

        <div class="space-y-5">
            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="user" tone="emerald" label="Pegawai Aktif" :value="String(stats.pegawai_aktif ?? 0)" hint="Total tenaga kependidikan" />
                <StatCard icon="check-circle" tone="teal" label="Hadir Hari Ini" :value="`${stats.persen_hadir ?? 0}%`" hint="Kehadiran terkini" />
                <StatCard icon="receipt" tone="blue" label="Slip Bulan Ini" :value="stats.slip_bulan ?? '—'" hint="Periode penggajian aktif" />
                <StatCard icon="calendar" tone="amber" label="Kontrak ≤ 60 Hari" :value="String(stats.kontrak_segera ?? 0)" hint="Perlu perpanjangan" />
            </div>

            <SectionCard kicker="Perhatian" title="Kontrak Segera Berakhir" subtitle="Pegawai dengan masa kontrak ≤ 60 hari" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="k in kontrakSegera"
                        :key="k.nama"
                        :title="k.nama"
                        :subtitle="`${k.jabatan} · berakhir ${k.berakhir}`"
                    >
                        <template #leading><IconCircle icon="clock" tone="amber" /></template>
                        <template #trailing>
                            <StatusBadge status="info" :label="`${k.sisa_hari} hari`" size="sm" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <SectionCard kicker="Akses Cepat" title="Pintasan HRD" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow href="/kepegawaian/pegawai" title="Data Pegawai" subtitle="Profil, jabatan & kontrak" chevron>
                        <template #leading><IconCircle icon="user" tone="emerald" /></template>
                    </ListRow>
                    <ListRow href="/kepegawaian/absensi" title="Absensi Hari Ini" subtitle="Rekap hadir, izin & alpa" chevron>
                        <template #leading><IconCircle icon="check-circle" tone="teal" /></template>
                    </ListRow>
                    <ListRow href="/kepegawaian/slip" title="Slip Gaji" subtitle="Periode berjalan" chevron>
                        <template #leading><IconCircle icon="receipt" tone="blue" /></template>
                    </ListRow>
                    <ListRow href="/kepegawaian/cuti" title="Pengajuan Cuti" subtitle="Setujui atau tolak" chevron>
                        <template #leading><IconCircle icon="calendar" tone="amber" /></template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Aplikasi demo HRD — data peragaan, tidak terhubung ke catatan kepegawaian nyata.
            </p>
        </div>
    </AppShell>
</template>
