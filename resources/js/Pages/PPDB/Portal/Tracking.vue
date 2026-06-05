<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';

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

    <SpmbLayout>
        <div class="space-y-6">
            <section class="overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-sm">
                <div class="border-b border-stone-100 px-5 py-5">
                    <div class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">
                        {{ statusCard.badge }}
                    </div>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-stone-950">
                        {{ statusCard.title }}
                    </h1>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        {{ statusCard.description }}
                    </p>
                </div>

                <div class="grid gap-4 px-5 py-5 md:grid-cols-2">
                    <article class="rounded-2xl bg-stone-950 px-4 py-4 text-stone-100">
                        <div class="text-xs uppercase tracking-[0.2em] text-stone-400">Nomor pendaftaran</div>
                        <div class="mt-2 text-lg font-semibold">{{ snapshot.nomorPendaftaran }}</div>
                        <div class="mt-4 text-xs uppercase tracking-[0.2em] text-stone-400">Nama peserta</div>
                        <div class="mt-2 text-sm">{{ snapshot.nama }}</div>
                    </article>

                    <article class="rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4 text-sm text-stone-700">
                        <div class="font-semibold text-stone-900">Status cepat</div>
                        <div class="mt-3 space-y-2">
                            <div class="flex items-center justify-between gap-4">
                                <span>Dokumen yang sudah masuk</span>
                                <span class="font-semibold text-stone-950">{{ snapshot.dokumenTerkirim }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span>Hasil ujian tercatat</span>
                                <span class="font-semibold text-stone-950">{{ snapshot.hasilUjianTercatat }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span>Tenant</span>
                                <span class="font-semibold text-stone-950">{{ tenant.name }}</span>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                <article class="rounded-[2rem] border border-stone-200 bg-white px-5 py-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-stone-950">Apa yang bisa dilakukan dari sini</h2>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-stone-600">
                        <li class="flex gap-3">
                            <span class="mt-2 h-2.5 w-2.5 rounded-full bg-emerald-500" />
                            <span>Tautan ini hanya menampilkan status ringkas yang aman dibuka tanpa login.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 h-2.5 w-2.5 rounded-full bg-emerald-500" />
                            <span>Untuk melengkapi biodata, unggah dokumen, dan langkah lanjutan, peserta harus masuk ke portal SPMB.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 h-2.5 w-2.5 rounded-full bg-emerald-500" />
                            <span>Simpan nomor pendaftaran ini karena dipakai untuk aktivasi dan komunikasi dengan panitia.</span>
                        </li>
                    </ul>
                </article>

                <article class="rounded-[2rem] border border-stone-200 bg-stone-950 px-5 py-5 text-stone-100 shadow-sm">
                    <h2 class="text-lg font-semibold">Lanjut ke portal peserta</h2>
                    <p class="mt-2 text-sm leading-6 text-stone-300">
                        Jika akun sudah aktif, masuk untuk melihat langkah berikutnya. Jika belum, aktivasi dulu memakai nomor pendaftaran ini.
                    </p>
                    <div class="mt-5 grid gap-3">
                        <Link
                            :href="cta.loginUrl"
                            class="inline-flex items-center justify-center rounded-full bg-white px-4 py-3 text-sm font-semibold text-stone-950"
                        >
                            Login untuk melanjutkan
                        </Link>
                        <Link
                            :href="cta.activationUrl"
                            class="inline-flex items-center justify-center rounded-full border border-white/20 px-4 py-3 text-sm font-semibold text-white"
                        >
                            Aktivasi akun peserta
                        </Link>
                    </div>
                </article>
            </section>
        </div>
    </SpmbLayout>
</template>
