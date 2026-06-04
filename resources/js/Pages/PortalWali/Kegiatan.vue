<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
    santri: { type: Object, required: true },
    izin: { type: Array, default: () => [] },
    kunjungan: { type: Array, default: () => [] },
})

const statusColor = (status) => ({
    'disetujui': 'bg-emerald-50 text-emerald-700',
    'ditolak':   'bg-red-50 text-red-700',
    'pending':   'bg-amber-50 text-amber-700',
}[status] ?? 'bg-slate-50 text-slate-600')

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) : '-'
</script>

<template>
    <div class="space-y-5">
        <Head><title>Kegiatan & Perizinan — {{ santri.nama }}</title></Head>

        <div class="flex items-center gap-3">
            <Link href="/wali" class="text-slate-400 hover:text-slate-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-600">Kegiatan & Perizinan</p>
                <h2 class="font-serif text-2xl text-slate-900">{{ santri.nama }}</h2>
            </div>
        </div>

        <!-- Riwayat Izin -->
        <section class="space-y-3">
            <h3 class="font-serif text-xl text-slate-900">Riwayat Izin</h3>

            <div v-if="izin.length" class="space-y-3">
                <article
                    v-for="item in izin"
                    :key="item.id"
                    class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-4"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-semibold capitalize text-slate-900">
                                Izin {{ item.jenis_izin.replace('_', ' ') }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ formatDate(item.tanggal_mulai) }} – {{ formatDate(item.tanggal_selesai) }}
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusColor(item.status)">
                            {{ item.status }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">{{ item.alasan }}</p>
                    <div v-if="item.sudah_kembali" class="mt-2 text-xs text-emerald-600 font-semibold">
                        ✓ Sudah kembali ke pesantren
                    </div>
                </article>
            </div>
            <div v-else class="rounded-[1.75rem] bg-slate-50 px-5 py-8 text-center text-sm text-slate-400">
                Belum ada riwayat izin.
            </div>
        </section>

        <!-- Kunjungan Wali -->
        <section class="space-y-3">
            <h3 class="font-serif text-xl text-slate-900">Kunjungan Wali</h3>

            <div v-if="kunjungan.length" class="space-y-3">
                <article
                    v-for="item in kunjungan"
                    :key="item.id"
                    class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-4"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-semibold text-slate-900">{{ item.nama_pengunjung }}</p>
                            <p class="mt-1 text-sm text-slate-500 capitalize">
                                {{ item.hubungan }} · {{ formatDate(item.tanggal) }}
                            </p>
                        </div>
                    </div>
                    <p v-if="item.keperluan" class="mt-2 text-sm text-slate-600">{{ item.keperluan }}</p>
                </article>
            </div>
            <div v-else class="rounded-[1.75rem] bg-slate-50 px-5 py-8 text-center text-sm text-slate-400">
                Belum ada data kunjungan.
            </div>
        </section>
    </div>
</template>
