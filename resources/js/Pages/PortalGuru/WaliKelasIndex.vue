<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

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

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Wali Kelas</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Kelas Binaan</h2>
        </section>

        <section v-if="kelas.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Anda bukan wali kelas</p>
            <p class="mt-2 text-sm text-slate-500">Halaman ini khusus guru yang ditunjuk sebagai wali kelas.</p>
        </section>

        <section v-else class="space-y-2">
            <Link
                v-for="k in kelas"
                :key="k.id"
                :href="k.rekap_url"
                class="flex items-center justify-between gap-3 rounded-2xl bg-white px-4 py-4 shadow-sm"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ k.nama }}</p>
                    <p class="text-xs text-slate-400">Tingkat {{ k.tingkat }}</p>
                </div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Rekap →</span>
            </Link>
        </section>
    </div>
</template>
