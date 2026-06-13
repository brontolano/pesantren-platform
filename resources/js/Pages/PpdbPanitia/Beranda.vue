<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    AppShell, HeroHeader, PageHeader, StatCard, SectionCard, ListRow,
    StatusBadge, Chip,
} from '@/Shared'

const props = defineProps({
    userName: { type: String, default: '' },
    navigation: { type: Array, default: () => [] },
    gelombang: { type: Array, default: () => [] },
    statistik: { type: Object, default: () => ({}) },
    agenda: { type: Array, default: () => [] },
})

// Peta key nav -> ikon whitelist UiIcon.
const navIcons = {
    beranda: 'chart',
    pendaftar: 'user',
    verifikasi: 'check-circle',
    hasil: 'document',
}

const gelAktif = ref(props.gelombang[0]?.value ?? 1)
const stat = computed(() => props.statistik[gelAktif.value] ?? { total: 0, menunggu: 0, lulus: 0, ditolak: 0 })
</script>

<template>
    <Head title="PPDB Panitia" />

    <AppShell :nav-items="navigation" :nav-icons="navIcons" brand-name="PPDB Panitia">
        <template #header>
            <HeroHeader :name="userName || 'Panitia'" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold text-white ring-1 ring-inset ring-white/25">
                        Panitia PPDB
                    </span>
                </template>
            </HeroHeader>
        </template>

        <div class="space-y-6">
            <!-- Pemilih gelombang -->
            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <Chip
                    v-for="g in gelombang"
                    :key="g.value"
                    :active="g.value === gelAktif"
                    size="sm"
                    @click="gelAktif = g.value"
                >
                    {{ g.label }}
                </Chip>
            </div>

            <!-- Statistik gelombang aktif -->
            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="user" tone="blue" label="Total Pendaftar" :value="String(stat.total)" hint="calon santri" />
                <StatCard icon="clock" tone="amber" label="Menunggu Verif." :value="String(stat.menunggu)" hint="perlu ditinjau" />
                <StatCard icon="check-circle" tone="emerald" label="Lulus" :value="String(stat.lulus)" hint="diterima" />
                <StatCard icon="x-circle" tone="rose" label="Ditolak" :value="String(stat.ditolak)" hint="tidak lolos" />
            </div>

            <!-- Agenda PPDB -->
            <SectionCard kicker="Jadwal" title="Agenda Terdekat" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="(a, i) in agenda"
                        :key="i"
                        :title="a.label"
                        :subtitle="a.tanggal"
                    >
                        <template #leading>
                            <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                            </span>
                        </template>
                        <template #trailing>
                            <StatusBadge :status="a.status" size="sm" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <PageHeader
                kicker="Pintasan"
                title="Mulai bekerja"
                subtitle="Tinjau pendaftar, verifikasi berkas, lalu putuskan kelulusan dari menu di bawah."
            />
        </div>
    </AppShell>
</template>
