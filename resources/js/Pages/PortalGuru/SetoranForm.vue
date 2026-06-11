<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import SetoranFormFields from './SetoranFormFields.vue'

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

        <section>
            <Link href="/guru/setoran" class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                ← Semua {{ istilah.halaqah || 'halaqah' }}
            </Link>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ halaqah.nama }}</h2>
            <p class="text-sm text-slate-500">Setoran hafalan · {{ halaqah.jenis }}</p>
        </section>

        <section v-if="santri.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada anggota aktif</p>
            <p class="mt-2 text-sm text-slate-500">Tambahkan {{ istilah.santri || 'santri' }} ke {{ istilah.halaqah || 'halaqah' }} ini dari panel admin.</p>
        </section>

        <SetoranFormFields v-else :santri="santri" :halaqah-id="halaqah.id" />
    </div>
</template>
