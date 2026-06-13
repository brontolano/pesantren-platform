<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, ListRow, EmptyState, IconCircle } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

defineProps({
    kombinasi: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Input Nilai</title>
        </Head>

        <PageHeader
            kicker="Grid Nilai"
            title="Pilih Kelas × Pelajaran"
            subtitle="Hanya pelajaran & kelas yang Anda ampu."
        />

        <EmptyState
            v-if="kombinasi.length === 0"
            icon="book"
            title="Belum ada jadwal mengajar"
            message="Hubungi TU untuk mengatur jadwal pelajaran Anda."
        />

        <SectionCard v-else flush>
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="item in kombinasi"
                    :key="`${item.kelas_id}-${item.pelajaran_id}`"
                    :href="item.grid_url"
                    :title="item.pelajaran"
                    :subtitle="item.kelas"
                    chevron
                >
                    <template #leading>
                        <IconCircle icon="book" tone="emerald" variant="tint" size="sm" />
                    </template>
                    <template #trailing>
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Isi Nilai</span>
                    </template>
                </ListRow>
            </div>
        </SectionCard>
    </div>
</template>
