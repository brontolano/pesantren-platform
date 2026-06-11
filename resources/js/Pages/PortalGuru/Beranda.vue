<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({
    layout: PortalGuruLayout,
})

defineProps({
    hari: { type: String, required: true },
    tanggal: { type: String, required: true },
    sesi: { type: Array, default: () => [] },
    halaqah: { type: Array, default: () => [] },
    istilah: { type: Object, default: () => ({}) },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Hari Ini</title>
        </Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">
                Hari Ini · {{ hari }}
            </p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Jadwal Mengajar</h2>
            <p class="text-sm text-slate-500">{{ tanggal }}</p>
        </section>

        <section v-if="sesi.length === 0 && halaqah.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Tidak ada jadwal hari ini</p>
            <p class="mt-2 text-sm leading-6 text-slate-500">
                Alhamdulillah, belum ada sesi mengajar atau {{ istilah.halaqah || 'halaqah' }} yang terjadwal untuk Anda hari ini. Selamat beristirahat atau silakan cek kembali besok.
            </p>
        </section>

        <section v-if="sesi.length > 0" class="space-y-3">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Sesi Kelas</h3>
            <article
                v-for="item in sesi"
                :key="item.id"
                class="rounded-[1.5rem] bg-white px-5 py-4 shadow"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-slate-900">{{ item.pelajaran }}</p>
                        <p class="text-sm text-slate-500">
                            {{ item.kelas }} · {{ item.jam_mulai }}–{{ item.jam_selesai }}
                            <span v-if="item.ruangan"> · {{ item.ruangan }}</span>
                        </p>
                    </div>
                </div>
                <Link
                    :href="item.presensi_url"
                    class="mt-3 inline-block rounded-full bg-emerald-700 px-5 py-2 text-xs font-semibold uppercase tracking-wider text-white"
                >
                    Presensi Kelas
                </Link>
            </article>
        </section>

        <section v-if="halaqah.length > 0" class="space-y-3">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500">
                {{ istilah.halaqah || 'Halaqah' }} Binaan
            </h3>
            <article
                v-for="item in halaqah"
                :key="item.id"
                class="rounded-[1.5rem] bg-white px-5 py-4 shadow"
            >
                <p class="text-base font-semibold text-slate-900">{{ item.nama }}</p>
                <p class="text-sm text-slate-500">
                    {{ item.jenis }} · {{ item.jumlah_santri }} {{ istilah.santri || 'santri' }}
                </p>
                <Link
                    :href="item.setoran_url"
                    class="mt-3 inline-block rounded-full bg-amber-600 px-5 py-2 text-xs font-semibold uppercase tracking-wider text-white"
                >
                    Setoran Hafalan
                </Link>
            </article>
        </section>

        <!-- F2-PWA — pintasan modul guru -->
        <section class="space-y-3">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Menu Guru</h3>
            <div class="grid grid-cols-3 gap-2">
                <Link href="/guru/nilai" class="rounded-2xl bg-white px-3 py-4 text-center shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">Nilai</p>
                    <p class="mt-1 text-xs text-slate-400">Grid massal</p>
                </Link>
                <Link href="/guru/kitab" class="rounded-2xl bg-white px-3 py-4 text-center shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">Kitab</p>
                    <p class="mt-1 text-xs text-slate-400">Capaian kajian</p>
                </Link>
                <Link href="/guru/wali-kelas" class="rounded-2xl bg-white px-3 py-4 text-center shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">Wali Kelas</p>
                    <p class="mt-1 text-xs text-slate-400">Rekap binaan</p>
                </Link>
            </div>
        </section>
    </div>
</template>
