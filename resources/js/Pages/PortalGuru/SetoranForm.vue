<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import SetoranFormFields from './SetoranFormFields.vue'
import { PageHeader, EmptyState, UiButton } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

defineProps({
    halaqah: { type: Object, required: true },
    santri: { type: Array, default: () => [] },
    istilah: { type: Object, default: () => ({}) },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Setoran {{ halaqah.nama }}</title>
        </Head>

        <div class="space-y-3">
            <UiButton href="/guru/setoran" variant="ghost" size="sm" iconLeft="arrow-left">
                Semua {{ istilah.halaqah || 'halaqah' }}
            </UiButton>
            <PageHeader :title="halaqah.nama" :subtitle="`Setoran hafalan · ${halaqah.jenis}`" />
        </div>

        <EmptyState
            v-if="santri.length === 0"
            icon="user"
            title="Belum ada anggota aktif"
            :message="`Tambahkan ${istilah.santri || 'santri'} ke ${istilah.halaqah || 'halaqah'} ini dari panel admin.`"
        />

        <SetoranFormFields v-else :santri="santri" :halaqah-id="halaqah.id" />
    </div>
</template>
