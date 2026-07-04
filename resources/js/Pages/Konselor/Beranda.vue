<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, HeroHeader, StatCard, SectionCard, ListRow, IconCircle, StatusBadge, EmptyState } from '@/Shared'

const props = defineProps({
    userName: { type: String, default: 'Konselor' },
    stats: { type: Object, default: () => ({}) },
    sesiTerdekat: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])
</script>

<template>
    <Head title="Konselor — Beranda" />

    <AppShell :nav-items="navItems" brand-name="Konselor">
        <template #header>
            <HeroHeader :name="userName" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <StatusBadge status="aktif" label="Konselor" size="sm" />
                </template>
            </HeroHeader>
        </template>

        <div class="space-y-6">
            <section>
                <div class="grid grid-cols-2 gap-3">
                    <StatCard icon="chat" tone="emerald" label="Terjadwal" :value="stats.terjadwal ?? 0" compact />
                    <StatCard icon="check-circle" tone="emerald" label="Selesai Bulan Ini" :value="stats.selesai_bulan_ini ?? 0" compact />
                </div>
            </section>

            <section>
                <p class="mb-3 font-jost text-[11px] font-semibold uppercase tracking-wider text-emerald-700">
                    Sesi Terdekat
                </p>
                <SectionCard flush>
                    <div v-if="sesiTerdekat.length" class="divide-y divide-slate-100">
                        <ListRow
                            v-for="s in sesiTerdekat"
                            :key="s.id"
                            :title="s.santri ?? '—'"
                            :subtitle="s.topik"
                        >
                            <template #leading>
                                <IconCircle icon="chat" tone="emerald" size="sm" />
                            </template>
                            <template #trailing>
                                <span class="text-xs font-medium text-slate-500">{{ s.tanggal }}</span>
                            </template>
                        </ListRow>
                    </div>
                    <EmptyState v-else icon="chat" title="Tidak ada sesi terjadwal" message="Belum ada sesi konseling yang dijadwalkan." />
                </SectionCard>
            </section>
        </div>
    </AppShell>
</template>
