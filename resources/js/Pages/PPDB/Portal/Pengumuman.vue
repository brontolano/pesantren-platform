<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import SpmbLayout from '../../../Layouts/SpmbLayout.vue';

const props = defineProps({
    nomorPendaftaran: { type: String, default: '' },
    nama: { type: String, default: '' },
    sudahAda: { type: Boolean, default: false },
    keputusan: { type: String, default: null }, // lulus | cadangan | ditolak | null
    badge: { type: String, default: '' },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});

const tema = computed(() => ({
    lulus: { box: 'bg-emerald-50 border-emerald-200', text: 'text-emerald-800', judul: 'Selamat, Anda DITERIMA! 🎉' },
    cadangan: { box: 'bg-amber-50 border-amber-200', text: 'text-amber-800', judul: 'Anda masuk daftar CADANGAN' },
    ditolak: { box: 'bg-rose-50 border-rose-200', text: 'text-rose-800', judul: 'Mohon maaf, belum lulus' },
}[props.keputusan] ?? null));
</script>

<template>
    <Head title="Pengumuman Seleksi" />

    <SpmbLayout>
        <div class="px-4 py-5 space-y-4">
            <div>
                <h1 class="text-lg font-semibold text-stone-900">Pengumuman Seleksi</h1>
                <p class="text-sm text-stone-500">
                    <span v-if="nama">{{ nama }}</span><span v-if="nomorPendaftaran"> &middot; No. {{ nomorPendaftaran }}</span>
                </p>
            </div>

            <!-- Keputusan sudah dipublikasikan -->
            <div
                v-if="sudahAda && tema"
                class="rounded-2xl border px-4 py-5 text-center"
                :class="[tema.box, tema.text]"
            >
                <p class="text-base font-semibold">{{ tema.judul }}</p>
                <p class="mt-1 text-sm">{{ title }}</p>
                <p v-if="description" class="mt-2 text-sm opacity-90">{{ description }}</p>
            </div>

            <!-- Belum ada keputusan -->
            <div v-else class="rounded-2xl border border-black/5 bg-white px-4 py-6 text-center shadow-sm">
                <p class="text-sm font-medium text-stone-700">Pengumuman belum tersedia</p>
                <p class="mt-1 text-xs text-stone-500">
                    Hasil seleksi akan tampil di sini setelah dipublikasikan panitia.
                </p>
                <span class="mt-3 inline-block rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">
                    Status saat ini: {{ badge }}
                </span>
                <p v-if="description" class="mt-3 text-xs text-stone-500">{{ description }}</p>
            </div>
        </div>
    </SpmbLayout>
</template>
