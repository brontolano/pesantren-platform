<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, HeroHeader, PageHeader, StatCard, SectionCard, ListRow, StatusBadge, IconCircle } from '@/Shared'

// Beranda Pos Keamanan — ringkasan kondisi gerbang + menu pintas + aktivitas
// terkini. Data dummy dari controller (DEMO-GRADE).
const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    userName: { type: String, default: '' },
    stats: { type: Array, default: () => [] },
    menu: { type: Array, default: () => [] },
    terkini: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="Pos Keamanan" />

    <AppShell :nav-items="navigation" brand-name="Pos Keamanan">
        <template #header>
            <HeroHeader :name="userName || 'Petugas Keamanan'" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/16 px-2.5 py-1 text-[11px] font-semibold text-white ring-1 ring-inset ring-white/25">
                        Petugas Keamanan · Pos Gerbang
                    </span>
                </template>
            </HeroHeader>
        </template>

        <div class="space-y-5">
            <div class="grid grid-cols-2 gap-3">
                <StatCard
                    v-for="s in stats"
                    :key="s.key"
                    :icon="s.icon"
                    :tone="s.tone"
                    :label="s.label"
                    :value="String(s.value)"
                    :hint="s.hint"
                    compact
                />
            </div>

            <section class="space-y-3">
                <PageHeader kicker="Aksi Cepat" title="Menu Pos" />
                <div class="grid grid-cols-2 gap-3">
                    <a
                        v-for="m in menu"
                        :key="m.key"
                        :href="m.href"
                        class="flex flex-col gap-2.5 rounded-3xl bg-white p-4 ring-1 ring-slate-100 shadow-soft transition-all duration-200 active:scale-[.97]"
                    >
                        <IconCircle :icon="m.icon" :tone="m.tone" size="md" />
                        <div>
                            <p class="font-jost text-[14px] font-bold leading-tight text-slate-900">{{ m.label }}</p>
                            <p class="mt-0.5 text-[11.5px] leading-snug text-slate-400">{{ m.desc }}</p>
                        </div>
                    </a>
                </div>
            </section>

            <SectionCard kicker="Hari Ini" title="Aktivitas Terkini" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="(t, i) in terkini"
                        :key="i"
                        :title="t.nama"
                        :subtitle="`${t.aksi} · ${t.kelas}`"
                    >
                        <template #leading>
                            <IconCircle
                                :icon="t.status === 'ditolak' ? 'ban' : t.status === 'info' ? 'user' : 'arrow-up-right'"
                                :tone="t.status === 'ditolak' ? 'rose' : t.status === 'info' ? 'blue' : 'amber'"
                                size="sm"
                            />
                        </template>
                        <template #trailing>
                            <span class="font-jost text-xs font-semibold tabular-nums text-slate-400">{{ t.jam }}</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Mode peragaan — semua data contoh. Aksi catat keluar/masuk & ronda hanya simulasi di perangkat ini.
            </p>
        </div>
    </AppShell>
</template>
