<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import JemaahAuthLayout from '@/Layouts/JemaahAuthLayout.vue'

const form = useForm({
    name: '',
    phone: '',
    email: '',
})

const submit = () => {
    form.post('/jemaah/daftar')
}
</script>

<template>
    <Head title="Daftar Jemaah" />

    <JemaahAuthLayout>
        <div class="space-y-6">
            <div class="space-y-1.5">
                <h2 class="text-2xl font-bold tracking-tight">Daftar Jemaah</h2>
                <p class="text-sm leading-relaxed text-stone-600">
                    Isi data singkat, lalu kami kirim kode 6 angka ke WhatsApp Anda untuk mengaktifkan akun.
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <label for="name" class="block text-sm font-medium text-stone-800">
                        Nama lengkap
                    </label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        name="name"
                        autocomplete="name"
                        required
                        placeholder="Nama Anda"
                        class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--jemaah-primary)] focus:ring-2 focus:ring-[var(--jemaah-primary)]/15"
                    >
                    <p v-if="form.errors.name" class="text-sm text-red-600">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="phone" class="block text-sm font-medium text-stone-800">
                        Nomor WhatsApp
                    </label>
                    <input
                        id="phone"
                        v-model="form.phone"
                        type="tel"
                        name="phone"
                        inputmode="numeric"
                        autocomplete="tel"
                        required
                        placeholder="0812 3456 7890"
                        class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--jemaah-primary)] focus:ring-2 focus:ring-[var(--jemaah-primary)]/15"
                    >
                    <p class="text-xs text-stone-500">
                        Pakai nomor WhatsApp aktif — kode aktivasi dikirim ke sini.
                    </p>
                    <p v-if="form.errors.phone" class="text-sm text-red-600">
                        {{ form.errors.phone }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="email" class="block text-sm font-medium text-stone-800">
                        Email <span class="font-normal text-stone-400">(opsional)</span>
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        placeholder="email@contoh.com"
                        class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--jemaah-primary)] focus:ring-2 focus:ring-[var(--jemaah-primary)]/15"
                    >
                    <p v-if="form.errors.email" class="text-sm text-red-600">
                        {{ form.errors.email }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="block w-full rounded-full bg-[var(--jemaah-primary)] px-4 py-3.5 text-center text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60"
                >
                    {{ form.processing ? 'Memproses…' : 'Kirim Kode Aktivasi' }}
                </button>
            </form>

            <p class="text-center text-sm text-stone-600">
                Sudah punya akun?
                <Link href="/jemaah/login" class="font-medium text-[var(--jemaah-primary)] underline-offset-2 hover:underline">
                    Masuk di sini
                </Link>
            </p>
        </div>
    </JemaahAuthLayout>
</template>
