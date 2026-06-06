<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
    santri: { type: Object, required: true },
    riwayat: { type: Array, default: () => [] },
    konseling: { type: Array, default: () => [] },
})

const kondisiColor = (kondisi) => ({
    'baik':  'bg-emerald-50 text-emerald-700',
    'sakit': 'bg-red-50 text-red-700',
    'rujuk': 'bg-amber-50 text-amber-700',
}[kondisi] ?? 'bg-slate-50 text-slate-600')

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) : '-'
</script>

<template>
    <div class="space-y-5">
        <Head><title>Kesehatan — {{ santri.nama }}</title></Head>

        <div class="flex items-center gap-3">
            <Link href="/wali" class="text-slate-400 hover:text-slate-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-rose-600">Kesehatan & Konseling</p>
                <h2 class="font-jost text-2xl text-slate-900">{{ santri.nama }}</h2>
            </div>
        </div>

        <!-- Riwayat Kesehatan -->
        <section class="space-y-3">
            <h3 class="font-jost text-xl text-slate-900">Riwayat Kesehatan</h3>

            <div v-if="riwayat.length" class="space-y-3">
                <article
                    v-for="item in riwayat"
                    :key="item.id"
                    class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-4"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-semibold text-slate-900">{{ item.keluhan ?? 'Pemeriksaan rutin' }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ formatDate(item.tanggal) }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="kondisiColor(item.kondisi)">
                            {{ item.kondisi }}
                        </span>
                    </div>
                    <div v-if="item.diagnosa || item.tindakan" class="mt-3 rounded-[1rem] bg-slate-50 px-3 py-3 text-sm text-slate-600 space-y-1">
                        <p v-if="item.diagnosa"><span class="font-semibold">Diagnosa:</span> {{ item.diagnosa }}</p>
                        <p v-if="item.tindakan"><span class="font-semibold">Tindakan:</span> {{ item.tindakan }}</p>
                        <p v-if="item.petugas" class="text-xs text-slate-400">Petugas: {{ item.petugas }}</p>
                    </div>
                </article>
            </div>
            <div v-else class="rounded-[1.75rem] bg-slate-50 px-5 py-8 text-center text-sm text-slate-400">
                Tidak ada riwayat kesehatan tercatat.
            </div>
        </section>

        <!-- Konseling -->
        <section class="space-y-3">
            <h3 class="font-jost text-xl text-slate-900">Sesi Konseling</h3>

            <div v-if="konseling.length" class="space-y-3">
                <article
                    v-for="sesi in konseling"
                    :key="sesi.id"
                    class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-4"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-semibold text-slate-900">{{ sesi.topik ?? 'Konseling Umum' }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ formatDate(sesi.tanggal) }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="sesi.status === 'selesai' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                            {{ sesi.status }}
                        </span>
                    </div>
                    <p v-if="sesi.rekomendasi" class="mt-3 rounded-[1rem] bg-slate-50 px-3 py-3 text-sm text-slate-600">
                        <span class="font-semibold">Rekomendasi:</span> {{ sesi.rekomendasi }}
                    </p>
                </article>
            </div>
            <div v-else class="rounded-[1.75rem] bg-slate-50 px-5 py-8 text-center text-sm text-slate-400">
                Tidak ada sesi konseling tercatat.
            </div>
        </section>
    </div>
</template>
