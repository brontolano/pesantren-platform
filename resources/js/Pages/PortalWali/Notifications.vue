<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

defineOptions({
    layout: PortalWaliLayout,
})

defineProps({
    notifications: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Notifikasi Wali</title>
        </Head>

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Notifikasi
            </p>
            <h2 class="mt-3 font-serif text-3xl leading-tight">
                Pusat Informasi
            </h2>
            <p class="mt-2 text-sm leading-6 text-white/75">
                Ringkasan pesan email dan WA untuk wali.
            </p>
        </section>

        <section class="grid grid-cols-2 gap-3">
            <div class="rounded-[1.5rem] bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                    Terkirim
                </p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ summary.sentCount }}
                </p>
            </div>
            <div class="rounded-[1.5rem] bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                    Gagal
                </p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ summary.failedCount }}
                </p>
            </div>
        </section>

        <div class="grid gap-3">
            <article
                v-for="notification in notifications"
                :key="notification.id"
                class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            {{ notification.channel }} · {{ notification.jenis }}
                        </p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">
                            {{ notification.target }}
                        </h3>
                    </div>
                    <div class="rounded-full bg-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-700">
                        {{ notification.status }}
                    </div>
                </div>
                <p class="mt-3 text-sm text-slate-500">
                    {{ notification.createdAt || '-' }}
                </p>
            </article>
        </div>

        <Link
            href="/wali/izin"
            class="block rounded-[1.5rem] bg-amber-50 px-4 py-4 text-center text-sm font-semibold uppercase tracking-[0.18em] text-amber-700"
        >
            Buka pengajuan izin
        </Link>
    </div>
</template>
