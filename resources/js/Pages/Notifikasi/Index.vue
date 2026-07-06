<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell } from '@/Shared'
import EmptyState from '@/Components/Ui/EmptyState.vue'

/**
 * F4 — Riwayat notifikasi lintas-peran (/notifikasi). Generalisasi dari
 * PortalWali/Notifications (lihat PortalWaliNotificationController) agar
 * dipakai peran apa pun (guru, kasir, pimpinan, wali, pengelola_uks, dll).
 */
defineProps({
    notifications: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ sentCount: 0, failedCount: 0 }) },
})

const backTo = new URLSearchParams(window.location.search).get('from') || '/'

const statusTone = (status) => {
    if (status === 'sent') return 'text-emerald-700 bg-emerald-50'
    if (status === 'failed') return 'text-rose-700 bg-rose-50'
    return 'text-slate-600 bg-slate-100'
}
</script>

<template>
    <AppShell :nav-items="[]" brand-name="Notifikasi">
        <div class="space-y-5">
            <Head><title>Notifikasi</title></Head>

            <a
                :href="backTo"
                class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-slate-700"
            >
                &larr; Kembali
            </a>

            <section class="rounded-[2rem] bg-brand-grad-hero px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                    Riwayat Notifikasi
                </p>
                <div class="mt-3 flex gap-6">
                    <div>
                        <p class="font-jost text-2xl font-bold">{{ summary.sentCount }}</p>
                        <p class="text-xs text-white/60">Terkirim</p>
                    </div>
                    <div>
                        <p class="font-jost text-2xl font-bold">{{ summary.failedCount }}</p>
                        <p class="text-xs text-white/60">Gagal</p>
                    </div>
                </div>
            </section>

            <div v-if="notifications.length" class="grid gap-3">
                <article
                    v-for="item in notifications"
                    :key="item.id"
                    class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 text-sm shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900">{{ item.jenis || 'Notifikasi' }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ item.channel }} &middot; {{ item.target }}</p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="statusTone(item.status)"
                        >
                            {{ item.status }}
                        </span>
                    </div>
                    <p v-if="item.errorMessage" class="mt-2 text-xs text-rose-600">
                        {{ item.errorMessage }}
                    </p>
                    <p class="mt-2 text-xs text-slate-400">{{ item.createdAt }}</p>
                </article>
            </div>

            <EmptyState
                v-else
                icon="bell"
                title="Belum ada notifikasi"
                message="Riwayat notifikasi Anda akan muncul di sini."
            />
        </div>
    </AppShell>
</template>
