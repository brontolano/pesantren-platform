<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import PortalWaliAuthLayout from '@/Layouts/PortalWaliAuthLayout.vue'

const form = useForm({
    identifier: '',
    password: '',
    remember: true,
})

const showPassword = ref(false)

const submit = () => {
    form.post('/wali/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Masuk Portal Wali" />

    <PortalWaliAuthLayout>
        <div class="space-y-6">
            <div class="space-y-1.5">
                <h2 class="text-2xl font-bold tracking-tight">Masuk Portal Wali</h2>
                <p class="text-sm leading-relaxed text-stone-600">
                    Pantau tagihan, tabungan, dan status pembayaran anak dari satu akun wali.
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <label for="identifier" class="block text-sm font-medium text-stone-800">
                        Nomor WhatsApp / email wali
                    </label>
                    <input
                        id="identifier"
                        v-model="form.identifier"
                        type="text"
                        name="identifier"
                        autocomplete="username"
                        required
                        placeholder="Contoh: 0812xxxx atau email wali"
                        class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--wali-primary)] focus:ring-2 focus:ring-[var(--wali-primary)]/15"
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
                            placeholder="Password akun wali"
                            class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 pr-20 text-base shadow-sm outline-none transition focus:border-[var(--wali-primary)] focus:ring-2 focus:ring-[var(--wali-primary)]/15"
                        >
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 px-4 text-sm font-medium text-stone-500"
                            @click="showPassword = !showPassword"
                        >
                            {{ showPassword ? 'Tutup' : 'Lihat' }}
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            name="remember"
                            class="h-4 w-4 rounded border-stone-300 text-[var(--wali-primary)] focus:ring-[var(--wali-primary)]"
                        >
                        Tetap masuk
                    </label>
                    <Link href="/wali/lupa-password" class="text-sm font-medium text-[var(--wali-primary)] underline-offset-2 hover:underline">
                        Lupa password?
                    </Link>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="block w-full rounded-2xl bg-[var(--wali-primary)] px-4 py-3.5 text-center text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60"
                >
                    {{ form.processing ? 'Memproses…' : 'Masuk' }}
                </button>
            </form>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-stone-700">
                <p class="font-semibold text-stone-900">Belum pernah masuk?</p>
                <p class="mt-1 leading-relaxed">
                    Aktifkan dulu akun wali dengan kode WhatsApp.
                    <Link href="/wali/aktivasi" class="font-medium text-[var(--wali-primary)] underline-offset-2 hover:underline">
                        Aktifkan akun di sini
                    </Link>.
                </p>
            </div>
        </div>
    </PortalWaliAuthLayout>
</template>
