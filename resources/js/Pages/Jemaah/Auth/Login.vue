<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import JemaahAuthLayout from '@/Layouts/JemaahAuthLayout.vue'

const form = useForm({
    identifier: '',
    password: '',
    remember: true,
})

const showPassword = ref(false)

const submit = () => {
    form.post('/jemaah/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Masuk Jemaah" />

    <JemaahAuthLayout>
        <div class="space-y-6">
            <div class="space-y-1.5">
                <h2 class="text-2xl font-bold tracking-tight">Masuk Jemaah</h2>
                <p class="text-sm leading-relaxed text-stone-600">
                    Masuk untuk ikut berinteraksi di beranda komunitas pesantren.
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <label for="identifier" class="block text-sm font-medium text-stone-800">
                        Nomor WhatsApp / email
                    </label>
                    <input
                        id="identifier"
                        v-model="form.identifier"
                        type="text"
                        name="identifier"
                        autocomplete="username"
                        required
                        placeholder="0812 3456 7890"
                        class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--jemaah-primary)] focus:ring-2 focus:ring-[var(--jemaah-primary)]/15"
                    >
                    <p v-if="form.errors.identifier" class="text-sm text-red-600">
                        {{ form.errors.identifier }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-stone-800">
                        Kata sandi
                    </label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                            class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 pr-20 text-base shadow-sm outline-none transition focus:border-[var(--jemaah-primary)] focus:ring-2 focus:ring-[var(--jemaah-primary)]/15"
                        >
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 px-4 text-sm font-medium text-stone-500"
                            @click="showPassword = !showPassword"
                        >
                            {{ showPassword ? 'Tutup' : 'Lihat' }}
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="text-sm text-red-600">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="text-right">
                    <Link href="/jemaah/lupa-password" class="text-sm font-medium text-[var(--jemaah-primary)] underline-offset-2 hover:underline">
                        Lupa kata sandi?
                    </Link>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="block w-full rounded-2xl bg-[var(--jemaah-primary)] px-4 py-3.5 text-center text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60"
                >
                    {{ form.processing ? 'Memproses…' : 'Masuk' }}
                </button>
            </form>

            <p class="text-center text-sm text-stone-600">
                Belum punya akun?
                <Link href="/jemaah/daftar" class="font-medium text-[var(--jemaah-primary)] underline-offset-2 hover:underline">
                    Daftar di sini
                </Link>
            </p>
        </div>
    </JemaahAuthLayout>
</template>
