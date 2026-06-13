<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, IconCircle, EmptyState, SegmentedControl } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    hari: { type: Array, default: () => [] },
    jadwal: { type: Object, default: () => ({}) },
})

const hariOptions = computed(() => props.hari.map((h) => ({ value: h.value, label: h.label })))
const hariAktif = ref(props.hari[0]?.value ?? 'senin')
const daftar = computed(() => props.jadwal[hariAktif.value] ?? [])
</script>

<template>
    <Head title="Jadwal Pelajaran" />

    <AppShell :nav-items="navigation" brand-name="Aplikasi Santri">
        <div class="space-y-5">
            <PageHeader kicker="Akademik" title="Jadwal" subtitle="Jadwal pelajaran mingguan" />

            <SegmentedControl v-model="hariAktif" :options="hariOptions" />

            <SectionCard flush>
                <EmptyState
                    v-if="!daftar.length"
                    icon="calendar"
                    title="Tidak ada jadwal"
                    message="Belum ada pelajaran terjadwal pada hari ini."
                />
                <div v-else class="divide-y divide-slate-100">
                    <ListRow
                        v-for="j in daftar"
                        :key="j.id"
                        :title="j.pelajaran"
                        :subtitle="`${j.pengajar} • ${j.ruang}`"
                    >
                        <template #leading>
                            <IconCircle icon="clock" tone="blue" size="md" />
                        </template>
                        <template #trailing>
                            <span class="text-sm font-bold tabular-nums text-slate-600">{{ j.jam }}</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Data peragaan. Geser tab hari untuk melihat jadwal pelajaran lainnya.
            </p>
        </div>
    </AppShell>
</template>
