<script setup>
// F1.2.3 — Lupa password via OTP WhatsApp, 2 langkah dalam satu halaman:
// (1) minta kode → (2) masukkan kode + password baru. Langkah 2 terbuka
// otomatis setelah kode terkirim; ada jalan pintas "sudah punya kode".
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';
import MdButton from '@/spmb/components/MdButton.vue';
import MdField from '@/spmb/components/MdField.vue';

const step = ref(1);
const showPassword = ref(false);

const requestForm = useForm({
    identifier: '',
});

const resetForm = useForm({
    identifier: '',
    kode: '',
    password: '',
    password_confirmation: '',
});

const requestCode = () => {
    requestForm.post('/spmb/lupa-password', {
        preserveScroll: true,
        onSuccess: () => {
            resetForm.identifier = requestForm.identifier;
            step.value = 2;
        },
    });
};

const goToStepTwo = () => {
    resetForm.identifier = requestForm.identifier;
    step.value = 2;
};

const submitReset = () => {
    resetForm.post('/spmb/lupa-password/reset', {
        onFinish: () => resetForm.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Lupa Password" />

    <SpmbLayout :bottom-nav="false" brand-header>
        <div class="space-y-4">
            <div class="px-1">
                <h1 class="text-2xl font-bold tracking-tight text-stone-900">
                    Lupa Password
                </h1>
                <p class="mt-1 text-[13px] leading-relaxed text-stone-600">
                    Tenang, password bisa dibuat ulang. Kami akan mengirim kode 6 angka
                    ke nomor WhatsApp yang terdaftar.
                </p>
            </div>

            <MdCard>
                <!-- Langkah 1: minta kode -->
                <form v-if="step === 1" class="space-y-4" @submit.prevent="requestCode">
                    <MdField
                        v-model="requestForm.identifier"
                        type="text"
                        label="Nomor pendaftaran / No. HP / email"
                        placeholder="Contoh: PSB-2026-0001 atau 0812…"
                        required
                        :error="requestForm.errors.identifier"
                    />

                    <MdButton type="submit" :loading="requestForm.processing">
                        {{ requestForm.processing ? 'Mengirim…' : 'Kirim Kode ke WhatsApp' }}
                    </MdButton>

                    <button
                        type="button"
                        class="block w-full text-center text-[13px] font-semibold text-[var(--spmb-primary)] underline-offset-2 active:underline"
                        @click="goToStepTwo"
                    >
                        Saya sudah punya kode
                    </button>
                </form>

                <!-- Langkah 2: kode + password baru -->
                <form v-else class="space-y-4" @submit.prevent="submitReset">
                    <MdField
                        v-model="resetForm.identifier"
                        type="text"
                        label="Nomor pendaftaran / No. HP / email"
                        placeholder="Sama dengan yang tadi diisi"
                        required
                        :error="resetForm.errors.identifier"
                    />

                    <div>
                        <span class="mb-1.5 block text-[13px] font-medium text-stone-700">
                            Kode dari WhatsApp (6 angka)<span class="text-red-500"> *</span>
                        </span>
                        <input
                            id="kode"
                            v-model="resetForm.kode"
                            type="text"
                            name="kode"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            autocomplete="one-time-code"
                            required
                            placeholder="••••••"
                            class="block h-14 w-full rounded-xl border bg-white px-3.5 text-center text-2xl font-semibold tracking-[0.5em]
                                   text-stone-900 outline-none transition placeholder:tracking-normal placeholder:text-stone-400
                                   focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/25"
                            :class="resetForm.errors.kode ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-300'"
                        >
                        <p v-if="resetForm.errors.kode" class="mt-1 text-[12px] font-medium text-red-600">
                            {{ resetForm.errors.kode }}
                        </p>
                    </div>

                    <div>
                        <span class="mb-1.5 block text-[13px] font-medium text-stone-700">
                            Password baru (minimal 8 karakter)<span class="text-red-500"> *</span>
                        </span>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="resetForm.password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                autocomplete="new-password"
                                required
                                minlength="8"
                                placeholder="Password baru"
                                class="block h-12 w-full rounded-xl border bg-white px-3.5 pr-20 text-[15px]
                                       text-stone-900 outline-none transition placeholder:text-stone-400
                                       focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/25"
                                :class="resetForm.errors.password ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-300'"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 px-4 text-[13px] font-semibold text-stone-500 transition active:text-stone-800"
                                @click="showPassword = !showPassword"
                            >
                                {{ showPassword ? 'Tutup' : 'Lihat' }}
                            </button>
                        </div>
                        <p v-if="resetForm.errors.password" class="mt-1 text-[12px] font-medium text-red-600">
                            {{ resetForm.errors.password }}
                        </p>
                    </div>

                    <div>
                        <span class="mb-1.5 block text-[13px] font-medium text-stone-700">
                            Ulangi password baru<span class="text-red-500"> *</span>
                        </span>
                        <input
                            id="password_confirmation"
                            v-model="resetForm.password_confirmation"
                            :type="showPassword ? 'text' : 'password'"
                            name="password_confirmation"
                            autocomplete="new-password"
                            required
                            minlength="8"
                            placeholder="Ketik ulang password yang sama"
                            class="block h-12 w-full rounded-xl border border-stone-300 bg-white px-3.5 text-[15px]
                                   text-stone-900 outline-none transition placeholder:text-stone-400
                                   focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/25"
                        >
                    </div>

                    <MdButton type="submit" :loading="resetForm.processing">
                        {{ resetForm.processing ? 'Memproses…' : 'Ganti Password & Masuk' }}
                    </MdButton>

                    <button
                        type="button"
                        class="block w-full text-center text-[13px] font-semibold text-stone-500 underline-offset-2 active:underline"
                        @click="step = 1"
                    >
                        ← Kirim ulang kode
                    </button>
                </form>
            </MdCard>

            <p class="text-center text-[13px] text-stone-600">
                Ingat password Anda?
                <Link
                    href="/spmb/login"
                    class="font-semibold text-[var(--spmb-primary)] underline-offset-2 active:underline"
                >
                    Kembali masuk
                </Link>
            </p>
        </div>
    </SpmbLayout>
</template>
