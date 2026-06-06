<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SpmbLayout from '../../../Layouts/SpmbLayout.vue';

const props = defineProps({
    candidate: {
        type: Object,
        required: true,
    },
    fieldConfiguration: {
        type: Object,
        required: true,
    },
    statusCard: {
        type: Object,
        required: true,
    },
    stepper: {
        type: Array,
        required: true,
    },
    checklist: {
        type: Array,
        required: true,
    },
    mainAction: {
        type: Object,
        required: true,
    },
    tracking: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    nama: props.candidate.nama ?? '',
    nik: props.candidate.nik ?? '',
    tempatLahir: props.candidate.tempatLahir ?? '',
    tanggalLahir: props.candidate.tanggalLahir ?? '',
    jenisKelamin: props.candidate.jenisKelamin ?? '',
    jenjangAsal: props.candidate.jenjangAsal ?? '',
    sekolahAsal: props.candidate.sekolahAsal ?? '',
    namaWali: props.candidate.namaWali ?? '',
    hubunganWali: props.candidate.hubunganWali ?? '',
    noHpWali: props.candidate.noHpWali ?? '',
    emailWali: props.candidate.emailWali ?? '',
    programDiminati: props.candidate.programDiminati ?? '',
    catatanTambahan: props.candidate.catatanTambahan ?? '',
});

const formOptions = {
    jenisKelamin: ['Laki-laki', 'Perempuan'],
    jenjangAsal: ['SD/MI', 'SMP/MTs', 'SMA/MA', 'Paket Kesetaraan'],
    programDiminati: ['Reguler', 'Tahfidz', 'Muadalah'],
};

const fieldMode = (field) => props.fieldConfiguration[field]?.mode ?? 'optional';
const isVisible = (field) => fieldMode(field) !== 'hidden';
const isRequired = (field) => fieldMode(field) === 'required';
const fieldLabel = (field, fallback) => {
    const label = props.fieldConfiguration[field]?.label ?? fallback;
    if (isRequired(field)) return `${label} *`;
    if (fieldMode(field) === 'optional') return `${label} (Opsional)`;
    return label;
};

