<script setup>
// F1.2.3 — Halaman masuk Portal SPMB. Bahasa sederhana untuk wali/peserta
// awam; identifier bebas: nomor pendaftaran / No. HP / email.
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';

const form = useForm({
    identifier: '',
    password: '',
    remember: true,
});

const showPassword = ref(false);

const submit = () => {
    form.post('/spmb/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Masuk Portal SPMB" />

    <SpmbLayout>
        <div class="space-y-6">
            <div class="space-y-1.5">
                <h1 class="text-2xl font-bold tracking-tight">
                    Masuk Portal SPMB
                </h1>
                <p class="text-sm leading-relaxed text-stone-600">
                    Pantau status pendaftaran, lengkapi data, dan ikuti tahapan seleksi dari HP Anda.
                </p>
            </div>

            <!-- Calon santri baru WAJIB daftar dulu untuk dapat akun -->
            <div class="rounded-2xl border-2 border-[var(--spmb-primary)]/30 bg-[var(--spmb-primary)]/5 p-4">
                <p class="text-sm font-semibold text-stone-800">Belum punya akun?</p>
                <p class="mt-1 text-sm text-stone-600">
                    Calon santri baru wajib mendaftar dulu untuk mendapatkan akun portal.
                </p>
                <Link
                    href="/ppdb"
                    class="mt-3 block w-full rounded-xl bg-[var(--spmb-primary)] px-4 py-3 text-center text-base font-semibold text-white shadow-sm transition active:scale-[0.99]"
                >
                    Daftar Sekarang
                </Link>
            </div>

            <div class="flex items-center gap-3 text-xs text-stone-400">
                <span class="h-px flex-1 bg-stone-200"></span>
                sudah punya akun? masuk di bawah
                <span class="h-px flex-1 bg-stone-200"></span>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <label for="identifier" class="block text-sm font-medium text-stone-800">
                        Nomor pendaftaran / No. HP / email
                    </label>
                    <input
                        id="identifier"
                        v-model="form.identifier"
                        type="text"
                        name="identifier"
                        autocomplete="username"
                        autocapitalize="none"
                        autocorrect="off"
                        required
                        placeholder="Contoh: PSB-2026-0001 atau 0812…"
                        class="block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/20"
                    >
                    <p v-if="form.errors.identifier" class="text-sm text-red-600">
                        {{ form.errors.identifier }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-stone-800">
                        Password
                    </label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            autocomplete="current-password"
                            required
                            placeholder="Password Anda"
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

                <div class="flex items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            name="remember"
                            class="h-4 w-4 rounded border-stone-300 text-[var(--spmb-primary)] focus:ring-[var(--spmb-primary)]"
                        >
                        Tetap masuk
                    </label>
                    <Link
                        href="/spmb/lupa-password"
                        class="text-sm font-medium text-[var(--spmb-primary)] underline-offset-2 hover:underline"
                    >
                        Lupa password?
                    </Link>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="block w-full rounded-xl bg-[var(--spmb-primary)] px-4 py-3.5 text-center text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60"
                >
                    {{ form.processing ? 'Memproses…' : 'Masuk' }}
                </button>
            </form>

            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm leading-relaxed text-stone-600">
                <p class="font-medium text-stone-800">
                    Baru selesai mendaftar?
                </p>
                <p class="mt-1">
                    Akun Anda perlu diaktifkan dulu dengan kode dari WhatsApp.
                    <Link
                        href="/spmb/aktivasi"
                        class="font-medium text-[var(--spmb-primary)] underline-offset-2 hover:underline"
                    >
                        Aktifkan akun di sini
                    </Link>.
                </p>
            </div>
        </div>
    </SpmbLayout>
</template>
