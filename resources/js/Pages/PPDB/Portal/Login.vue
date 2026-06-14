<script setup>
// F1.2.3 — Halaman masuk Portal SPMB.
// Urutan prioritas UX:
//   1. Aktivasi Akun (user baru wajib ini dulu setelah daftar)
//   2. Login form (user yang sudah aktif)
//   3. Formulir Pendaftaran /ppdb (belum pernah daftar sama sekali)
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';
import MdButton from '@/spmb/components/MdButton.vue';
import MdField from '@/spmb/components/MdField.vue';

// ppdbUrl: tujuan "Isi Formulir Pendaftaran". Default form internal /ppdb;
// tenant dgn pendaftaran di website eksternal (Setting ppdb_external_url)
// mendapat URL absolut → pakai <a> biasa (bukan Inertia <Link>).
defineProps({
    ppdbUrl: { type: String, default: '/ppdb' },
});

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

    <SpmbLayout :bottom-nav="false" brand-header>
        <div class="space-y-4">

            <!-- Judul -->
            <div class="px-1">
                <h1 class="text-2xl font-bold tracking-tight text-stone-900">
                    Portal Peserta SPMB
                </h1>
                <p class="mt-1 text-[13px] leading-relaxed text-stone-600">
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
                class="block rounded-2xl bg-[var(--spmb-primary)] px-4 py-3.5 text-white shadow-sm
                       transition active:brightness-95 active:scale-[0.99]"
            >
                <div class="flex items-center gap-3">
                    <!-- Ikon centang -->
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center
                                rounded-xl bg-white/20">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none"
                             stroke="currentColor" stroke-width="2.2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-[15px] font-bold leading-tight">Aktivasi Akun</p>
                        <p class="mt-0.5 text-[12px] leading-relaxed text-white/80">
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
            <div class="flex items-center gap-3 px-1 text-[12px] text-stone-400">
                <span class="h-px flex-1 bg-stone-200"></span>
                sudah punya password? masuk di sini
                <span class="h-px flex-1 bg-stone-200"></span>
            </div>

            <MdCard>
                <form class="space-y-4" @submit.prevent="submit">
                    <MdField
                        v-model="form.identifier"
                        type="text"
                        label="Nomor pendaftaran / No. HP / email"
                        placeholder="Contoh: PSB-2026-0001 atau 0812…"
                        required
                        :error="form.errors.identifier"
                    />

                    <div>
                        <span class="mb-1.5 block text-[13px] font-medium text-stone-700">
                            Password<span class="text-red-500"> *</span>
                        </span>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                autocomplete="current-password"
                                required
                                placeholder="Password Anda"
                                class="block h-12 w-full rounded-xl border bg-white px-3.5 pr-20
                                       text-[15px] text-stone-900 outline-none transition placeholder:text-stone-400
                                       focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/25"
                                :class="form.errors.password ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-300'"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 px-4 text-[13px] font-semibold
                                       text-stone-500 transition active:text-stone-800"
                                @click="showPassword = !showPassword"
                            >
                                {{ showPassword ? 'Tutup' : 'Lihat' }}
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-[12px] font-medium text-red-600">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <label class="flex items-center gap-2 text-[13px] text-stone-700">
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
                            class="text-[13px] font-semibold text-[var(--spmb-primary)]
                                   underline-offset-2 active:underline"
                        >
                            Lupa password?
                        </Link>
                    </div>

                    <MdButton type="submit" variant="tonal" :loading="form.processing">
                        {{ form.processing ? 'Memproses…' : 'Masuk' }}
                    </MdButton>
                </form>
            </MdCard>

            <!-- ══════════════════════════════════════════════
                 3. DAFTAR — belum pernah mendaftar sama sekali
                 → arahkan ke /ppdb (formulir pendaftaran publik)
                 ══════════════════════════════════════════════ -->
            <div class="rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3.5">
                <p class="text-[13px] text-stone-600">
                    Belum pernah mendaftar?
                </p>
                <a
                    :href="ppdbUrl"
                    class="mt-2 flex h-12 w-full items-center justify-center gap-2 rounded-xl
                           border border-stone-300 bg-white px-4 text-[14px] font-semibold
                           text-stone-700 shadow-sm transition active:scale-[0.99]
                           active:border-[var(--spmb-primary)]/50 active:text-[var(--spmb-primary)]"
                >
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Isi Formulir Pendaftaran
                </a>
            </div>

        </div>
    </SpmbLayout>
</template>
