<script setup>
// F1.2.2 — Halaman aktivasi akun: masukkan kode OTP dari WhatsApp lalu
// buat password. Nomor pendaftaran ter-prefill bila datang dari redirect
// formulir publik (?pendaftaran=…). Tersedia tombol kirim ulang kode
// dengan jeda 60 detik (rate limit per nomor tetap dijaga server).
import { onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';
import MdButton from '@/spmb/components/MdButton.vue';
import MdField from '@/spmb/components/MdField.vue';

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

    <SpmbLayout :bottom-nav="false" brand-header>
        <div class="space-y-4">
            <div class="px-1">
                <h1 class="text-2xl font-bold tracking-tight text-stone-900">
                    Aktifkan Akun Anda
                </h1>
                <p class="mt-1 text-[13px] leading-relaxed text-stone-600">
                    Kami sudah mengirim kode 6 angka ke WhatsApp wali.
                    Masukkan kode tersebut, lalu buat password untuk masuk portal.
                </p>
            </div>

            <MdCard>
                <form class="space-y-4" @submit.prevent="submit">
                    <MdField
                        v-model="form.pendaftaran"
                        type="text"
                        label="Nomor pendaftaran"
                        placeholder="Contoh: PSB-2026-0001"
                        required
                        help="Nomor ini tertera di pesan WhatsApp dan di layar setelah Anda mendaftar."
                        :error="form.errors.pendaftaran"
                    />

                    <div>
                        <span class="mb-1.5 block text-[13px] font-medium text-stone-700">
                            Kode dari WhatsApp (6 angka)<span class="text-red-500"> *</span>
                        </span>
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
                            class="block h-14 w-full rounded-xl border bg-white px-3.5 text-center text-2xl font-semibold tracking-[0.5em]
                                   text-stone-900 outline-none transition placeholder:tracking-normal placeholder:text-stone-400
                                   focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/25"
                            :class="form.errors.kode ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-300'"
                        >
                        <p v-if="form.errors.kode" class="mt-1 text-[12px] font-medium text-red-600">
                            {{ form.errors.kode }}
                        </p>
                        <div class="mt-2 flex items-center justify-between text-[13px]">
                            <span class="text-stone-500">Belum menerima kode?</span>
                            <button
                                type="button"
                                :disabled="resendForm.processing || resendCooldown > 0 || !form.pendaftaran"
                                class="font-semibold text-[var(--spmb-primary)] underline-offset-2 transition active:underline disabled:cursor-not-allowed disabled:opacity-50"
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

                    <div>
                        <span class="mb-1.5 block text-[13px] font-medium text-stone-700">
                            Buat password (minimal 8 karakter)<span class="text-red-500"> *</span>
                        </span>
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
                                class="block h-12 w-full rounded-xl border bg-white px-3.5 pr-20 text-[15px]
                                       text-stone-900 outline-none transition placeholder:text-stone-400
                                       focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/25"
                                :class="form.errors.password ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-300'"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 px-4 text-[13px] font-semibold text-stone-500 transition active:text-stone-800"
                                @click="showPassword = !showPassword"
                            >
                                {{ showPassword ? 'Tutup' : 'Lihat' }}
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-[12px] font-medium text-red-600">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <span class="mb-1.5 block text-[13px] font-medium text-stone-700">
                            Ulangi password<span class="text-red-500"> *</span>
                        </span>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
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

                    <MdButton type="submit" :loading="form.processing">
                        {{ form.processing ? 'Memproses…' : 'Aktifkan & Masuk' }}
                    </MdButton>
                </form>
            </MdCard>

            <p class="text-center text-[13px] text-stone-600">
                Sudah pernah aktivasi?
                <Link
                    href="/spmb/login"
                    class="font-semibold text-[var(--spmb-primary)] underline-offset-2 active:underline"
                >
                    Masuk di sini
                </Link>
            </p>
        </div>
    </SpmbLayout>
</template>
