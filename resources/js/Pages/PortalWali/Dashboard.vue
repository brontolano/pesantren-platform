<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    wali: {
        type: Object,
        required: true,
    },
    children: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        required: true,
    },
})

const todayLabel = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date())
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Dashboard Wali</title>
        </Head>

        <section class="overflow-hidden rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Assalamu alaikum
            </p>
            <h2 class="mt-3 font-serif text-3xl leading-tight">
                {{ wali.name }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-white/75">
                {{ todayLabel }}
            </p>

            <div class="mt-5 grid grid-cols-2 gap-3">
                <div class="rounded-[1.5rem] bg-white/8 px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/55">
                        Anak Terhubung
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ summary.childrenCount }}
                    </p>
                </div>
                <div class="rounded-[1.5rem] bg-white/8 px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/55">
                        Status Aktif
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ summary.activeChildrenCount }}
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex items-end justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                        Akses Anak
                    </p>
                    <h3 class="mt-1 font-serif text-2xl text-slate-900">
                        Ringkasan Santri
                    </h3>
                </div>
            </div>

            <div
                v-if="children.length"
                class="grid gap-3"
            >
                <article
                    v-for="child in children"
                    :key="child.id"
                    class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">
                                {{ child.hubungan }}
                            </p>
                            <h4 class="mt-2 font-serif text-2xl text-slate-900">
                                {{ child.nama }}
                            </h4>
                            <p class="mt-1 text-sm text-slate-500">
                                NIS {{ child.nis }}
                            </p>
                        </div>
                        <div
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="child.status === 'aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                        >
                            {{ child.status }}
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2 rounded-[1.25rem] bg-slate-50 px-4 py-4 text-sm text-slate-600">
                        <p>
                            <span class="font-semibold text-slate-900">Kelas:</span>
                            {{ child.kelas?.nama || 'Belum ditempatkan' }}
                        </p>
                        <p>
                            <span class="font-semibold text-slate-900">Tingkat:</span>
                            {{ child.kelas?.tingkat || '-' }}
                        </p>
                        <p v-if="child.isPrimary" class="font-semibold text-emerald-700">
                            Akses utama wali
                        </p>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-xs font-semibold uppercase tracking-[0.16em]">
                        <Link
                            :href="`/wali/santri/${child.id}`"
                            class="rounded-2xl bg-slate-900 px-3 py-3 text-center text-white"
                        >
                            Profil
                        </Link>
                        <Link
                            :href="`/wali/santri/${child.id}/akademik`"
                            class="rounded-2xl bg-emerald-50 px-3 py-3 text-center text-emerald-700"
                        >
                            Akademik
                        </Link>
                        <Link
                            :href="`/wali/santri/${child.id}/keuangan`"
                            class="rounded-2xl bg-amber-50 px-3 py-3 text-center text-amber-700"
                        >
                            Keuangan
                        </Link>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-[1.75rem] border border-dashed border-slate-300 bg-white px-4 py-6 text-sm leading-6 text-slate-600"
            >
                Belum ada data santri yang terhubung ke akun wali ini.
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3">
            <Link
                href="/wali/notifikasi"
                class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                    Notifikasi
                </p>
                <p class="mt-2 font-serif text-xl text-slate-900">
                    Info wali
                </p>
            </Link>
            <Link
                href="/wali/izin"
                class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                    Perizinan
                </p>
                <p class="mt-2 font-serif text-xl text-slate-900">
                    Ajukan izin
                </p>
            </Link>
        </section>
    </div>
</template>
