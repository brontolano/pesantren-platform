<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, HeroHeader, StatCard, SectionCard, ListRow, StatusBadge, IconCircle, Chip } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    userName: { type: String, default: 'Santri Pengabdian' },
    penugasan: { type: Object, default: () => ({}) },
    stats: { type: Array, default: () => [] },
    tugasTerdekat: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="Pengabdian Santri" />

    <AppShell :nav-items="navigation" brand-name="Pengabdian Santri">
        <template #header>
            <HeroHeader :name="userName" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <Chip :active="true" tone="soft" size="sm" icon="heart">
                        {{ penugasan.unit || 'Khidmah' }}
                    </Chip>
                </template>

                <div class="grid grid-cols-3 gap-2">
                    <div
                        v-for="s in stats"
                        :key="s.key"
                        class="rounded-2xl bg-white/15 px-3 py-3 text-center ring-1 ring-inset ring-white/20"
                    >
                        <p class="text-lg font-extrabold leading-none text-white">{{ s.value }}</p>
                        <p class="mt-1 text-[10.5px] font-medium leading-tight text-white/75">{{ s.label }}</p>
                    </div>
                </div>
            </HeroHeader>
        </template>

        <div class="space-y-5">
            <SectionCard kicker="Penugasan Aktif" :title="penugasan.unit || 'Khidmah'" :subtitle="penugasan.periode">
                <div class="space-y-2.5">
                    <div class="flex items-center gap-3">
                        <IconCircle icon="user" tone="emerald" size="sm" />
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pembimbing</p>
                            <p class="truncate text-sm font-semibold text-slate-800">{{ penugasan.pembimbing || '—' }}</p>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <div class="grid grid-cols-3 gap-3">
                <StatCard
                    v-for="s in stats"
                    :key="s.key"
                    :icon="s.icon"
                    :tone="s.tone"
                    :label="s.label"
                    :value="s.value"
                    :hint="s.hint"
                    compact
                />
            </div>

            <SectionCard kicker="Agenda" title="Tugas Terdekat" subtitle="Khidmah yang menanti dilaksanakan" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="t in tugasTerdekat"
                        :key="t.id"
                        :title="t.judul"
                        :subtitle="`${t.waktu} · ${t.lokasi}`"
                    >
                        <template #leading>
                            <IconCircle icon="calendar" tone="teal" size="md" />
                        </template>
                        <template #trailing>
                            <StatusBadge :status="t.status" size="sm" :label="t.status === 'aktif' ? 'Hari ini' : 'Terjadwal'" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Khidmah adalah ladang amal. Hadir tepat waktu dan tunaikan setiap tugas dengan ikhlas.
            </p>
        </div>
    </AppShell>
</template>
