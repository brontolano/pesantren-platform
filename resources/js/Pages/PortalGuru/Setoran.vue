<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import SetoranFormFields from './SetoranFormFields.vue'
import { PageHeader, SectionCard, UiCard, ListRow, IconCircle, EmptyState } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

defineProps({
    halaqah: { type: Array, default: () => [] },
    semuaSantri: { type: Array, default: null },
    istilah: { type: Object, default: () => ({}) },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Setoran Tahfidz</title>
        </Head>

        <PageHeader kicker="Setoran Tahfidz" :title="`Pilih ${istilah.halaqah || 'Halaqah'}`" />

        <SectionCard v-if="halaqah.length > 0" flush>
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="item in halaqah"
                    :key="item.id"
                    :href="`/guru/setoran/${item.id}`"
                    :title="item.nama"
                    :subtitle="`${item.jenis} · ${item.jumlah_santri} ${istilah.santri || 'santri'}`"
                    chevron
                >
                    <template #leading>
                        <IconCircle icon="book" tone="amber" />
                    </template>
                </ListRow>
            </div>
        </SectionCard>

        <!-- Guru tahfidz tanpa halaqah binaan: setor untuk semua santri aktif. -->
        <section v-else-if="semuaSantri" class="space-y-3">
            <UiCard padding="md">
                <div class="flex items-start gap-3">
                    <IconCircle icon="info" tone="amber" size="sm" />
                    <p class="text-sm leading-6 text-amber-800">
                        Anda belum memiliki {{ istilah.halaqah || 'halaqah' }} binaan — pilih dari semua {{ istilah.santri || 'santri' }} aktif.
                    </p>
                </div>
            </UiCard>
            <SetoranFormFields :santri="semuaSantri" :halaqah-id="null" />
        </section>

        <EmptyState
            v-else
            icon="book"
            :title="`Belum ada ${istilah.halaqah || 'halaqah'} binaan`"
            :message="`Anda belum tercatat sebagai ${istilah.musyrif || 'musyrif'} pada ${istilah.halaqah || 'halaqah'} mana pun. Hubungi admin pesantren bila ini keliru.`"
        />
    </div>
</template>
