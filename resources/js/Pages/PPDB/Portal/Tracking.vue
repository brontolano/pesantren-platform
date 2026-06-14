<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';
import MdButton from '@/spmb/components/MdButton.vue';

const props = defineProps({
    tenant: {
        type: Object,
        required: true,
    },
    snapshot: {
        type: Object,
        required: true,
    },
    statusCard: {
        type: Object,
        required: true,
    },
    cta: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="`Status SPMB ${snapshot.nomorPendaftaran}`" />

    <SpmbLayout :bottom-nav="false" title="Status Pendaftaran">
        <div class="space-y-4">
            <!-- Status snapshot -->
            <MdCard>
                <span class="inline-flex rounded-full bg-[var(--spmb-primary)]/12 px-2.5 py-1 text-[11px] font-semibold text-[var(--spmb-primary)]">
                    {{ statusCard.badge }}
                </span>
                <h1 class="mt-2 text-lg font-bold leading-snug text-stone-900">
                    {{ statusCard.title }}
                </h1>
                <p class="mt-1 text-[13px] leading-relaxed text-stone-600">
                    {{ statusCard.description }}
                </p>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-stone-900 px-3 py-2.5 text-white">
                        <div class="text-[10px] uppercase tracking-wider text-white/60">No. Pendaftaran</div>
                        <div class="mt-0.5 text-[15px] font-bold">{{ snapshot.nomorPendaftaran }}</div>
                    </div>
                    <div class="rounded-xl bg-stone-100 px-3 py-2.5">
                        <div class="text-[10px] uppercase tracking-wider text-stone-500">Nama Peserta</div>
                        <div class="mt-0.5 text-[15px] font-bold text-stone-900">{{ snapshot.nama }}</div>
                    </div>
                </div>
            </MdCard>

            <!-- Status cepat -->
            <MdCard>
                <h2 class="mb-3 text-[15px] font-bold text-stone-900">Status cepat</h2>
                <div class="space-y-2.5 text-[13px] text-stone-700">
                    <div class="flex items-center justify-between gap-4 border-b border-stone-100 pb-2.5">
                        <span>Dokumen yang sudah masuk</span>
                        <span class="font-semibold text-stone-900">{{ snapshot.dokumenTerkirim }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-stone-100 pb-2.5">
                        <span>Hasil ujian tercatat</span>
                        <span class="font-semibold text-stone-900">{{ snapshot.hasilUjianTercatat }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span>Pesantren</span>
                        <span class="font-semibold text-stone-900">{{ tenant.name }}</span>
                    </div>
                </div>
            </MdCard>

            <!-- Apa yang bisa dilakukan -->
            <MdCard>
                <h2 class="mb-3 text-[15px] font-bold text-stone-900">Apa yang bisa dilakukan dari sini</h2>
                <ul class="space-y-2.5 text-[13px] leading-relaxed text-stone-600">
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[var(--spmb-primary)]" />
                        <span>Tautan ini hanya menampilkan status ringkas yang aman dibuka tanpa login.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[var(--spmb-primary)]" />
                        <span>Untuk melengkapi biodata, unggah dokumen, dan langkah lanjutan, peserta harus masuk ke portal SPMB.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[var(--spmb-primary)]" />
                        <span>Simpan nomor pendaftaran ini karena dipakai untuk aktivasi dan komunikasi dengan panitia.</span>
                    </li>
                </ul>
            </MdCard>

            <!-- CTA lanjut ke portal -->
            <MdCard>
                <h2 class="text-[15px] font-bold text-stone-900">Lanjut ke portal peserta</h2>
                <p class="mt-1 text-[13px] leading-relaxed text-stone-600">
                    Jika akun sudah aktif, masuk untuk melihat langkah berikutnya. Jika belum, aktivasi dulu memakai nomor pendaftaran ini.
                </p>
                <div class="mt-4 grid gap-3">
                    <Link :href="cta.loginUrl" class="block">
                        <MdButton variant="primary">Login untuk melanjutkan</MdButton>
                    </Link>
                    <Link :href="cta.activationUrl" class="block">
                        <MdButton variant="tonal">Aktivasi akun peserta</MdButton>
                    </Link>
                </div>
            </MdCard>
        </div>
    </SpmbLayout>
</template>
