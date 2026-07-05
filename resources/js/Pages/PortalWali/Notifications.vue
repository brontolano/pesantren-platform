<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import UiIcon from '@/Components/Ui/UiIcon.vue'

defineOptions({ layout: PortalWaliLayout })

const props = defineProps({
    wali: { type: Object, required: true },
    children: { type: Array, default: () => [] },
    notifications: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
    vapidPublicKey: { type: String, default: '' },
})

const initials = (name) => String(name || '?').charAt(0).toUpperCase()

function logout() {
    router.post('/wali/logout')
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
    <div class="space-y-4">
        <Head><title>Akun</title></Head>

        <!-- Profile header -->
        <div class="flex flex-col items-center gap-[10px] pt-2 text-center">
            <div class="flex h-[72px] w-[72px] items-center justify-center rounded-full bg-violet-50 font-jost text-[28px] font-bold text-violet-700 shadow-[inset_0_0_0_1px_#efe7da]">
                {{ initials(wali.name) }}
            </div>
            <div>
                <p class="font-jost text-xl font-bold text-[#111]">{{ wali.name }}</p>
                <p class="mt-[3px] text-[13px] text-slate-500">{{ wali.phone || wali.email || '-' }}</p>
            </div>
            <span v-if="children.length" class="inline-flex items-center gap-1.5 rounded-full bg-[#f7efdc] px-[14px] py-[5px] font-jost text-xs font-semibold text-[#a07c2c]">
                Wali dari {{ children.length }} santri
            </span>
        </div>

        <!-- Santri list -->
        <div class="space-y-[9px]">
            <p class="font-jost text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Santri Saya</p>
            <Link
                v-for="child in children"
                :key="child.id"
                :href="`/wali/santri/${child.id}`"
                class="flex items-center gap-3 rounded-[20px] bg-white px-4 py-[13px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)] transition active:scale-[.99]"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-violet-50 font-jost text-base font-bold text-violet-700">
                    {{ initials(child.nama) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-[#111]">{{ child.nama }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ child.kelas || 'Belum ada kelas' }}</p>
                </div>
                <UiIcon name="chevron-right" class="h-[15px] w-[15px] shrink-0 text-slate-400" />
            </Link>
        </div>

        <!-- Settings -->
        <div class="space-y-[9px]">
            <p class="font-jost text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Pengaturan</p>
            <div class="rounded-[22px] bg-white px-[17px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
                <!-- F6 — Web Push opt-in (pelengkap; WA tetap kanal utama) -->
                <button
                    v-if="pushSupported && pushStatus !== 'active'"
                    type="button"
                    class="flex w-full items-center gap-3 border-b border-[#f6f0e6] py-3 text-left"
                    :disabled="pushStatus === 'loading'"
                    @click="aktifkanPush"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
                        <UiIcon name="bell" class="h-[18px] w-[18px]" />
                    </span>
                    <span class="flex-1 text-[13.5px] font-semibold text-slate-800">
                        {{ pushStatus === 'loading' ? 'Mengaktifkan…' : 'Aktifkan Notifikasi Push' }}
                    </span>
                </button>
                <p v-if="pushStatus === 'active'" class="border-b border-[#f6f0e6] py-3 text-[13px] text-emerald-700">
                    Notifikasi push aktif di perangkat ini.
                </p>
                <p v-else-if="pushStatus === 'denied'" class="border-b border-[#f6f0e6] py-3 text-[13px] text-slate-500">
                    Izin notifikasi ditolak browser. Aktifkan lewat pengaturan browser bila ingin mencoba lagi.
                </p>
                <p v-else-if="pushStatus === 'error'" class="border-b border-[#f6f0e6] py-3 text-[13px] text-rose-600">
                    Gagal mengaktifkan notifikasi push. Coba lagi nanti.
                </p>
                <Link href="/wali/notifikasi" class="flex items-center gap-3 border-b border-[#f6f0e6] py-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <UiIcon name="bell" class="h-[18px] w-[18px]" />
                    </span>
                    <span class="flex-1 text-[13.5px] font-semibold text-slate-800">Notifikasi</span>
                    <UiIcon name="chevron-right" class="h-[15px] w-[15px] shrink-0 text-slate-400" />
                </Link>
                <Link href="/wali/pembayaran-saya" class="flex items-center gap-3 border-b border-[#f6f0e6] py-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <UiIcon name="document" class="h-[18px] w-[18px]" />
                    </span>
                    <span class="flex-1 text-[13.5px] font-semibold text-slate-800">Riwayat pembayaran</span>
                    <UiIcon name="chevron-right" class="h-[15px] w-[15px] shrink-0 text-slate-400" />
                </Link>
                <div class="flex items-center gap-3 border-b border-[#f6f0e6] py-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <UiIcon name="info" class="h-[18px] w-[18px]" />
                    </span>
                    <span class="flex-1 text-[13.5px] font-semibold text-slate-800">Bantuan via WhatsApp</span>
                    <UiIcon name="chevron-right" class="h-[15px] w-[15px] shrink-0 text-slate-400" />
                </div>
                <button class="flex w-full items-center gap-3 py-3 text-left" @click="logout">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                        <UiIcon name="arrow-left" class="h-[18px] w-[18px]" />
                    </span>
                    <span class="flex-1 text-[13.5px] font-semibold text-rose-600">Keluar</span>
                </button>
            </div>
        </div>
    </div>
</template>
