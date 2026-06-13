<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, Chip, IconCircle, SegmentedControl, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    pegawai: { type: Array, default: () => [] },
})

const statusFilter = ref('aktif')
const statusOptions = [
    { value: 'aktif', label: 'Aktif' },
    { value: 'nonaktif', label: 'Nonaktif' },
]

const unitFilter = ref('semua')
const units = computed(() => {
    const set = new Set(props.pegawai.map((p) => p.unit))
    return ['semua', ...Array.from(set)]
})

const filtered = computed(() =>
    props.pegawai.filter(
        (p) =>
            p.status === statusFilter.value &&
            (unitFilter.value === 'semua' || p.unit === unitFilter.value),
    ),
)
</script>

<template>
    <Head title="Data Pegawai" />

    <AppShell :nav-items="navigation" brand-name="HRD Kepegawaian">
        <div class="space-y-5">
            <PageHeader kicker="Kepegawaian" title="Data Pegawai" subtitle="Profil, jabatan & status kontrak" />

            <SegmentedControl v-model="statusFilter" :options="statusOptions" />

            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <Chip
                    v-for="u in units"
                    :key="u"
                    :active="unitFilter === u"
                    tone="soft"
                    size="sm"
                    @click="unitFilter = u"
                >
                    {{ u === 'semua' ? 'Semua Unit' : u }}
                </Chip>
            </div>

            <SectionCard v-if="filtered.length" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="p in filtered"
                        :key="p.id"
                        :href="`/kepegawaian/pegawai/${p.id}`"
                        :title="p.nama"
                        :subtitle="`${p.jabatan} · NIP ${p.nip}`"
                        chevron
                    >
                        <template #leading><IconCircle icon="user" tone="emerald" /></template>
                        <template #trailing>
                            <StatusBadge
                                :status="p.status === 'aktif' ? 'aktif' : 'info'"
                                :label="p.status === 'aktif' ? 'Aktif' : 'Nonaktif'"
                                size="sm"
                            />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <EmptyState
                v-else
                icon="user"
                title="Tidak ada pegawai"
                message="Tidak ada pegawai pada filter status & unit ini."
            />
        </div>
    </AppShell>
</template>
