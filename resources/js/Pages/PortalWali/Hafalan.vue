<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
    santri: { type: Object, required: true },
    hafalan: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
})

const statusColor = (status) => ({
    'selesai':      'bg-emerald-50 text-emerald-700',
    'berjalan':     'bg-amber-50 text-amber-700',
    'ditunda':      'bg-red-50 text-red-700',
    'direncanakan': 'bg-slate-50 text-slate-600',
}[status] ?? 'bg-slate-50 text-slate-600')
</script>

<template>
    <div class="space-y-5">
        <Head><title>Hafalan — {{ santri.nama }}</title></Head>

        <!-- Header -->
        <div class="flex items-center gap-3">
            <Link href="/wali" class="text-slate-400 hover:text-slate-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">Tahfidz</p>
                <h2 class="font-jost text-2xl text-slate-900">Hafalan {{ santri.nama }}</h2>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 gap-3 rounded-[1.75rem] bg-slate-950 p-5 text-white">
            <div class="rounded-[1.25rem] bg-white/8 px-4 py-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/55">Total Hafalan</p>
                <p class="mt-2 text-2xl font-semibold">{{ ringkasan.total ?? 0 }}</p>
            </div>
            <div class="rounded-[1.25rem] bg-white/8 px-4 py-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/55">Selesai</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-400">{{ ringkasan.selesai ?? 0 }}</p>
            </div>
        </div>

        <!-- Daftar Hafalan -->
        <section class="space-y-3">
            <h3 class="font-jost text-xl text-slate-900">Riwayat Hafalan</h3>

            <div v-if="hafalan.length" class="space-y-3">
                <article
                    v-for="item in hafalan"
                    :key="item.id"
                    class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-4 shadow-[0_8px_24px_-16px_rgba(15,23,42,0.3)]"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-semibold text-slate-900">{{ item.surah }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                Ayat {{ item.ayat_awal ?? '?' }} – {{ item.ayat_akhir ?? '?' }}
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusColor(item.status)">
                            {{ item.status }}
                        </span>
                    </div>
                    <div v-if="item.tanggal_target" class="mt-3 text-sm text-slate-500">
                        Target: {{ new Date(item.tanggal_target).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) }}
                    </div>
                    <div v-if="item.penilaian?.length" class="mt-3 rounded-[1rem] bg-slate-50 px-3 py-3 text-sm text-slate-600">
                        <p class="font-semibold text-slate-700">Penilaian Terakhir</p>
                        <p>{{ item.penilaian[0].jenis }} — Nilai: <span class="font-semibold">{{ item.penilaian[0].nilai ?? '-' }}</span></p>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-[1.75rem] bg-slate-50 px-5 py-8 text-center text-sm text-slate-400">
                Belum ada data hafalan tercatat.
            </div>
        </section>
    </div>
</template>
