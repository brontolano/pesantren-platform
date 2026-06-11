<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

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

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Grid Nilai</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Pilih Kelas × Pelajaran</h2>
            <p class="text-sm text-slate-500">Hanya pelajaran & kelas yang Anda ampu.</p>
        </section>

        <section v-if="kombinasi.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada jadwal mengajar</p>
            <p class="mt-2 text-sm text-slate-500">Hubungi TU untuk mengatur jadwal pelajaran Anda.</p>
        </section>

        <section v-else class="space-y-2">
            <Link
                v-for="item in kombinasi"
                :key="`${item.kelas_id}-${item.pelajaran_id}`"
                :href="item.grid_url"
                class="flex items-center justify-between gap-3 rounded-2xl bg-white px-4 py-4 shadow-sm"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ item.pelajaran }}</p>
                    <p class="text-xs text-slate-400">{{ item.kelas }}</p>
                </div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Isi Nilai →</span>
            </Link>
        </section>
    </div>
</template>
