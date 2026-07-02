<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    nama: String,
    penugasan: Array, // [{qism, label, periode_selesai}]
});
</script>

<template>
    <div class="min-h-screen bg-[#fffaf3] pb-10">
        <header class="bg-gradient-to-b from-amber-700 to-amber-500 px-5 pb-12 pt-8 text-white">
            <div class="mx-auto max-w-md">
                <p class="text-sm text-amber-100">Mode Pengurus</p>
                <h1 class="text-xl font-bold">{{ nama }}</h1>
                <p class="mt-1 text-xs text-amber-100">
                    <span v-for="(p, i) in penugasan" :key="p.qism">
                        {{ p.label }}<span v-if="i < penugasan.length - 1"> · </span>
                    </span>
                </p>
            </div>
        </header>

        <main class="mx-auto -mt-7 max-w-md space-y-4 px-5">
            <Link href="/app/pengurus/pelanggaran"
                class="block rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm">
                <p class="font-semibold text-stone-800">Catat Pelanggaran</p>
                <p class="mt-1 text-xs text-stone-500">Laporan masuk berstatus "dicatat" — menunggu verifikasi ustadz.</p>
            </Link>

            <Link href="/app/pengurus/kegiatan"
                class="block rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm">
                <p class="font-semibold text-stone-800">Presensi Kegiatan</p>
                <p class="mt-1 text-xs text-stone-500">Hanya kegiatan domain qism Anda.</p>
            </Link>

            <Link href="/app" class="block text-center text-xs font-semibold text-stone-400">
                ← Kembali ke beranda santri
            </Link>
        </main>
    </div>
</template>
