<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import SetoranFormFields from './SetoranFormFields.vue'

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

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Setoran Tahfidz</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Pilih {{ istilah.halaqah || 'Halaqah' }}</h2>
        </section>

        <section v-if="halaqah.length > 0" class="space-y-3">
            <Link
                v-for="item in halaqah"
                :key="item.id"
                :href="`/guru/setoran/${item.id}`"
                class="block rounded-[1.5rem] bg-white px-5 py-4 shadow"
            >
                <p class="text-base font-semibold text-slate-900">{{ item.nama }}</p>
                <p class="text-sm text-slate-500">{{ item.jenis }} · {{ item.jumlah_santri }} {{ istilah.santri || 'santri' }}</p>
            </Link>
        </section>

        <!-- Guru tahfidz tanpa halaqah binaan: setor untuk semua santri aktif. -->
        <section v-else-if="semuaSantri" class="space-y-3">
            <p class="rounded-2xl bg-amber-50 px-4 py-2 text-sm text-amber-800">
                Anda belum memiliki {{ istilah.halaqah || 'halaqah' }} binaan — pilih dari semua {{ istilah.santri || 'santri' }} aktif.
            </p>
            <SetoranFormFields :santri="semuaSantri" :halaqah-id="null" />
        </section>

        <section v-else class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada {{ istilah.halaqah || 'halaqah' }} binaan</p>
            <p class="mt-2 text-sm leading-6 text-slate-500">
                Anda belum tercatat sebagai {{ istilah.musyrif || 'musyrif' }} pada {{ istilah.halaqah || 'halaqah' }} mana pun. Hubungi admin pesantren bila ini keliru.
            </p>
        </section>
    </div>
</template>
