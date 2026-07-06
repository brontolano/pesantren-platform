<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import { UiCard, IconCircle, EmptyState } from '@/Components/Ui'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
    santri: { type: Object, required: true },
    izin: { type: Array, default: () => [] },
    kunjungan: { type: Array, default: () => [] },
})

const statusColor = (status) => ({
    'disetujui': 'bg-emerald-100 text-emerald-700',
    'ditolak':   'bg-rose-100 text-rose-700',
    'pending':   'bg-amber-100 text-amber-700',
}[status] ?? 'bg-slate-100 text-slate-600')

const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) : '-'
</script>

<template>
    <div class="space-y-5">
        <Head><title>Kegiatan & Perizinan — {{ santri.nama }}</title></Head>

        <!-- HERO -->
        <UiCard variant="hero-dark" padding="lg">
            <div class="flex items-start gap-3">
                <Link href="/wali" class="mt-0.5 text-white/70 transition hover:text-white" aria-label="Kembali">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">Kegiatan &amp; Perizinan</p>
                    <h2 class="mt-1 text-2xl font-extrabold leading-tight tracking-tight">{{ santri.nama }}</h2>
                </div>
            </div>
        </UiCard>

        <!-- Riwayat Izin -->
        <section class="space-y-3">
            <div class="flex items-center gap-2.5">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-grad" />
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Riwayat Izin</p>
            </div>

            <div v-if="izin.length" class="space-y-3">
                <UiCard v-for="item in izin" :key="item.id" padding="md">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold capitalize text-slate-900">
                                Izin {{ item.jenis_izin.replace('_', ' ') }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ formatDate(item.tanggal_mulai) }} – {{ formatDate(item.tanggal_selesai) }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold capitalize" :class="statusColor(item.status)">
                            {{ item.status }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">{{ item.alasan }}</p>
                    <div v-if="item.sudah_kembali" class="mt-2 text-xs font-semibold text-emerald-600">
                        ✓ Sudah kembali ke pesantren
                    </div>
                </UiCard>
            </div>
            <EmptyState v-else icon="calendar" title="Belum ada riwayat izin" message="Perizinan santri akan tampil di sini." />
        </section>

        <!-- Kunjungan Wali -->
        <section class="space-y-3">
            <div class="flex items-center gap-2.5">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-grad" />
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Kunjungan Wali</p>
            </div>

            <div v-if="kunjungan.length" class="space-y-3">
                <UiCard v-for="item in kunjungan" :key="item.id" padding="md">
                    <div class="flex items-start gap-3">
                        <IconCircle icon="user" tone="emerald" variant="tint" size="sm" />
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900">{{ item.nama_pengunjung }}</p>
                            <p class="mt-0.5 text-sm capitalize text-slate-500">
                                {{ item.hubungan }} · {{ formatDate(item.tanggal) }}
                            </p>
                            <p v-if="item.keperluan" class="mt-1 text-sm text-slate-600">{{ item.keperluan }}</p>
                        </div>
                    </div>
                </UiCard>
            </div>
            <EmptyState v-else icon="user" title="Belum ada kunjungan" message="Data kunjungan wali akan tampil di sini." />
        </section>
    </div>
</template>
