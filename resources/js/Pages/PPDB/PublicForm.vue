<script setup>
// Form pendaftaran publik — SENGAJA singkat: cukup untuk menerbitkan nomor +
// akun. Biodata lengkap diisi peserta di portal /spmb setelah aktivasi.
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    tenant: { type: Object, required: true },
    formOptions: { type: Object, required: true },
    registrationChecklist: { type: Array, required: true },
});

const form = useForm({
    nama: '',
    jenisKelamin: '',
    tempatLahir: '',
    tanggalLahir: '',
    jenjangAsal: '',
    asalSekolah: '',
    namaWali: '',
    noHpWali: '',
    emailWali: '',
    setuju_kebijakan: false,
});

const inputClass = 'w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-[15px] outline-none transition focus:border-violet-500 focus:bg-white';

const submit = () => form.post('/ppdb', { preserveState: true });
</script>

<template>
    <Head :title="`Pendaftaran ${tenant.name}`" />

    <div class="min-h-screen bg-[linear-gradient(160deg,#f4efe6_0%,#eef6ef_48%,#d8e8d8_100%)] text-stone-900">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-8 px-5 py-8 lg:flex-row lg:px-8 lg:py-12">
            <!-- Panel info -->
            <section class="lg:w-[40%]">
                <div class="space-y-5 lg:sticky lg:top-8">
                    <span class="inline-flex items-center rounded-full border border-violet-800/10 bg-white/70 px-4 py-2 text-sm font-medium text-violet-900 shadow-sm backdrop-blur">
                        Pendaftaran Santri Baru • {{ tenant.name }}
                    </span>
                    <h1 class="text-3xl font-semibold tracking-tight text-stone-950 lg:text-4xl">
                        Daftar dulu dengan data ringkas.
                    </h1>
                    <p class="text-[15px] leading-7 text-stone-700">
                        Isi data singkat di bawah untuk menerbitkan nomor pendaftaran dan akun. Biodata
                        lengkap (alamat, data orang tua, dan unggah dokumen) dilakukan di Portal SPMB setelah akun aktif.
                    </p>
                    <div class="rounded-3xl bg-stone-950 p-5 text-stone-100 shadow-xl">
                        <h2 class="text-base font-semibold">Langkahnya</h2>
                        <ul class="mt-3 space-y-2.5 text-sm leading-6 text-stone-300">
                            <li v-for="item in registrationChecklist" :key="item" class="flex gap-3">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-violet-400" />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Form -->
            <section class="lg:flex-1">
                <div class="rounded-3xl bg-white/90 p-5 shadow-2xl ring-1 ring-black/5 backdrop-blur sm:p-7">
                    <div class="mb-6 border-b border-stone-200 pb-4">
                        <div class="text-xs font-medium uppercase tracking-[0.18em] text-violet-800">Data awal calon santri</div>
                        <p class="mt-2 text-sm leading-6 text-stone-600">Pastikan nomor WhatsApp wali aktif — kode aktivasi dikirim ke sana.</p>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div v-if="Object.keys(form.errors).length" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                            <p class="font-semibold">Perbaiki kesalahan berikut:</p>
                            <ul class="mt-2 list-disc pl-5">
                                <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                            </ul>
                        </div>

                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-stone-700">Nama lengkap calon santri *</span>
                            <input v-model="form.nama" type="text" required :class="inputClass" placeholder="Masukkan nama lengkap">
                        </label>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="block space-y-1.5">
                                <span class="text-sm font-medium text-stone-700">Jenis kelamin *</span>
                                <select v-model="form.jenisKelamin" required :class="inputClass">
                                    <option value="">Pilih salah satu</option>
                                    <option v-for="opt in formOptions.jenisKelamin" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </label>
                            <label class="block space-y-1.5">
                                <span class="text-sm font-medium text-stone-700">Tanggal lahir *</span>
                                <input v-model="form.tanggalLahir" type="date" required :class="inputClass">
                            </label>
                            <label class="block space-y-1.5">
                                <span class="text-sm font-medium text-stone-700">Tempat lahir</span>
                                <input v-model="form.tempatLahir" type="text" :class="inputClass" placeholder="Contoh: Subang">
                            </label>
                            <label class="block space-y-1.5">
                                <span class="text-sm font-medium text-stone-700">Jenjang sekolah asal *</span>
                                <select v-model="form.jenjangAsal" required :class="inputClass">
                                    <option value="">Pilih jenjang</option>
                                    <option v-for="opt in formOptions.jenjangAsal" :key="opt" :value="opt">{{ opt }}</option>
                                </select>
                            </label>
                        </div>

                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-stone-700">Nama sekolah asal *</span>
                            <input v-model="form.asalSekolah" type="text" required :class="inputClass" placeholder="mis. MTs Asy-Syifaa (tulis nama sekolahnya)">
                        </label>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="block space-y-1.5">
                                <span class="text-sm font-medium text-stone-700">Nama orang tua / wali *</span>
                                <input v-model="form.namaWali" type="text" required :class="inputClass" placeholder="Nama ayah / ibu / wali">
                            </label>
                            <label class="block space-y-1.5">
                                <span class="text-sm font-medium text-stone-700">No. WhatsApp wali *</span>
                                <input v-model="form.noHpWali" type="tel" inputmode="tel" required :class="inputClass" placeholder="08xxxxxxxxxx">
                            </label>
                        </div>

                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium text-stone-700">Email wali (opsional)</span>
                            <input v-model="form.emailWali" type="email" :class="inputClass" placeholder="email@contoh.com">
                        </label>

                        <div class="rounded-2xl border border-dashed border-stone-300 bg-stone-50/90 p-4">
                            <label class="flex items-start gap-3 text-sm text-stone-600">
                                <input v-model="form.setuju_kebijakan" type="checkbox" required class="mt-1 h-4 w-4 rounded border-stone-300">
                                <span>
                                    Saya (orang tua/wali) menyetujui
                                    <a href="/kebijakan-privasi" target="_blank" rel="noopener" class="font-semibold text-violet-700 underline">Kebijakan Privasi Sistem</a>
                                    — termasuk penggunaan data, foto, dan contoh tulisan anak untuk keperluan pendidikan internal pesantren. *
                                </span>
                            </label>
                            <p v-if="form.errors.setuju_kebijakan" class="mt-2 text-sm text-red-600">{{ form.errors.setuju_kebijakan }}</p>
                        </div>

                        <button type="submit" :disabled="form.processing" class="inline-flex w-full items-center justify-center rounded-full bg-stone-900 px-6 py-3.5 text-[15px] font-semibold text-white transition active:scale-[0.99] disabled:opacity-60 sm:w-auto">
                            {{ form.processing ? 'Mengirim…' : 'Kirim Pendaftaran' }}
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</template>
