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
    placementHistory: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Profil Santri</title>
        </Head>

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Profil Santri
            </p>
            <h2 class="mt-3 font-jost text-3xl leading-tight">
                {{ child.nama }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-white/75">
                NIS {{ child.nis }} · {{ child.kelas?.nama || 'Belum ada kelas' }}
            </p>
        </section>

        <section class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
            <div class="grid gap-3 text-sm text-slate-600">
                <p><span class="font-semibold text-slate-900">Status:</span> {{ child.status }}</p>
                <p><span class="font-semibold text-slate-900">Tanggal lahir:</span> {{ child.tanggalLahir || '-' }}</p>
                <p><span class="font-semibold text-slate-900">Tempat lahir:</span> {{ child.tempatLahir || '-' }}</p>
                <p><span class="font-semibold text-slate-900">Jenis kelamin:</span> {{ child.jenisKelamin || '-' }}</p>
                <p><span class="font-semibold text-slate-900">Agama:</span> {{ child.agama || '-' }}</p>
                <p><span class="font-semibold text-slate-900">Asal sekolah:</span> {{ child.asalSekolah || '-' }}</p>
                <p><span class="font-semibold text-slate-900">Alamat:</span> {{ child.alamat || '-' }}</p>
                <p><span class="font-semibold text-slate-900">Angkatan:</span> {{ child.tahunAngkatan || '-' }}</p>
                <p><span class="font-semibold text-slate-900">Ayah:</span> {{ child.namaAyah || '-' }}</p>
                <p><span class="font-semibold text-slate-900">Ibu:</span> {{ child.namaIbu || '-' }}</p>
                <p><span class="font-semibold text-slate-900">No HP wali:</span> {{ child.noHpWali || '-' }}</p>
                <p><span class="font-semibold text-slate-900">Email wali:</span> {{ child.emailWali || '-' }}</p>
            </div>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Penempatan
                </p>
                <h3 class="mt-1 font-jost text-2xl text-slate-900">
                    Riwayat Kelas
                </h3>
            </div>

            <div v-if="placementHistory.length" class="grid gap-3">
                <article
                    v-for="item in placementHistory"
                    :key="`${item.kelas}-${item.tanggalPenempatan}`"
                    class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 text-sm shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <p class="font-semibold text-slate-900">
                        {{ item.kelas || 'Belum ada kelas' }}
                    </p>
                    <p class="mt-1 text-slate-500">
                        {{ item.tahunAkademik || '-' }} · {{ item.semester || '-' }}
                    </p>
                    <p class="mt-1 text-slate-500">
                        {{ item.tanggalPenempatan || '-' }}
                    </p>
                </article>
            </div>

            <div
                v-else
                class="rounded-[1.5rem] border border-dashed border-slate-300 bg-white px-4 py-6 text-sm text-slate-600"
            >
                Riwayat penempatan kelas belum tersedia.
            </div>
        </section>

        <Link
            :href="`/wali/santri/${child.id}/akademik`"
            class="block rounded-[1.5rem] bg-emerald-50 px-4 py-4 text-center text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700"
        >
            Lanjut ke akademik
        </Link>
    </div>
</template>
