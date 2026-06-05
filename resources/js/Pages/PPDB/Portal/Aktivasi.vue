<script setup>
// F1.2.2 — Halaman aktivasi akun: masukkan kode OTP dari WhatsApp lalu
// buat password. Nomor pendaftaran ter-prefill bila datang dari redirect
// formulir publik (?pendaftaran=…). Tersedia tombol kirim ulang kode
// dengan jeda 60 detik (rate limit per nomor tetap dijaga server).
import { onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';

const props = defineProps({
    pendaftaran: {
        type: String,
        default: '',
    },
});

const form = useForm({
    pendaftaran: props.pendaftaran,
    kode: '',
    password: '',
    password_confirmation: '',
});

const resendForm = useForm({
    pendaftaran: props.pendaftaran,
});

const showPassword = ref(false);
const resendCooldown = ref(0);
let cooldownTimer = null;

const startCooldown = () => {
    resendCooldown.value = 60;
    cooldownTimer = setInterval(() => {
        resendCooldown.value -= 1;
        if (resendCooldown.value <= 0) {
            clearInterval(cooldownTimer);
        }
    }, 1000);
};

onBeforeUnmount(() => {
    if (cooldownTimer) {
        clearInterval(cooldownTimer);
    }
});

const submit = () => {
    form.post('/spmb/aktivasi', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const resend = () => {
    resendForm.pendaftaran = form.pendaftaran;
    resendForm.post('/spmb/aktivasi/kirim-ulang', {
        preserveScroll: true,
        onSuccess: () => startCooldown(),
    });
};
</script>

<template>
    <Head title="Aktivasi Akun" />

    <SpmbLayout>
        <div class="space-y-6">
            <div class="space-y-1.5">
                <h1 class="text-2xl font-bold tracking-tight">
                    Aktifkan Akun Anda
                </h1>
                <p class="text-sm leading-relaxed text-stone-600">
                    Kami sudah mengirim kode 6 angka ke WhatsApp wali.
                    Masukkan kode tersebut, lalu buat password untuk masuk portal.
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <label for="pendaftaran" class="block text-sm font-medium text-stone-800">
                        Nomor pendaftaran
                    </label>
                    <input
                        id="pendaftaran"
                        v-model="form.pendaftaran"
                        type="text"
                        name="pendaftaran"
                        autocapitalize="characters"
                        autocorrect="off"
                        required
                        placeholder="Contoh: PSB-2026-0001"
                        class="block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/20"
                    >
                    <p class="text-xs text-stone-500">
                        Nomor ini tertera di pesan WhatsApp dan di layar setelah Anda mendaftar.
                    </p>
                    <p v-if="form.errors.pendaftaran" class="text-sm text-red-600">
                        {{ form.errors.pendaftaran }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="kode" class="block text-sm font-medium text-stone-800">
                        Kode dari WhatsApp (6 angka)
                    </label>
                    <input
                        id="kode"
                        v-model="form.kode"
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
                    <p v-if="form.errors.kode" class="text-sm text-red-600">
                        {{ form.errors.kode }}
                    </p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-stone-500">Belum menerima kode?</span>
                        <button
                            type="button"
                            :disabled="resendForm.processing || resendCooldown > 0 || !form.pendaftaran"
                            class="font-medium text-[var(--spmb-primary)] underline-offset-2 transition hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                            @click="resend"
                        >
                            <template v-if="resendCooldown > 0">
                                Kirim ulang ({{ resendCooldown }} dtk)
                            </template>
                            <template v-else-if="resendForm.processing">
                                Mengirim…
                            </template>
                            <template v-else>
                                Kirim ulang kode
                            </template>
                        </button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-stone-800">
                        Buat password (minimal 8 karakter)
                    </label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
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
                    <p v-if="form.errors.password" class="text-sm text-red-600">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block text-sm font-medium text-stone-800">
                        Ulangi password
                    </label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
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
                    :disabled="form.processing"
                    class="block w-full rounded-xl bg-[var(--spmb-primary)] px-4 py-3.5 text-center text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60"
                >
                    {{ form.processing ? 'Memproses…' : 'Aktifkan & Masuk' }}
                </button>
            </form>

            <p class="text-center text-sm text-stone-600">
                Sudah pernah aktivasi?
                <Link
                    href="/spmb/login"
                    class="font-medium text-[var(--spmb-primary)] underline-offset-2 hover:underline"
                >
                    Masuk di sini
                </Link>
            </p>
        </div>
    </SpmbLayout>
</template>