const scrollToBiodata = () => {
    document.getElementById('biodata-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const openTracking = () => {
    window.open(props.tracking.url, '_blank', 'noopener,noreferrer');
};

const submit = () => {
    form.put('/spmb/biodata', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Portal SPMB ${candidate.nomorPendaftaran}`" />

    <SpmbLayout>
        <div class="space-y-6">
            <!-- Navigasi cepat (auto-backlog): Berkas, Pengumuman, Tagihan, Kartu Ujian -->
            <section class="grid grid-cols-2 gap-3">
                <Link
                    href="/spmb/berkas"
                    class="flex flex-col items-center gap-1 rounded-2xl border border-stone-200 bg-white px-2 py-3 text-xs font-medium text-stone-800 shadow-sm transition hover:border-[var(--spmb-primary)]"
                >
                    <span class="text-lg" aria-hidden="true">📄</span> Berkas
                </Link>
                <Link
                    href="/spmb/pengumuman"
                    class="flex flex-col items-center gap-1 rounded-2xl border border-stone-200 bg-white px-2 py-3 text-xs font-medium text-stone-800 shadow-sm transition hover:border-[var(--spmb-primary)]"
                >
                    <span class="text-lg" aria-hidden="true">📣</span> Pengumuman
                </Link>
                <Link
                    href="/spmb/tagihan"
                    class="flex flex-col items-center gap-1 rounded-2xl border border-stone-200 bg-white px-2 py-3 text-xs font-medium text-stone-800 shadow-sm transition hover:border-[var(--spmb-primary)]"
                >
                    <span class="text-lg" aria-hidden="true">🧾</span> Tagihan
                </Link>
                <Link
                    href="/spmb/kartu-ujian"
                    class="flex flex-col items-center gap-1 rounded-2xl border border-stone-200 bg-white px-2 py-3 text-xs font-medium text-stone-800 shadow-sm transition hover:border-[var(--spmb-primary)]"
                >
                    <span class="text-lg" aria-hidden="true">🪪</span> Kartu Ujian
                </Link>
            </section>

            <section class="overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-sm">
                <div class="border-b border-stone-100 px-5 py-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="space-y-3">
                            <div class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">
                                {{ statusCard.badge }}
                            </div>
                            <div>
                                <h1 class="text-2xl font-semibold tracking-tight text-stone-950">
                                    {{ statusCard.title }}
                                </h1>
                                <p class="mt-2 text-sm leading-6 text-stone-600">
                                    {{ statusCard.description }}
                                </p>
                            </div>
                        </div>

                        <div class="min-w-[14rem] rounded-2xl bg-stone-950 px-4 py-4 text-stone-100">
                            <div class="text-xs uppercase tracking-[0.2em] text-stone-400">Nomor pendaftaran</div>
                            <div class="mt-2 text-lg font-semibold">{{ candidate.nomorPendaftaran }}</div>
                            <div class="mt-4 text-xs uppercase tracking-[0.2em] text-stone-400">Kelengkapan biodata</div>
                            <div class="mt-2 text-sm">
                                {{ candidate.completion.filled }} / {{ candidate.completion.total }} field wajib
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 px-5 py-5 md:grid-cols-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-full bg-stone-900 px-4 py-3 text-sm font-semibold text-white"
                        @click="mainAction.type === 'form' ? scrollToBiodata() : openTracking()"
                    >
                        {{ mainAction.label }}
                    </button>

                    <div class="rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700">
                        {{ mainAction.helper }}
                    </div>
                </div>
            </section>

            <section class="rounded-[2rem] border border-stone-200 bg-white px-5 py-5 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-stone-950">Tahap pendaftaran</h2>
                    <p class="mt-1 text-sm text-stone-600">Portal ini akan mengikuti proses panitia secara bertahap.</p>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <article
                        v-for="(step, index) in stepper"
                        :key="step.title"
                        class="rounded-2xl border px-4 py-4"
                        :class="{
                            'border-emerald-200 bg-emerald-50': step.state === 'current',
                            'border-stone-200 bg-white': step.state === 'upcoming',
                            'border-stone-900 bg-stone-950 text-stone-100': step.state === 'done',
                        }"
                    >
                        <div class="text-xs font-semibold uppercase tracking-[0.18em]" :class="step.state === 'done' ? 'text-stone-400' : 'text-stone-500'">
                            Tahap {{ index + 1 }}
                        </div>
                        <div class="mt-2 text-base font-semibold">{{ step.title }}</div>
                        <p class="mt-2 text-sm leading-6" :class="step.state === 'done' ? 'text-stone-300' : 'text-stone-600'">
                            {{ step.hint }}
                        </p>
                    </article>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,0.9fr)]">
                <div id="biodata-form" class="rounded-[2rem] border border-stone-200 bg-white px-5 py-5 shadow-sm">
                    <div class="mb-6 flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-stone-950">Biodata peserta</h2>
                            <p class="mt-1 text-sm text-stone-600">
                                Periksa dan lengkapi data ini. Panitia memakai data yang tampil di sini.
                            </p>
                        </div>
                        <div
                            v-if="!candidate.canEditBiodata"
                            class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800"
                        >
                            Biodata dikunci
                        </div>
                    </div>

                    <form class="space-y-6" @submit.prevent="submit">
                        <div v-if="Object.keys(form.errors).length" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                            <p class="font-semibold">Periksa data berikut:</p>
                            <ul class="mt-2 list-disc pl-5">
                                <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                            </ul>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label v-if="isVisible('nama')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('nama', 'Nama lengkap calon santri') }}</span>
                                <input v-model="form.nama" :required="isRequired('nama')" :disabled="!candidate.canEditBiodata" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60" />
                            </label>

                            <label v-if="isVisible('nik')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('nik', 'NIK / No. identitas') }}</span>
                                <input v-model="form.nik" :required="isRequired('nik')" :disabled="!candidate.canEditBiodata" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60" />
                            </label>

                            <label v-if="isVisible('tempat_lahir')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('tempat_lahir', 'Tempat lahir') }}</span>
                                <input v-model="form.tempatLahir" :required="isRequired('tempat_lahir')" :disabled="!candidate.canEditBiodata" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60" />
                            </label>

                            <label v-if="isVisible('tanggal_lahir')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('tanggal_lahir', 'Tanggal lahir') }}</span>
                                <input v-model="form.tanggalLahir" :required="isRequired('tanggal_lahir')" :disabled="!candidate.canEditBiodata" type="date" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60" />
                            </label>

                            <label v-if="isVisible('jenis_kelamin')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('jenis_kelamin', 'Jenis kelamin') }}</span>
                                <select v-model="form.jenisKelamin" :required="isRequired('jenis_kelamin')" :disabled="!candidate.canEditBiodata" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60">
                                    <option value="">Pilih salah satu</option>
                                    <option v-for="option in formOptions.jenisKelamin" :key="option" :value="option">{{ option }}</option>
                                </select>
                            </label>

                            <label v-if="isVisible('jenjang_asal')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('jenjang_asal', 'Jenjang sekolah asal') }}</span>
                                <select v-model="form.jenjangAsal" :required="isRequired('jenjang_asal')" :disabled="!candidate.canEditBiodata" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60">
                                    <option value="">Pilih jenjang</option>
                                    <option v-for="option in formOptions.jenjangAsal" :key="option" :value="option">{{ option }}</option>
                                </select>
                            </label>
                        </div>

                        <label v-if="isVisible('sekolah_asal')" class="space-y-2">
                            <span class="text-sm font-medium text-stone-700">{{ fieldLabel('sekolah_asal', 'Sekolah asal') }}</span>
                            <input v-model="form.sekolahAsal" :required="isRequired('sekolah_asal')" :disabled="!candidate.canEditBiodata" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60" />
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label v-if="isVisible('nama_wali')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('nama_wali', 'Nama wali') }}</span>
                                <input v-model="form.namaWali" :required="isRequired('nama_wali')" :disabled="!candidate.canEditBiodata" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60" />
                            </label>

                            <label v-if="isVisible('hubungan_wali')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('hubungan_wali', 'Hubungan dengan calon') }}</span>
                                <input v-model="form.hubunganWali" :required="isRequired('hubungan_wali')" :disabled="!candidate.canEditBiodata" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60" />
                            </label>

                            <label v-if="isVisible('no_hp_wali')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('no_hp_wali', 'No. WhatsApp wali') }}</span>
                                <input v-model="form.noHpWali" :required="isRequired('no_hp_wali')" :disabled="!candidate.canEditBiodata" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60" />
                            </label>

                            <label v-if="isVisible('email_wali')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('email_wali', 'Email wali') }}</span>
                                <input v-model="form.emailWali" :required="isRequired('email_wali')" :disabled="!candidate.canEditBiodata" type="email" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60" />
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                            <label v-if="isVisible('program_diminati')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('program_diminati', 'Program diminati') }}</span>
                                <select v-model="form.programDiminati" :required="isRequired('program_diminati')" :disabled="!candidate.canEditBiodata" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60">
                                    <option value="">Pilih program</option>
                                    <option v-for="option in formOptions.programDiminati" :key="option" :value="option">{{ option }}</option>
                                </select>
                            </label>

                            <label v-if="isVisible('catatan_tambahan')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('catatan_tambahan', 'Catatan tambahan') }}</span>
                                <textarea v-model="form.catatanTambahan" :required="isRequired('catatan_tambahan')" :disabled="!candidate.canEditBiodata" rows="4" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white disabled:cursor-not-allowed disabled:opacity-60"></textarea>
                            </label>
                        </div>

                        <div class="flex flex-col gap-3 rounded-2xl border border-dashed border-stone-300 bg-stone-50 px-4 py-4 md:flex-row md:items-center md:justify-between">
                            <p class="text-sm leading-6 text-stone-600">
                                Bila ada salah ketik biodata, perbaiki dari sini sebelum panitia melanjutkan ke tahap berikutnya.
                            </p>
                            <button
                                type="submit"
                                :disabled="form.processing || !candidate.canEditBiodata"
                                class="inline-flex items-center justify-center rounded-full bg-stone-900 px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ form.processing ? 'Menyimpan...' : 'Simpan biodata' }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <section class="rounded-[2rem] border border-stone-200 bg-white px-5 py-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-stone-950">Checklist peserta</h2>
                        <div class="mt-4 space-y-3">
                            <article
                                v-for="item in checklist"
                                :key="item.title"
                                class="rounded-2xl border px-4 py-4"
                                :class="item.done ? 'border-emerald-200 bg-emerald-50' : 'border-stone-200 bg-stone-50'"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 h-3 w-3 rounded-full" :class="item.done ? 'bg-emerald-500' : 'bg-amber-400'" />
                                    <div>
                                        <div class="text-sm font-semibold text-stone-900">{{ item.title }}</div>
                                        <p class="mt-1 text-sm leading-6 text-stone-600">{{ item.description }}</p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="rounded-[2rem] border border-stone-200 bg-stone-950 px-5 py-5 text-stone-100 shadow-sm">
                        <h2 class="text-lg font-semibold">Status cepat tanpa login</h2>
                        <p class="mt-2 text-sm leading-6 text-stone-300">
                            Tautan ini hanya untuk melihat status ringkas. Aksi seperti ubah biodata atau unggah dokumen tetap wajib login.
                        </p>
                        <div class="mt-4 break-all rounded-2xl bg-white/5 px-4 py-3 text-sm text-stone-100">
                            {{ tracking.url }}
                        </div>
                        <button
                            type="button"
                            class="mt-4 inline-flex items-center justify-center rounded-full border border-white/20 px-4 py-3 text-sm font-semibold text-white"
                            @click="openTracking"
                        >
                            Buka link status
                        </button>
                    </section>
                </div>
            </section>
        </div>
    </SpmbLayout>
</template>
