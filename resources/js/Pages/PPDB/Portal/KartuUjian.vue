<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';

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

    <SpmbLayout show-back :back-href="'/spmb'" title="Kartu Ujian">
        <template #app-bar-actions>
            <button
                type="button"
                class="inline-flex h-9 items-center gap-1.5 rounded-full bg-white/15 px-3 text-[13px] font-semibold text-white transition active:scale-[0.98] active:bg-white/25 print:hidden"
                @click="cetak"
            >
                🖨️ Cetak
            </button>
        </template>

        <div class="space-y-4">
            <!-- Kartu peserta -->
            <MdCard :padded="false">
                <div class="bg-[var(--spmb-primary)] px-4 py-3.5 text-center text-white">
                    <p class="text-[15px] font-bold">{{ pesantren }}</p>
                    <p class="text-[11px] tracking-wide opacity-90">Kartu Peserta Seleksi PPDB</p>
                </div>

                <div class="flex items-center gap-4 px-4 py-5">
                    <img :src="qrUrl" alt="QR Nomor Pendaftaran" width="110" height="110" class="shrink-0 rounded-xl border border-stone-200" />
                    <div class="min-w-0">
                        <p class="text-[11px] uppercase tracking-wide text-stone-500">Nomor Pendaftaran</p>
                        <p class="text-xl font-bold tracking-wide text-stone-900">{{ nomorPendaftaran }}</p>
                        <p class="mt-3 text-[11px] uppercase tracking-wide text-stone-500">Nama</p>
                        <p class="truncate text-[15px] font-bold text-stone-900">{{ nama }}</p>
                    </div>
                </div>

                <!-- Jadwal -->
                <div class="border-t border-stone-100 px-4 py-4">
                    <p class="mb-2.5 text-[11px] font-semibold uppercase tracking-wide text-stone-500">Jadwal Ujian</p>
                    <ul v-if="jadwal.length" class="space-y-2">
                        <li v-for="(j, i) in jadwal" :key="i" class="rounded-xl border border-stone-200/80 bg-stone-50 px-3.5 py-2.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[14px] font-bold text-stone-800">{{ j.kategori }}</span>
                                <span class="text-[13px] font-medium text-stone-600">{{ j.tanggal }}</span>
                            </div>
                            <div class="mt-0.5 text-[12px] text-stone-500">
                                <span v-if="j.waktuMulai">{{ j.waktuMulai }}<span v-if="j.waktuSelesai">–{{ j.waktuSelesai }}</span> WIB</span>
                                <span v-if="j.ruang"> &middot; Ruang {{ j.ruang }}</span>
                            </div>
                            <p v-if="j.keterangan" class="mt-1 text-[12px] text-stone-400">{{ j.keterangan }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-[13px] leading-relaxed text-stone-500">Jadwal ujian belum dirilis. Pantau pengumuman panitia.</p>
                </div>
            </MdCard>

            <p class="text-center text-[12px] leading-relaxed text-stone-400 print:hidden">
                Tunjukkan kartu ini (cetak atau layar) saat ujian.
            </p>
        </div>
    </SpmbLayout>
</template>
