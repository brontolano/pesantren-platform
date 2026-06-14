<script setup>
import { router } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';
import MdButton from '@/spmb/components/MdButton.vue';

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

// Warna chip status: gaya material, netral stone + aksen sesuai keadaan.
function chipClass(p) {
    if (p.status === 'berjalan') return 'bg-amber-50 text-amber-800 border-amber-200';
    if (p.status === 'menunggu_nilai') return 'bg-sky-50 text-sky-800 border-sky-200';
    if (p.status === 'selesai') return 'bg-emerald-50 text-emerald-800 border-emerald-200';
    return 'bg-stone-100 text-stone-600 border-stone-200';
}
</script>

<template>
    <SpmbLayout show-back :back-href="'/spmb'" title="Ujian Online">
        <div class="space-y-4">
            <!-- Catatan ringkas tentang cara kerja timer -->
            <p class="px-1 text-[13px] leading-relaxed text-stone-600">
                Kerjakan ujian sesuai jadwal. Waktu berjalan dari server — menutup halaman tidak menghentikan timer.
            </p>

            <!-- Empty state ramah -->
            <MdCard v-if="!paket.length">
                <div class="flex flex-col items-center py-6 text-center">
                    <span class="text-3xl">📝</span>
                    <h2 class="mt-3 text-[15px] font-bold text-stone-900">Belum ada ujian</h2>
                    <p class="mt-1 text-[13px] leading-relaxed text-stone-600">
                        Belum ada paket ujian yang dibuka panitia. Cek kembali nanti sesuai jadwal yang diumumkan.
                    </p>
                </div>
            </MdCard>

            <!-- Daftar paket ujian -->
            <MdCard v-for="p in paket" :key="p.id">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-[15px] font-bold leading-snug text-stone-900">{{ p.nama }}</h2>
                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 text-[12px] text-stone-500">
                            <span>{{ p.kategori }}</span>
                            <span class="text-stone-300">·</span>
                            <span>{{ p.jumlah_soal }} soal</span>
                            <span class="text-stone-300">·</span>
                            <span>{{ p.durasi_menit }} menit</span>
                        </div>
                    </div>
                    <span
                        class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                        :class="chipClass(p)"
                    >
                        {{ labelStatus(p) }}
                    </span>
                </div>

                <MdButton
                    v-if="!p.status"
                    variant="primary"
                    class="mt-4"
                    @click="mulai(p)"
                >
                    Mulai Ujian
                </MdButton>
                <MdButton
                    v-else-if="p.status === 'berjalan'"
                    variant="tonal"
                    class="mt-4"
                    @click="lanjut(p)"
                >
                    Lanjutkan
                </MdButton>
            </MdCard>
        </div>
    </SpmbLayout>
</template>
