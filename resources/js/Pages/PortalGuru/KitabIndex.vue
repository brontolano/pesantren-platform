<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

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

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Kajian Kitab</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Kajian yang Saya Ampu</h2>
            <p class="text-sm text-slate-500">Sorogan dicatat per santri; bandongan satu posisi untuk kelompok.</p>
        </section>

        <section v-if="kajian.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada kajian kitab aktif</p>
            <p class="mt-2 text-sm text-slate-500">Hubungi TU untuk mendaftarkan kajian Anda.</p>
        </section>

        <section v-else class="space-y-2">
            <Link
                v-for="k in kajian"
                :key="k.id"
                :href="k.form_url"
                class="flex items-center justify-between gap-3 rounded-2xl bg-white px-4 py-4 shadow-sm"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ k.kitab }}</p>
                    <p class="text-xs text-slate-400">{{ k.kelompok || '—' }}</p>
                </div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-700">
                    {{ k.metode }}
                </span>
            </Link>
        </section>
    </div>
</template>
