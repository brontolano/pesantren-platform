<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, HeroHeader, StatCard, SectionCard, ListRow, IconCircle, StatusBadge, EmptyState } from '@/Shared'

const props = defineProps({
    userName: { type: String, default: 'Petugas Sarpras' },
    stats: { type: Object, default: () => ({}) },
    laporanTerbaru: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])
</script>

<template>
    <Head title="Sarpras — Beranda" />

    <AppShell :nav-items="navItems" brand-name="Sarpras">
        <template #header>
            <HeroHeader :name="userName" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <StatusBadge status="aktif" label="Petugas Sarpras" size="sm" />
                </template>
            </HeroHeader>
        </template>

        <div class="space-y-6">
            <section>
                <div class="grid grid-cols-3 gap-3">
                    <StatCard icon="wrench" tone="amber" label="Masuk" :value="stats.masuk ?? 0" compact />
                    <StatCard icon="wrench" tone="emerald" label="Dikerjakan" :value="stats.dikerjakan ?? 0" compact />
                    <StatCard icon="check-circle" tone="emerald" label="Selesai Bln Ini" :value="stats.selesai_bulan_ini ?? 0" compact />
                </div>
            </section>

            <section>
                <p class="mb-3 font-jost text-[11px] font-semibold uppercase tracking-wider text-violet-700">
                    Laporan Terbaru
                </p>
                <SectionCard flush>
                    <div v-if="laporanTerbaru.length" class="divide-y divide-slate-100">
                        <ListRow v-for="l in laporanTerbaru" :key="l.id" :title="l.lokasi" :subtitle="l.dibuat">
                            <template #leading>
                                <IconCircle icon="wrench" tone="amber" size="sm" />
                            </template>
                            <template #trailing>
                                <StatusBadge :status="l.status" size="sm" />
                            </template>
                        </ListRow>
                    </div>
                    <EmptyState v-else icon="wrench" title="Tidak ada laporan" message="Belum ada laporan kerusakan masuk." />
                </SectionCard>
            </section>
        </div>
    </AppShell>
</template>
