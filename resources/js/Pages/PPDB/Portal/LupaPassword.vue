<script setup>
// F1.2.3 — Lupa password via OTP WhatsApp, 2 langkah dalam satu halaman:
// (1) minta kode → (2) masukkan kode + password baru. Langkah 2 terbuka
// otomatis setelah kode terkirim; ada jalan pintas "sudah punya kode".
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';

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

    <SpmbLayout>
        <div class="space-y-6">
            <div class="space-y-1.5">
                <h1 class="text-2xl font-bold tracking-tight">
                    Lupa Password
                </h1>
                <p class="text-sm leading-relaxed text-stone-600">
                    Tenang, password bisa dibuat ulang. Kami akan mengirim kode 6 angka
                    ke nomor WhatsApp yang terdaftar.
                </p>
            </div>

            <!-- Langkah 1: minta kode -->
            <form v-if="step === 1" class="space-y-4" @submit.prevent="requestCode">
                <div class="space-y-1.5">
                    <label for="identifier" class="block text-sm font-medium text-stone-800">
                        Nomor pendaftaran / No. HP / email
                    </label>
                    <input
                        id="identifier"
                        v-model="requestForm.identifier"
                        type="text"
                        name="identifier"
                        autocomplete="username"
                        autocapitalize="none"
                        autocorrect="off"
                        required
                        placeholder="Contoh: PSB-2026-0001 atau 0812…"
                        class="block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/20"
                    >
                    <p v-if="requestForm.errors.identifier" class="text-sm text-red-600">
                        {{ requestForm.errors.identifier }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="requestForm.processing"
                    class="block w-full rounded-xl bg-[var(--spmb-primary)] px-4 py-3.5 text-center text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60"
                >
                    {{ requestForm.processing ? 'Mengirim…' : 'Kirim Kode ke WhatsApp' }}
                </button>

                <button
                    type="button"
                    class="block w-full text-center text-sm font-medium text-[var(--spmb-primary)] underline-offset-2 hover:underline"
                    @click="goToStepTwo"
                >
                    Saya sudah punya kode
                </button>
            </form>

            <!-- Langkah 2: kode + password baru -->
            <form v-else class="space-y-4" @submit.prevent="submitReset">
                <div class="space-y-1.5">
                    <label for="reset-identifier" class="block text-sm font-medium text-stone-800">
                        Nomor pendaftaran / No. HP / email
                    </label>
                    <input
                        id="reset-identifier"
                        v-model="resetForm.identifier"
                        type="text"
                        name="identifier"
                        autocomplete="username"
                        autocapitalize="none"
                        autocorrect="off"
                        required
                        placeholder="Sama dengan yang tadi diisi"
                        class="block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/20"
                    >
                    <p v-if="resetForm.errors.identifier" class="text-sm text-red-600">
                        {{ resetForm.errors.identifier }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="kode" class="block text-sm font-medium text-stone-800">
                        Kode dari WhatsApp (6 angka)
                    </label>
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
                        class="block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-center text-2xl font-semibold tracking-[0.5em] shadow-sm outline-none transition placeholder:tracking-normal focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/20"
                    >
                    <p v-if="resetForm.errors.kode" class="text-sm text-red-600">
                        {{ resetForm.errors.kode }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-stone-800">
                        Password baru (minimal 8 karakter)
                    </label>
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
                            class="block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 pr-20 text-base shadow-sm outline-none transition focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/20"
                        >
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 px-4 text-sm font-medium text-stone-500 transition hover:text-stone-800"
                            @click="showPassword = !showPassword"
                        >
                            {{ showPassword ? 'Tutup' : 'Lihat' }}
                        </button>
                    </div>
                    <p v-if="resetForm.errors.password" class="text-sm text-red-600">
                        {{ resetForm.errors.password }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block text-sm font-medium text-stone-800">
                        Ulangi password baru
                    </label>
                    <input
                        id="password_confirmation"
                        v-model="resetForm.password_confirmation"
                        :type="showPassword ? 'text' : 'password'"
                        name="password_confirmation"
                        autocomplete="new-password"
                        required
                        minlength="8"
                        placeholder="Ketik ulang password yang sama"
                        class="block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/20"
                    >
                </div>

                <button
                    type="submit"
                    :disabled="resetForm.processing"
                    class="block w-full rounded-xl bg-[var(--spmb-primary)] px-4 py-3.5 text-center text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60"
                >
                    {{ resetForm.processing ? 'Memproses…' : 'Ganti Password & Masuk' }}
                </button>

                <button
                    type="button"
                    class="block w-full text-center text-sm font-medium text-stone-500 underline-offset-2 hover:underline"
                    @click="step = 1"
                >
                    ← Kirim ulang kode
                </button>
            </form>

            <p class="text-center text-sm text-stone-600">
                Ingat password Anda?
                <Link
                    href="/spmb/login"
                    class="font-medium text-[var(--spmb-primary)] underline-offset-2 hover:underline"
                >
                    Kembali masuk
                </Link>
            </p>
        </div>
    </SpmbLayout>
</template>
