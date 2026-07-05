<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, Chip, IconCircle, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    agenda: { type: Array, default: () => [] },
})

const navItems = props.navigation

// Kelompokkan agenda mendatang per tanggal (urutan dipertahankan dari controller).
const grup = computed(() => {
    const map = new Map()
    for (const a of props.agenda) {
        if (!map.has(a.tanggal)) map.set(a.tanggal, [])
        map.get(a.tanggal).push(a)
    }
    return Array.from(map, ([tanggal, items]) => ({ tanggal, items }))
})

const JENIS_TONE = {
    Rapat: 'blue',
    Liputan: 'emerald',
    Kegiatan: 'amber',
    Publikasi: 'teal',
}
</script>

<template>
    <Head title="Agenda Humas" />

    <AppShell :nav-items="navItems" brand-name="Humas Pesantren">
        <div class="space-y-5">
            <PageHeader
                kicker="Jadwal"
                title="Agenda Mendatang"
                subtitle="Rencana liputan & kegiatan humas"
            />

            <template v-if="grup.length">
                <SectionCard v-for="g in grup" :key="g.tanggal" flush>
                    <template #header>
                        <div class="flex items-center gap-2 px-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-[var(--wali-primary,#7c3aed)]" />
                            <p class="font-jost text-[13px] font-bold uppercase tracking-wider text-violet-700">
                                {{ g.tanggal }}
                            </p>
                        </div>
                    </template>

                    <div class="divide-y divide-slate-100">
                        <ListRow v-for="a in g.items" :key="a.id">
                            <template #leading>
                                <IconCircle icon="calendar" :tone="JENIS_TONE[a.jenis] || 'slate'" size="md" />
                            </template>
                            <template #title>{{ a.judul }}</template>
                            <template #subtitle>{{ a.waktu }} &middot; {{ a.lokasi }}</template>
                            <template #trailing>
                                <Chip :active="true" tone="soft" size="sm">{{ a.jenis }}</Chip>
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>
            </template>

            <EmptyState
                v-else
                icon="calendar"
                title="Tidak ada agenda"
                message="Belum ada agenda mendatang yang terjadwal."
            />
        </div>
    </AppShell>
</template>
