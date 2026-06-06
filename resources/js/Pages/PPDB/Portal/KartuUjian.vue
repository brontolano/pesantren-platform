<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import SpmbLayout from '../../../Layouts/SpmbLayout.vue';

const props = defineProps({
    pesantren: { type: String, default: 'Pesantren' },
    nomorPendaftaran: { type: String, default: '' },
    nama: { type: String, default: '' },
    jadwal: { type: Array, default: () => [] },
});

// QR berisi nomor pendaftaran (low-sensitif) via API publik (tanpa dependency npm).
const qrUrl = computed(
    () => `https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=0&data=${encodeURIComponent(props.nomorPendaftaran)}`,
);

function cetak() {
    if (typeof window !== 'undefined') {
        window.print();
    }
}
</script>

<template>
    <Head title="Kartu Peserta Ujian" />

    <SpmbLayout>
        <div class="px-4 py-5 space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold text-stone-900">Kartu Peserta Ujian</h1>
                <button
                    type="button"
                    class="rounded-lg border border-[var(--spmb-primary)] px-3 py-1.5 text-xs font-medium text-[var(--spmb-primary)] transition hover:bg-[var(--spmb-primary)] hover:text-white print:hidden"
                    @click="cetak"
                >
                    🖨️ Cetak
                </button>
            </div>

            <!-- Kartu -->
            <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                <div class="bg-[var(--spmb-primary)] px-4 py-3 text-center text-white">
                    <p class="text-sm font-semibold">{{ pesantren }}</p>
                    <p class="text-[11px] opacity-90">Kartu Peserta Seleksi PPDB</p>
                </div>

                <div class="flex items-center gap-4 px-4 py-4">
                    <img :src="qrUrl" alt="QR Nomor Pendaftaran" width="110" height="110" class="shrink-0 rounded-lg border border-stone-200" />
                    <div class="min-w-0">
                        <p class="text-xs text-stone-500">Nomor Pendaftaran</p>
                        <p class="text-xl font-bold tracking-wide text-stone-900">{{ nomorPendaftaran }}</p>
                        <p class="mt-2 text-xs text-stone-500">Nama</p>
                        <p class="truncate font-medium text-stone-900">{{ nama }}</p>
                    </div>
                </div>

                <!-- Jadwal -->
                <div class="border-t border-stone-100 px-4 py-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">Jadwal Ujian</p>
                    <ul v-if="jadwal.length" class="space-y-2">
                        <li v-for="(j, i) in jadwal" :key="i" class="rounded-lg bg-stone-50 px-3 py-2 text-sm">
                            <div class="flex justify-between gap-2">
                                <span class="font-medium text-stone-800">{{ j.kategori }}</span>
                                <span class="text-stone-600">{{ j.tanggal }}</span>
                            </div>
                            <div class="text-xs text-stone-500">
                                <span v-if="j.waktuMulai">{{ j.waktuMulai }}<span v-if="j.waktuSelesai">–{{ j.waktuSelesai }}</span> WIB</span>
                                <span v-if="j.ruang"> &middot; Ruang {{ j.ruang }}</span>
                            </div>
                            <p v-if="j.keterangan" class="mt-1 text-xs text-stone-400">{{ j.keterangan }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-stone-500">Jadwal ujian belum dirilis. Pantau pengumuman panitia.</p>
                </div>
            </div>

            <p class="text-center text-[11px] text-stone-400 print:hidden">
                Tunjukkan kartu ini (cetak atau layar) saat ujian.
            </p>
        </div>
    </SpmbLayout>
</template>
