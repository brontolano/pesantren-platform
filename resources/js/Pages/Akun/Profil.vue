<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
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
    vapidPublicKey: { type: String, default: '' },
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

// F6 — Web Push (VAPID) opt-in. Kanal PELENGKAP: WA tetap kanal utama.
// Degradasi anggun: tombol disembunyikan bila browser tak mendukung
// PushManager (mis. iOS belum di-install) atau kunci VAPID belum diset.
const pushSupported = typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && !!props.vapidPublicKey
const pushStatus = ref('idle') // idle | loading | active | error | denied

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
    const rawData = window.atob(base64)
    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)))
}

async function aktifkanPush() {
    if (!pushSupported) return
    pushStatus.value = 'loading'

    try {
        const permission = await Notification.requestPermission()
        if (permission !== 'granted') {
            pushStatus.value = 'denied'
            return
        }

        const registration = await navigator.serviceWorker.ready
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(props.vapidPublicKey),
        })

        await fetch('/push/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(subscription.toJSON()),
        })

        pushStatus.value = 'active'
    } catch (error) {
        pushStatus.value = 'error'
    }
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

            <section class="rounded-[2rem] bg-brand-grad-hero px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
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

            <section v-if="pushSupported || pushStatus === 'active'" class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-5 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                    Notifikasi
                </p>
                <h3 class="mt-2 font-jost text-lg text-slate-900">Notifikasi Push</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Kanal pelengkap — WhatsApp tetap kanal utama pemberitahuan.
                </p>

                <button
                    v-if="pushSupported && pushStatus !== 'active'"
                    type="button"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-60"
                    :disabled="pushStatus === 'loading'"
                    @click="aktifkanPush"
                >
                    {{ pushStatus === 'loading' ? 'Mengaktifkan…' : 'Aktifkan Notifikasi Push' }}
                </button>
                <p v-if="pushStatus === 'active'" class="mt-4 rounded-xl bg-emerald-600/10 px-3 py-2 text-sm font-medium text-emerald-800">
                    Notifikasi push aktif di perangkat ini.
                </p>
                <p v-else-if="pushStatus === 'denied'" class="mt-4 text-sm text-slate-500">
                    Izin notifikasi ditolak browser. Aktifkan lewat pengaturan browser bila ingin mencoba lagi.
                </p>
                <p v-else-if="pushStatus === 'error'" class="mt-4 text-sm text-rose-600">
                    Gagal mengaktifkan notifikasi push. Coba lagi nanti.
                </p>
            </section>
        </div>
    </AppShell>
</template>
