<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({ nis: '', password: '' });

function submit() {
    form.post('/app/login');
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-b from-emerald-900 to-emerald-700 px-4">
        <div class="w-full max-w-sm rounded-3xl bg-white p-7 shadow-xl">
            <div class="text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-2xl text-white">🎓</div>
                <h1 class="mt-4 text-xl font-bold text-stone-900">App Santri</h1>
                <p class="mt-1 text-sm text-stone-500">Masuk dengan NIS dan password kamu.</p>
            </div>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="text-xs font-semibold text-stone-600">NIS</label>
                    <input
                        v-model="form.nis"
                        type="text"
                        inputmode="numeric"
                        autocomplete="username"
                        class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3 text-sm"
                        placeholder="Nomor Induk Santri"
                    >
                </div>
                <div>
                    <label class="text-xs font-semibold text-stone-600">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3 text-sm"
                        placeholder="••••••••"
                    >
                </div>
                <p v-if="form.errors.nis" class="text-sm text-red-600">{{ form.errors.nis }}</p>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-full bg-emerald-600 py-3 text-sm font-semibold text-white disabled:opacity-60"
                >
                    Masuk
                </button>
                <p class="text-center text-xs text-stone-400">
                    Lupa password? Hubungi ustadz/admin pesantren.
                </p>
            </form>
        </div>
    </div>
</template>
