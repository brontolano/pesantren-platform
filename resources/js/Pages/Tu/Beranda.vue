<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import {
    AppShell, HeroHeader, StatCard, SectionCard, ListRow, IconCircle, StatusBadge,
} from '@/Shared'

/**
 * Beranda PWA Tata Usaha — ringkasan StatCard + pintasan + agenda terbaru.
 * Data dari controller (dummy). Root = AppShell (HeroHeader hanya di sini).
 */
const props = defineProps({
    userName: { type: String, default: 'Staf TU' },
    stats: { type: Array, default: () => [] },
    pintasan: { type: Array, default: () => [] },
    agendaTerbaru: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])
</script>

<template>
    <AppShell :nav-items="navItems" brand-name="Tata Usaha">
        <template #header>
            <HeroHeader :name="userName" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <StatusBadge status="aktif" label="Staf Tata Usaha" size="sm" />
                </template>
            </HeroHeader>
        </template>

        <div class="space-y-6">
            <!-- Ringkasan -->
            <section>
                <div class="grid grid-cols-2 gap-3">
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
            </section>

            <!-- Pintasan -->
            <section>
                <p class="mb-3 font-jost text-[11px] font-semibold uppercase tracking-wider text-violet-700">
                    Pintasan
                </p>
                <div class="grid grid-cols-4 gap-3">
                    <Link
                        v-for="p in pintasan"
                        :key="p.key"
                        :href="p.href"
                        class="flex flex-col items-center gap-2 rounded-2xl bg-white p-3 text-center shadow-soft ring-1 ring-slate-100 transition-all duration-200 active:scale-[.97]"
                    >
                        <IconCircle :icon="p.icon" :tone="p.tone" size="sm" />
                        <span class="text-[11px] font-semibold leading-tight text-slate-700">
                            {{ p.label }}
                        </span>
                    </Link>
                </div>
            </section>

            <!-- Agenda terbaru -->
            <SectionCard kicker="Buku Agenda" title="Agenda Terbaru" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="a in agendaTerbaru"
                        :key="a.no"
                        :title="a.perihal"
                        :subtitle="`${a.dari} · ${a.tanggal}`"
                    >
                        <template #leading>
                            <IconCircle
                                :icon="a.arah === 'masuk' ? 'arrow-down-left' : 'arrow-up-right'"
                                :tone="a.arah === 'masuk' ? 'blue' : 'amber'"
                                size="sm"
                            />
                        </template>
                        <template #trailing>
                            <span class="font-jost text-[11px] font-semibold tabular-nums text-slate-400">
                                {{ a.no }}
                            </span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </div>
    </AppShell>
</template>
