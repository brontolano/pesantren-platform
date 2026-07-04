<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, SegmentedControl, IconCircle, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    artikel: { type: Array, default: () => [] },
})

const filter = ref('semua')
const segmentOptions = [
    { value: 'semua', label: 'Semua' },
    { value: 'published', label: 'Terbit' },
    { value: 'draft', label: 'Draf' },
]

const tersaring = computed(() =>
    filter.value === 'semua' ? props.artikel : props.artikel.filter((a) => a.status === filter.value),
)
</script>

<template>
    <Head title="Berita & Pengumuman" />

    <AppShell :nav-items="navigation" brand-name="Humas Pesantren">
        <div class="space-y-5">
            <PageHeader
                kicker="Konten"
                title="Berita & Publikasi"
                subtitle="Daftar artikel — kelola isi & publikasi via panel Admin"
            />

            <SegmentedControl v-model="filter" :options="segmentOptions" />

            <SectionCard v-if="tersaring.length" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="a in tersaring" :key="a.id" :href="`/humas/berita/${a.id}`" chevron>
                        <template #leading>
                            <IconCircle icon="document" tone="emerald" size="md" />
                        </template>
                        <template #title>{{ a.judul }}</template>
                        <template #subtitle>{{ a.tanggal }}</template>
                        <template #trailing>
                            <StatusBadge
                                :status="a.status === 'published' ? 'aktif' : 'pending'"
                                :label="a.status === 'published' ? 'Terbit' : 'Draf'"
                                size="sm"
                            />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <EmptyState
                v-else
                icon="document"
                title="Belum ada konten"
                message="Belum ada artikel pada kategori ini."
            />
        </div>
    </AppShell>
</template>
