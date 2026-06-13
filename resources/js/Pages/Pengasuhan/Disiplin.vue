<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, StatCard, SectionCard, ListRow, IconCircle, Chip, UiButton, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: '' },
    bulan: { type: String, default: '' },
    totalPoin: { type: Number, default: 0 },
    pelanggaran: { type: Array, default: () => [] },
})

const KATEGORI = ['Semua', 'Kedisiplinan', 'Ketertiban', 'Kebersihan', 'Akhlak']
const filter = ref('Semua')

const terfilter = computed(() =>
    filter.value === 'Semua'
        ? props.pelanggaran
        : props.pelanggaran.filter((p) => p.kategori === filter.value),
)

function toneFor(kategori) {
    return {
        Kedisiplinan: 'amber',
        Ketertiban: 'blue',
        Kebersihan: 'teal',
        Akhlak: 'rose',
    }[kategori] ?? 'slate'
}
</script>

<template>
    <Head title="Disiplin" />

    <AppShell :nav-items="navigation" brand-name="Kepengasuhan">
        <div class="space-y-5">
            <PageHeader kicker="Kedisiplinan" title="Rekap Pelanggaran" :subtitle="bulan" />

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="chart" tone="rose" label="Total Poin" :value="totalPoin" hint="Bulan ini" />
                <StatCard icon="ban" tone="amber" label="Kasus" :value="pelanggaran.length" hint="Tercatat" />
            </div>

            <!-- Filter kategori -->
            <div class="flex flex-wrap gap-2">
                <Chip
                    v-for="k in KATEGORI"
                    :key="k"
                    :active="filter === k"
                    :tone="filter === k ? 'brand' : 'soft'"
                    size="sm"
                    @click="filter = k"
                >
                    {{ k }}
                </Chip>
            </div>

            <SectionCard kicker="Daftar" title="Pelanggaran Santri" flush>
                <EmptyState
                    v-if="terfilter.length === 0"
                    icon="check-circle" tone="emerald"
                    title="Tidak ada pelanggaran"
                    message="Tidak ada catatan pelanggaran pada kategori ini bulan ini. Alhamdulillah."
                />
                <div v-else class="divide-y divide-slate-100">
                    <ListRow v-for="p in terfilter" :key="p.id">
                        <template #leading><IconCircle icon="ban" :tone="toneFor(p.kategori)" /></template>
                        <template #title>{{ p.santri }}</template>
                        <template #subtitle>{{ p.jenis }}</template>
                        <template #trailing>
                            <span class="font-jost text-base font-extrabold tabular-nums text-rose-600">+{{ p.poin }}</span>
                            <p class="mt-0.5 text-[11px] text-slate-400">poin</p>
                        </template>
                        <div class="flex items-center gap-2">
                            <Chip :active="false" tone="soft" size="sm">{{ p.kategori }}</Chip>
                            <span class="text-[11px] text-slate-400">{{ p.kelas }} · {{ p.tanggal }}</span>
                        </div>
                    </ListRow>
                </div>
            </SectionCard>

            <UiButton href="/pengasuhan/catat-pelanggaran" variant="primary" block icon-left="plus">
                Catat Pelanggaran Baru
            </UiButton>
        </div>
    </AppShell>
</template>
