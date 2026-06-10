<script setup>
import { router } from '@inertiajs/vue3';
import SpmbLayout from '../../../Layouts/SpmbLayout.vue';

const props = defineProps({
    paket: { type: Array, default: () => [] },
});

const tenantPath = () => window.location.pathname.startsWith('/spmb') ? '' : '';

function mulai(p) {
    router.post(`/spmb/ujian/${p.id}/mulai`);
}

function lanjut(p) {
    router.visit(`/spmb/ujian/${p.id}`);
}

function labelStatus(p) {
    if (p.status === 'berjalan') return 'Sedang berjalan';
    if (p.status === 'menunggu_nilai') return 'Menunggu penilaian';
    if (p.status === 'selesai') return p.nilai_final !== null ? `Selesai — nilai ${p.nilai_final}` : 'Selesai';
    return 'Belum dikerjakan';
}
</script>

<template>
    <SpmbLayout>
        <div class="mx-auto max-w-3xl space-y-6 px-4 py-6">
            <header>
                <h1 class="text-2xl font-bold text-stone-950">Ujian Online</h1>
                <p class="mt-1 text-sm text-stone-600">
                    Kerjakan ujian sesuai jadwal. Waktu berjalan dari server — menutup halaman tidak menghentikan timer.
                </p>
            </header>

            <p v-if="!paket.length" class="rounded-2xl border border-stone-200 bg-stone-50 px-4 py-6 text-sm text-stone-600">
                Belum ada paket ujian yang dibuka panitia.
            </p>

            <article
                v-for="p in paket"
                :key="p.id"
                class="rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-base font-semibold text-stone-900">{{ p.nama }}</div>
                        <div class="mt-1 text-xs text-stone-500">
                            {{ p.kategori }} · {{ p.jumlah_soal }} soal · {{ p.durasi_menit }} menit
                        </div>
                        <div class="mt-1 text-sm" :class="p.status === 'selesai' ? 'text-emerald-700' : 'text-stone-600'">
                            {{ labelStatus(p) }}
                        </div>
                    </div>
                    <button
                        v-if="!p.status"
                        type="button"
                        class="rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white"
                        @click="mulai(p)"
                    >
                        Mulai Ujian
                    </button>
                    <button
                        v-else-if="p.status === 'berjalan'"
                        type="button"
                        class="rounded-full bg-amber-500 px-5 py-2.5 text-sm font-semibold text-white"
                        @click="lanjut(p)"
                    >
                        Lanjutkan
                    </button>
                </div>
            </article>
        </div>
    </SpmbLayout>
</template>
