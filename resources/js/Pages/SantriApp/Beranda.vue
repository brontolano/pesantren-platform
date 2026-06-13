<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { AppShell, HeroHeader, StatCard, SectionCard, ListRow, IconCircle } from '@/Shared'

defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: 'Santri Demo' },
    stats: { type: Array, default: () => [] },
    jadwalHariIni: { type: Array, default: () => [] },
    pengumuman: { type: Array, default: () => [] },
    domainTiles: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="Aplikasi Santri" />

    <AppShell :nav-items="navigation" brand-name="Aplikasi Santri">
        <template #header>
            <HeroHeader :name="userName" greeting="Assalamu'alaikum,">
                <template #subtitle>Aplikasi Santri Pesantren</template>
                Semoga harimu penuh berkah. Berikut ringkasanmu hari ini.
            </HeroHeader>
        </template>

        <div class="space-y-6">
            <div class="grid grid-cols-2 gap-3">
                <StatCard
                    v-for="s in stats"
                    :key="s.key"
                    :icon="s.icon"
                    :tone="s.tone"
                    :label="s.label"
                    :value="s.value"
                    :hint="s.hint"
                />
            </div>

            <SectionCard kicker="Hari ini" title="Jadwal Hari Ini" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="j in jadwalHariIni"
                        :key="j.id"
                        :title="j.pelajaran"
                        :subtitle="`${j.pengajar} • ${j.ruang}`"
                    >
                        <template #leading>
                            <IconCircle icon="book" tone="teal" size="md" />
                        </template>
                        <template #trailing>
                            <span class="text-sm font-bold tabular-nums text-slate-600">{{ j.jam }}</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <SectionCard kicker="Info" title="Pengumuman" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="p in pengumuman"
                        :key="p.id"
                        :title="p.judul"
                        :subtitle="p.ringkas"
                    >
                        <template #leading>
                            <IconCircle icon="bell" tone="rose" size="md" />
                        </template>
                        <template #trailing>
                            <span class="text-xs font-medium text-slate-400">{{ p.tanggal }}</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <div>
                <p class="px-1 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Jelajahi</p>
                <div class="grid grid-cols-3 gap-3">
                    <Link
                        v-for="t in domainTiles"
                        :key="t.key"
                        :href="t.href"
                        class="flex flex-col items-center gap-2 rounded-2xl bg-white px-2 py-4 text-center ring-1 ring-inset ring-slate-100 transition-all duration-200 hover:ring-slate-200 active:scale-95"
                    >
                        <IconCircle :icon="t.icon" :tone="t.tone" size="md" />
                        <span class="text-xs font-semibold text-slate-700">{{ t.label }}</span>
                    </Link>
                </div>
            </div>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Data peragaan. Buka tab di bawah untuk melihat jadwal, hafalan, keuangan, dan info ujian.
            </p>
        </div>
    </AppShell>
</template>
