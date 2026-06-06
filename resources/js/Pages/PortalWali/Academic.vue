<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

defineOptions({
    layout: PortalWaliLayout,
})

defineProps({
    child: {
        type: Object,
        required: true,
    },
    latestRapor: {
        type: Object,
        default: null,
    },
    grades: {
        type: Array,
        default: () => [],
    },
    schedule: {
        type: Array,
        default: () => [],
    },
    attendance: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Akademik Santri</title>
        </Head>

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Akademik
            </p>
            <h2 class="mt-3 font-jost text-3xl leading-tight">
                {{ child.nama }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-white/75">
                {{ child.kelas || 'Belum ada kelas' }}
            </p>
        </section>

        <section class="grid grid-cols-3 gap-3">
            <div class="rounded-[1.5rem] bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                    IPK
                </p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ latestRapor?.ipk ?? '-' }}
                </p>
            </div>
            <div class="rounded-[1.5rem] bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                    Ranking
                </p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ latestRapor?.rankKelas ?? '-' }}
                </p>
            </div>
            <div class="rounded-[1.5rem] bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                    Izin
                </p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ attendance.totalDisetujui }}
                </p>
            </div>
        </section>

        <section class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                        Rapor terakhir
                    </p>
                    <h3 class="mt-1 font-jost text-2xl text-slate-900">
                        {{ latestRapor?.periode || 'Belum ada rapor' }}
                    </h3>
                </div>
                <Link
                    v-if="latestRapor"
                    :href="`/wali/santri/${child.id}/rapor/${latestRapor.id}/download`"
                    class="rounded-full bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700"
                >
                    Download PDF
                </Link>
            </div>
            <p class="mt-3 text-sm text-slate-600">
                {{ latestRapor?.catatanWaliKelas || 'Catatan wali kelas belum tersedia.' }}
            </p>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Nilai
                </p>
                <h3 class="mt-1 font-jost text-2xl text-slate-900">
                    Rekap Pelajaran
                </h3>
            </div>

            <div v-if="grades.length" class="grid gap-3">
                <article
                    v-for="grade in grades"
                    :key="`${grade.kode}-${grade.periode}`"
                    class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                {{ grade.kode }}
                            </p>
                            <h4 class="mt-1 text-lg font-semibold text-slate-900">
                                {{ grade.pelajaran }}
                            </h4>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ grade.periode || '-' }}
                            </p>
                        </div>
                        <div class="rounded-full bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-900">
                            {{ grade.nilaiAkhir ?? '-' }}
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Jadwal
                </p>
                <h3 class="mt-1 font-jost text-2xl text-slate-900">
                    Kalender Pelajaran
                </h3>
            </div>

            <div v-if="schedule.length" class="grid gap-3">
                <article
                    v-for="item in schedule"
                    :key="`${item.hari}-${item.jamMulai}-${item.pelajaran}`"
                    class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 text-sm shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <p class="font-semibold text-slate-900">
                        {{ item.hari }} · {{ item.jamMulai }} - {{ item.jamSelesai }}
                    </p>
                    <p class="mt-1 text-slate-500">
                        {{ item.pelajaran }} · {{ item.ruangan || 'Ruang belum diatur' }}
                    </p>
                </article>
            </div>
        </section>
    </div>
</template>
