<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell } from '@/Shared'
import UiField from '@/Components/Ui/UiField.vue'
import UiInput from '@/Components/Ui/UiInput.vue'
import UiButton from '@/Components/Ui/UiButton.vue'

/**
 * F4 — Halaman akun lintas-peran (/akun): profil + ubah password.
 * Bisa diakses siapa pun user tenant yang login. Layout AppShell netral
 * (tanpa bottom-nav peran tertentu) + dukungan back-link via ?from=.
 */
const props = defineProps({
    user: { type: Object, required: true },
})

const page = usePage()
const flashSuccess = computed(() => page.props?.flash?.success || '')
const backTo = computed(() => new URLSearchParams(window.location.search).get('from') || '/')

const initials = (name) => String(name || '?').charAt(0).toUpperCase()

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

function simpanPassword() {
    passwordForm.post('/akun/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}
</script>

<template>
    <AppShell :nav-items="[]" brand-name="Akun Saya">
        <div class="space-y-5">
            <Head><title>Akun Saya</title></Head>

            <a
                :href="backTo"
                class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-slate-700"
            >
                &larr; Kembali
            </a>

            <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-white/10 font-jost text-2xl font-bold">
                        {{ initials(user.name) }}
                    </div>
                    <div>
                        <p class="font-jost text-xl font-bold">{{ user.name }}</p>
                        <p class="mt-1 text-sm text-white/70">{{ user.email }}</p>
                        <p v-if="user.phone" class="mt-0.5 text-sm text-white/70">{{ user.phone }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-5 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                    Keamanan
                </p>
                <h3 class="mt-2 font-jost text-lg text-slate-900">Ubah Kata Sandi</h3>

                <p v-if="flashSuccess" class="mt-3 rounded-xl bg-emerald-600/10 px-3 py-2 text-sm font-medium text-emerald-800">
                    {{ flashSuccess }}
                </p>

                <form class="mt-4 grid gap-4" @submit.prevent="simpanPassword">
                    <UiField label="Kata sandi saat ini" :error="passwordForm.errors.current_password" required>
                        <template #default="{ fieldId, describedby, invalid }">
                            <UiInput
                                :id="fieldId"
                                v-model="passwordForm.current_password"
                                type="password"
                                :invalid="invalid"
                                :aria-describedby="describedby"
                            />
                        </template>
                    </UiField>

                    <UiField label="Kata sandi baru" :error="passwordForm.errors.password" hint="Minimal 8 karakter" required>
                        <template #default="{ fieldId, describedby, invalid }">
                            <UiInput
                                :id="fieldId"
                                v-model="passwordForm.password"
                                type="password"
                                :invalid="invalid"
                                :aria-describedby="describedby"
                            />
                        </template>
                    </UiField>

                    <UiField label="Konfirmasi kata sandi baru" required>
                        <template #default="{ fieldId, describedby }">
                            <UiInput
                                :id="fieldId"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                :aria-describedby="describedby"
                            />
                        </template>
                    </UiField>

                    <UiButton type="submit" block :loading="passwordForm.processing">
                        Simpan Kata Sandi
                    </UiButton>
                </form>
            </section>
        </div>
    </AppShell>
</template>
