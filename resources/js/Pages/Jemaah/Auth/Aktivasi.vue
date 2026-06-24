<script setup>
import { onBeforeUnmount, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import JemaahAuthLayout from '@/Layouts/JemaahAuthLayout.vue'

const props = defineProps({
    identifier: {
        type: String,
        default: '',
    },
})

const form = useForm({
    identifier: props.identifier,
    kode: '',
    password: '',
    password_confirmation: '',
})

const resendForm = useForm({
    identifier: props.identifier,
})

const showPassword = ref(false)
const resendCooldown = ref(0)
let cooldownTimer = null

const startCooldown = () => {
    resendCooldown.value = 60
    cooldownTimer = setInterval(() => {
        resendCooldown.value -= 1
        if (resendCooldown.value <= 0) {
            clearInterval(cooldownTimer)
        }
    }, 1000)
}

onBeforeUnmount(() => {
    if (cooldownTimer) {
        clearInterval(cooldownTimer)
    }
})

const submit = () => {
    form.post('/jemaah/aktivasi', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}

const resend = () => {
    resendForm.identifier = form.identifier
    resendForm.post('/jemaah/aktivasi/kirim-ulang', {
        preserveScroll: true,
        onSuccess: () => startCooldown(),
    })
}
</script>

<template>
    <Head title="Aktivasi Jemaah" />

    <JemaahAuthLayout>
        <div class="space-y-6">
            <div class="space-y-1.5">
                <h2 class="text-2xl font-bold tracking-tight">Aktivasi Akun</h2>
                <p class="text-sm leading-relaxed text-stone-600">
                    Masukkan kode 6 angka yang dikirim ke WhatsApp Anda, lalu buat password baru.
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
                        placeholder="Nomor WA atau email yang dipakai daftar"
                        class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--jemaah-primary)] focus:ring-2 focus:ring-[var(--jemaah-primary)]/15"
                    >
                    <p v-if="form.errors.identifier" class="text-sm text-red-600">
                        {{ form.errors.identifier }}
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
                        class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-center text-2xl font-semibold tracking-[0.5em] shadow-sm outline-none transition placeholder:tracking-normal focus:border-[var(--jemaah-primary)] focus:ring-2 focus:ring-[var(--jemaah-primary)]/15"
                    >
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-stone-500">Belum menerima kode?</span>
                        <button
                            type="button"
                            :disabled="resendForm.processing || resendCooldown > 0 || !form.identifier"
                            class="font-medium text-[var(--jemaah-primary)] underline-offset-2 hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                            @click="resend"
                        >
                            <template v-if="resendCooldown > 0">Kirim ulang ({{ resendCooldown }} dtk)</template>
                            <template v-else-if="resendForm.processing">Mengirim…</template>
                            <template v-else>Kirim ulang kode</template>
                        </button>
                    </div>
                    <p v-if="form.errors.kode" class="text-sm text-red-600">
                        {{ form.errors.kode }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-stone-800">
                        Buat password baru
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
                            placeholder="Minimal 8 karakter"
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
                        class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-[var(--jemaah-primary)] focus:ring-2 focus:ring-[var(--jemaah-primary)]/15"
                    >
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="block w-full rounded-2xl bg-[var(--jemaah-primary)] px-4 py-3.5 text-center text-base font-semibold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60"
                >
                    {{ form.processing ? 'Memproses…' : 'Aktifkan & Masuk' }}
                </button>
            </form>

            <p class="text-center text-sm text-stone-600">
                Sudah aktif?
                <Link href="/jemaah/login" class="font-medium text-[var(--jemaah-primary)] underline-offset-2 hover:underline">
                    Masuk di sini
                </Link>
            </p>
        </div>
    </JemaahAuthLayout>
</template>
