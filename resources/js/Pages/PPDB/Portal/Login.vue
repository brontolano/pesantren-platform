<script setup>
// F1.2.3 — Halaman masuk Portal SPMB.
// Urutan prioritas UX:
//   1. Aktivasi Akun (user baru wajib ini dulu setelah daftar)
//   2. Login form (user yang sudah aktif)
//   3. Formulir Pendaftaran /ppdb (belum pernah daftar sama sekali)
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';

const form = useForm({
    identifier: '',
    password:   '',
    remember:   true,
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
        <div class="space-y-5">

            <!-- Judul -->
            <div class="space-y-1">
                <h1 class="text-2xl font-bold tracking-tight">
                    Portal Peserta SPMB
                </h1>
                <p class="text-sm leading-relaxed text-stone-600">
                    Pantau status pendaftaran, lengkapi berkas, dan ikuti tahapan seleksi.
                </p>
            </div>

            <!-- ══════════════════════════════════════════════
                 1. AKTIVASI AKUN — PRIORITAS UTAMA
                 User baru wajib aktivasi dulu (kode dari WhatsApp)
                 sebelum bisa login. Ini langkah PERTAMA.
                 ══════════════════════════════════════════════ -->
            <Link
                href="/spmb/aktivasi"
                class="block rounded-2xl bg-[var(--spmb-primary)] px-5 py-4 text-white shadow-md
                       transition hover:brightness-105 active:scale-[0.99]"
            >
                <div class="flex items-center gap-4">
                    <!-- Ikon centang -->
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center
                                rounded-xl bg-white/20">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none"
                             stroke="currentColor" stroke-width="2.2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>

                    <div class="flex-1">
                        <p class="text-base font-bold leading-tight">Aktivasi Akun</p>
                        <p class="mt-0.5 text-sm text-white/80">
                            Baru mendaftar? Masukkan kode dari WhatsApp &amp; buat password
                        </p>
                    </div>

                    <!-- Panah kanan -->
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-white/60"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </div>
            </Link>

            <!-- ══════════════════════════════════════════════
                 2. LOGIN — untuk yang sudah aktif
                 ══════════════════════════════════════════════ -->
            <div class="flex items-center gap-3 text-xs text-stone-400">
                <span class="h-px flex-1 bg-stone-200"></span>
                sudah punya password? masuk di sini
                <span class="h-px flex-1 bg-stone-200"></span>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <label for="identifier"
                           class="block text-sm font-medium text-stone-800">
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
                        class="block w-full rounded-xl border border-stone-300 bg-white px-4 py-3
                               text-base shadow-sm outline-none transition
                               focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/20"
                    >
                    <p v-if="form.errors.identifier" class="text-sm text-red-600">
                        {{ form.errors.identifier }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="password"
                           class="block text-sm font-medium text-stone-800">
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
                            class="block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 pr-20
                                   text-base shadow-sm outline-none transition
                                   focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/20"
                        >
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 px-4 text-sm font-medium
                                   text-stone-500 transition hover:text-stone-800"
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
                            class="h-4 w-4 rounded border-stone-300
                                   text-[var(--spmb-primary)] focus:ring-[var(--spmb-primary)]"
                        >
                        Tetap masuk
                    </label>
                    <Link
                        href="/spmb/lupa-password"
                        class="text-sm font-medium text-[var(--spmb-primary)]
                               underline-offset-2 hover:underline"
                    >
                        Lupa password?
                    </Link>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="block w-full rounded-xl border border-[var(--spmb-primary)] bg-white
                           px-4 py-3.5 text-center text-base font-semibold text-[var(--spmb-primary)]
                           shadow-sm transition hover:bg-[var(--spmb-primary)]/5
                           active:scale-[0.99] disabled:opacity-60"
                >
                    {{ form.processing ? 'Memproses…' : 'Masuk' }}
                </button>
            </form>

            <!-- ══════════════════════════════════════════════
                 3. DAFTAR — belum pernah mendaftar sama sekali
                 → arahkan ke /ppdb (formulir pendaftaran publik)
                 ══════════════════════════════════════════════ -->
            <div class="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3.5">
                <p class="text-sm text-stone-600">
                    Belum pernah mendaftar?
                </p>
                <Link
                    href="/ppdb"
                    class="mt-2 flex w-full items-center justify-center gap-2 rounded-xl
                           border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold
                           text-stone-700 shadow-sm transition hover:border-[var(--spmb-primary)]/50
                           hover:text-[var(--spmb-primary)] active:scale-[0.99]"
                >
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Isi Formulir Pendaftaran
                </Link>
            </div>

        </div>
    </SpmbLayout>
</template>
