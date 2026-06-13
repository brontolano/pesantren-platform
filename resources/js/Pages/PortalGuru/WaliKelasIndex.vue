<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, ListRow, EmptyState, IconCircle } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

defineProps({
    kelas: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Wali Kelas</title>
        </Head>

        <PageHeader kicker="Wali Kelas" title="Kelas Binaan" />

        <EmptyState
            v-if="kelas.length === 0"
            icon="user"
            title="Anda bukan wali kelas"
            message="Halaman ini khusus guru yang ditunjuk sebagai wali kelas."
        />

        <SectionCard v-else flush>
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="k in kelas"
                    :key="k.id"
                    :href="k.rekap_url"
                    :title="k.nama"
                    :subtitle="`Tingkat ${k.tingkat}`"
                    chevron
                >
                    <template #leading>
                        <IconCircle icon="book" tone="emerald" variant="tint" size="md" />
                    </template>
                </ListRow>
            </div>
        </SectionCard>
    </div>
</template>
