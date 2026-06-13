<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, Chip, IconCircle, EmptyState, SegmentedControl } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    pengumuman: { type: Array, default: () => [] },
    ujian: { type: Array, default: () => [] },
})

const tabOptions = [
    { value: 'pengumuman', label: 'Pengumuman', icon: 'bell' },
    { value: 'ujian', label: 'Ujian', icon: 'chart' },
]
const tab = ref('pengumuman')

// Peta status ujian -> kunci StatusBadge yang dikenal + label.
const ujianMeta = {
    berlangsung: { status: 'info', label: 'Berlangsung' },
    selesai: { status: 'lunas', label: 'Selesai' },
}

const adaPengumuman = computed(() => props.pengumuman.length > 0)
const adaUjian = computed(() => props.ujian.length > 0)
</script>

<template>
    <Head title="Pengumuman & Ujian" />

    <AppShell :nav-items="navigation" brand-name="Aplikasi Santri">
        <div class="space-y-5">
            <PageHeader kicker="Info" title="Pengumuman" subtitle="Kabar pondok & jadwal ujian" />

            <SegmentedControl v-model="tab" :options="tabOptions" />

            <template v-if="tab === 'pengumuman'">
                <SectionCard flush>
                    <EmptyState
                        v-if="!adaPengumuman"
                        icon="bell"
                        title="Belum ada pengumuman"
                        message="Kabar terbaru dari pondok akan tampil di sini."
                    />
                    <div v-else class="divide-y divide-slate-100">
                        <ListRow
                            v-for="p in pengumuman"
                            :key="p.id"
                            :title="p.judul"
                            :subtitle="p.isi"
                        >
                            <template #leading>
                                <IconCircle icon="bell" tone="rose" size="md" />
                            </template>
                            <template #trailing>
                                <div class="flex flex-col items-end gap-1.5">
                                    <Chip :active="false" tone="soft" size="sm">{{ p.kategori }}</Chip>
                                    <span class="text-xs font-medium text-slate-400">{{ p.tanggal }}</span>
                                </div>
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>
            </template>

            <template v-else>
                <SectionCard flush>
                    <EmptyState
                        v-if="!adaUjian"
                        icon="chart"
                        title="Belum ada ujian"
                        message="Sesi ujian yang dijadwalkan akan tampil di sini."
                    />
                    <div v-else class="divide-y divide-slate-100">
                        <ListRow
                            v-for="u in ujian"
                            :key="u.id"
                            :title="u.mapel"
                            :subtitle="`${u.jenis} • ${u.durasi}`"
                        >
                            <template #leading>
                                <IconCircle icon="chart" tone="blue" size="md" />
                            </template>
                            <template #trailing>
                                <div class="flex flex-col items-end gap-1.5">
                                    <StatusBadge
                                        :status="(ujianMeta[u.status] || {}).status || 'info'"
                                        :label="(ujianMeta[u.status] || {}).label || u.status"
                                        size="sm"
                                    />
                                    <span class="text-xs font-medium text-slate-400">{{ u.waktu }}</span>
                                </div>
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>
            </template>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Data peragaan. Ujian "Berlangsung" dikerjakan lewat modul Ujian Online.
            </p>
        </div>
    </AppShell>
</template>
