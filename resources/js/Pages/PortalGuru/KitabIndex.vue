<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, ListRow, EmptyState } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

defineProps({
    kajian: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Capaian Kitab</title>
        </Head>

        <PageHeader
            kicker="Kajian Kitab"
            title="Kajian yang Saya Ampu"
            subtitle="Sorogan dicatat per santri; bandongan satu posisi untuk kelompok."
        />

        <EmptyState
            v-if="kajian.length === 0"
            icon="book"
            title="Belum ada kajian kitab aktif"
            message="Hubungi TU untuk mendaftarkan kajian Anda."
        />

        <SectionCard v-else flush>
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="k in kajian"
                    :key="k.id"
                    :href="k.form_url"
                    :title="k.kitab"
                    :subtitle="k.kelompok || '—'"
                    chevron
                >
                    <template #trailing>
                        <span class="inline-flex shrink-0 items-center rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-violet-700 ring-1 ring-inset ring-violet-100">
                            {{ k.metode }}
                        </span>
                    </template>
                </ListRow>
            </div>
        </SectionCard>
    </div>
</template>
