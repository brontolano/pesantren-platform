<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';

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
    lulus: { box: 'border-emerald-200 bg-emerald-50', text: 'text-emerald-800', icon: '🎉', judul: 'Selamat, Anda DITERIMA!' },
    cadangan: { box: 'border-amber-200 bg-amber-50', text: 'text-amber-800', icon: '📋', judul: 'Anda masuk daftar CADANGAN' },
    ditolak: { box: 'border-red-200 bg-red-50', text: 'text-red-800', icon: '🙏', judul: 'Mohon maaf, belum lulus' },
}[props.keputusan] ?? null));
</script>

<template>
    <Head title="Pengumuman Seleksi" />

    <SpmbLayout show-back :back-href="'/spmb'" title="Pengumuman">
        <div class="space-y-4">
            <!-- Kepala -->
            <div class="px-1">
                <h1 class="text-[17px] font-bold leading-snug text-stone-900">Pengumuman Seleksi</h1>
                <p class="mt-0.5 text-[13px] text-stone-500">
                    <span v-if="nama">{{ nama }}</span><span v-if="nomorPendaftaran"> &middot; No. {{ nomorPendaftaran }}</span>
                </p>
            </div>

            <!-- Keputusan sudah dipublikasikan -->
            <MdCard v-if="sudahAda && tema" :padded="false">
                <div class="border px-5 py-7 text-center" :class="[tema.box, tema.text]">
                    <div class="text-4xl leading-none">{{ tema.icon }}</div>
                    <p class="mt-3 text-[17px] font-bold">{{ tema.judul }}</p>
                    <p class="mt-1.5 text-[15px] font-semibold">{{ title }}</p>
                    <p v-if="description" class="mt-2 text-[13px] leading-relaxed opacity-90">{{ description }}</p>
                </div>
            </MdCard>

            <!-- Belum ada keputusan -->
            <MdCard v-else>
                <div class="py-4 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-stone-100 text-2xl">📣</div>
                    <p class="mt-3 text-[15px] font-bold text-stone-900">Pengumuman belum tersedia</p>
                    <p class="mt-1 text-[13px] leading-relaxed text-stone-500">
                        Hasil seleksi akan tampil di sini setelah dipublikasikan panitia.
                    </p>
                    <span class="mt-4 inline-block rounded-full border border-stone-200 bg-stone-50 px-3 py-1 text-[12px] font-semibold text-stone-600">
                        Status saat ini: {{ badge }}
                    </span>
                    <p v-if="description" class="mt-3 text-[12px] leading-relaxed text-stone-500">{{ description }}</p>
                </div>
            </MdCard>
        </div>
    </SpmbLayout>
</template>
