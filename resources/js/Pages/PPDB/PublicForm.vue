<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    tenant: {
        type: Object,
        required: true,
    },
    fieldConfiguration: {
        type: Object,
        required: true,
    },
    formOptions: {
        type: Object,
        required: true,
    },
    registrationChecklist: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    nama: '',
    nik: '',
    tempatLahir: '',
    tanggalLahir: '',
    jenisKelamin: '',
    jenjangAsal: '',
    sekolahAsal: '',
    namaWali: '',
    hubunganWali: '',
    noHpWali: '',
    emailWali: '',
    programDiminati: '',
    catatanTambahan: '',
    setuju_kebijakan: false,
});

const fieldMode = (field) => props.fieldConfiguration[field]?.mode ?? 'optional';
const isVisible = (field) => fieldMode(field) !== 'hidden';
const isRequired = (field) => fieldMode(field) === 'required';
const fieldLabel = (field, label) => {
    if (isRequired(field)) {
        return `${label} *`;
    }

    if (fieldMode(field) === 'optional') {
        return `${label} (Opsional)`;
    }

    return label;
};

const submit = () => {
    form.post('/ppdb', {
        preserveState: true,
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <Head :title="`PPDB ${tenant.name}`" />

    <div class="min-h-screen bg-[linear-gradient(160deg,#f4efe6_0%,#eef6ef_48%,#d8e8d8_100%)] text-stone-900">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-10 px-6 py-10 lg:flex-row lg:px-10">
            <section class="lg:w-[38%]">
                <div class="sticky top-8 space-y-6">
                    <div class="inline-flex items-center rounded-full border border-emerald-800/10 bg-white/70 px-4 py-2 text-sm font-medium text-emerald-900 shadow-sm backdrop-blur">
                        PPDB Online • {{ tenant.name }}
                    </div>

                    <div class="space-y-4">
                        <h1 class="max-w-xl text-4xl font-semibold tracking-tight text-stone-950 lg:text-5xl">
                            Formulir pendaftaran santri baru yang siap dibuka tanpa login.
                        </h1>
                        <p class="max-w-2xl text-base leading-7 text-stone-700">
                            Halaman ini dipasang di domain tenant agar calon wali bisa mengisi data awal langsung
                            dari website pesantren, tetap dalam satu sistem Laravel dan tetap tenant-aware.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-3xl bg-white/80 p-4 shadow-sm ring-1 ring-black/5">
                            <div class="text-sm text-stone-500">Akses</div>
                            <div class="mt-2 text-lg font-semibold">Tanpa login</div>
                        </div>
                        <div class="rounded-3xl bg-white/80 p-4 shadow-sm ring-1 ring-black/5">
                            <div class="text-sm text-stone-500">Arsitektur</div>
                            <div class="mt-2 text-lg font-semibold">Vue + Inertia</div>
                        </div>
                        <div class="rounded-3xl bg-white/80 p-4 shadow-sm ring-1 ring-black/5">
                            <div class="text-sm text-stone-500">Tenant</div>
                            <div class="mt-2 text-lg font-semibold">{{ tenant.id }}</div>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-emerald-900/10 bg-stone-950 p-6 text-stone-100 shadow-xl">
                        <h2 class="text-lg font-semibold">Alur saat ini</h2>
                        <ul class="mt-4 space-y-3 text-sm leading-6 text-stone-300">
                            <li v-for="item in registrationChecklist" :key="item" class="flex gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-400" />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                        <p class="mt-4 text-xs uppercase tracking-[0.22em] text-amber-300">
                            Field dapat diatur per tenant: wajib, opsional, atau disembunyikan.
                        </p>
                    </div>
                </div>
            </section>

            <section class="lg:flex-1">
                <div class="rounded-[2rem] bg-white/85 p-6 shadow-2xl ring-1 ring-black/5 backdrop-blur lg:p-8">
                        <div class="mb-8 flex flex-col gap-3 border-b border-stone-200 pb-6">
                        <div class="text-sm font-medium uppercase tracking-[0.18em] text-emerald-800">
                            Data awal calon santri
                        </div>
                        <p class="max-w-3xl text-sm leading-6 text-stone-600">
                            Form publik ini sudah tenant-aware dan submit langsung ke ERP. Tenant juga bisa
                            mengatur field mana yang wajib, opsional, atau disembunyikan.
                        </p>
                    </div>

                    <form class="space-y-8" @submit.prevent="submit">
                        <div v-if="Object.keys(form.errors).length" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                            <p class="font-semibold">Perbaiki kesalahan berikut:</p>
                            <ul class="mt-2 list-disc pl-5">
                                <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                            </ul>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <label v-if="isVisible('nama')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('nama', 'Nama lengkap calon santri') }}</span>
                                <input v-model="form.nama" :required="isRequired('nama')" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white" placeholder="Masukkan nama lengkap" />
                            </label>

                            <label v-if="isVisible('nik')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('nik', 'NIK / No. identitas') }}</span>
                                <input v-model="form.nik" :required="isRequired('nik')" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white" placeholder="16 digit jika tersedia" />
                            </label>

                            <label v-if="isVisible('tempat_lahir')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('tempat_lahir', 'Tempat lahir') }}</span>
                                <input v-model="form.tempatLahir" :required="isRequired('tempat_lahir')" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white" placeholder="Contoh: Subang" />
                            </label>

                            <label v-if="isVisible('tanggal_lahir')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('tanggal_lahir', 'Tanggal lahir') }}</span>
                                <input v-model="form.tanggalLahir" :required="isRequired('tanggal_lahir')" type="date" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white" />
                            </label>

                            <label v-if="isVisible('jenis_kelamin')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('jenis_kelamin', 'Jenis kelamin') }}</span>
                                <select v-model="form.jenisKelamin" :required="isRequired('jenis_kelamin')" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white">
                                    <option value="">Pilih salah satu</option>
                                    <option v-for="option in formOptions.jenisKelamin" :key="option" :value="option">
                                        {{ option }}
                                    </option>
                                </select>
                            </label>

                            <label v-if="isVisible('jenjang_asal')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('jenjang_asal', 'Jenjang sekolah asal') }}</span>
                                <select v-model="form.jenjangAsal" :required="isRequired('jenjang_asal')" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white">
                                    <option value="">Pilih jenjang</option>
                                    <option v-for="option in formOptions.jenjangAsal" :key="option" :value="option">
                                        {{ option }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <label v-if="isVisible('sekolah_asal')" class="space-y-2">
                            <span class="text-sm font-medium text-stone-700">{{ fieldLabel('sekolah_asal', 'Sekolah asal') }}</span>
                            <input v-model="form.sekolahAsal" :required="isRequired('sekolah_asal')" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white" placeholder="Nama sekolah / madrasah terakhir" />
                        </label>

                        <div class="grid gap-5 md:grid-cols-2">
                            <label v-if="isVisible('nama_wali')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('nama_wali', 'Nama wali') }}</span>
                                <input v-model="form.namaWali" :required="isRequired('nama_wali')" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white" placeholder="Nama ayah / ibu / wali" />
                            </label>

                            <label v-if="isVisible('hubungan_wali')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('hubungan_wali', 'Hubungan dengan calon') }}</span>
                                <input v-model="form.hubunganWali" :required="isRequired('hubungan_wali')" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white" placeholder="Ayah, Ibu, Kakak, Wali" />
                            </label>

                            <label v-if="isVisible('no_hp_wali')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('no_hp_wali', 'No. WhatsApp wali') }}</span>
                                <input v-model="form.noHpWali" :required="isRequired('no_hp_wali')" type="text" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white" placeholder="08xxxxxxxxxx" />
                            </label>

                            <label v-if="isVisible('email_wali')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('email_wali', 'Email wali') }}</span>
                                <input v-model="form.emailWali" :required="isRequired('email_wali')" type="email" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white" placeholder="email@contoh.com" />
                            </label>
                        </div>

                        <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                            <label v-if="isVisible('program_diminati')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('program_diminati', 'Program diminati') }}</span>
                                <select v-model="form.programDiminati" :required="isRequired('program_diminati')" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white">
                                    <option value="">Pilih program</option>
                                    <option v-for="option in formOptions.programDiminati" :key="option" :value="option">
                                        {{ option }}
                                    </option>
                                </select>
                            </label>

                            <label v-if="isVisible('catatan_tambahan')" class="space-y-2">
                                <span class="text-sm font-medium text-stone-700">{{ fieldLabel('catatan_tambahan', 'Catatan tambahan') }}</span>
                                <textarea v-model="form.catatanTambahan" :required="isRequired('catatan_tambahan')" rows="4" class="w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 outline-none transition focus:border-emerald-500 focus:bg-white" placeholder="Kebutuhan khusus, jalur rekomendasi, atau catatan lain"></textarea>
                            </label>
                        </div>

                        <div class="flex flex-col gap-4 rounded-[1.5rem] border border-dashed border-stone-300 bg-stone-50/90 p-5 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="text-sm font-semibold text-stone-800">Status pengerjaan task</div>
                                <p class="mt-1 text-sm leading-6 text-stone-600">
                                    UI publik sudah siap. Proses submit, nomor pendaftaran, dan unggah dokumen akan
                                    ditautkan bertahap sesuai urutan checklist.
                                </p>
                            </div>

                            <label class="flex items-start gap-3 text-sm text-stone-600">
                                <input v-model="form.setuju_kebijakan" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-300" required>
                                <span>
                                    Saya (orang tua/wali) menyetujui
                                    <a href="/kebijakan-privasi" target="_blank" rel="noopener" class="font-semibold text-emerald-700 underline">Kebijakan Privasi Sistem</a>
                                    — termasuk penggunaan data, foto, dan contoh tulisan anak untuk keperluan pendidikan internal pesantren. *
                                </span>
                            </label>
                            <p v-if="form.errors.setuju_kebijakan" class="text-sm text-red-600">{{ form.errors.setuju_kebijakan }}</p>
                            <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center rounded-full bg-stone-900 px-6 py-3 text-sm font-semibold text-white disabled:opacity-60">
                                {{ form.processing ? 'Mengirim...' : 'Kirim Pendaftaran' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</template>
