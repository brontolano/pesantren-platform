<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'

defineProps({
    nama: String,
    sesi: { type: Array, default: () => [] },
})

const errors = usePage().props.errors || {}

function mulai(id) {
    router.post(`/app/ujian/${id}/mulai`)
}
</script>

<template>
    <div class="min-h-screen bg-stone-50 pb-10">
        <header class="bg-gradient-to-b from-emerald-800 to-emerald-600 px-5 pb-12 pt-8 text-white">
            <div class="mx-auto max-w-md">
                <Link href="/app" class="text-xs text-emerald-200">← Beranda</Link>
                <h1 class="mt-1 text-xl font-bold">Ujian Online</h1>
                <p class="text-xs text-emerald-200">{{ nama }}</p>
            </div>
        </header>

        <main class="mx-auto -mt-7 max-w-md space-y-3 px-5">
            <p v-if="errors.sesi" class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errors.sesi }}</p>

            <section v-if="sesi.length === 0" class="rounded-2xl border border-dashed border-stone-300 bg-white/60 px-5 py-6 text-center text-sm text-stone-500">
                Belum ada jadwal ujian untuk kelasmu.
            </section>

            <section v-for="s in sesi" :key="s.id" class="rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-stone-800">{{ s.nama }}</p>
                        <p class="text-xs text-stone-400">{{ s.pelajaran }} · {{ s.durasi_menit }} menit</p>
                        <p class="mt-1 text-xs text-stone-500">{{ s.mulai_at }} → {{ s.selesai_at }}</p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase"
                        :class="s.berlangsung ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500'"
                    >{{ s.berlangsung ? 'Dibuka' : 'Tutup' }}</span>
                </div>

                <div class="mt-3">
                    <p v-if="s.status_attempt === 'selesai'" class="text-sm font-semibold text-emerald-700">
                        Selesai — Nilai: {{ s.nilai_final ?? '—' }}
                    </p>
                    <p v-else-if="s.status_attempt === 'menunggu_nilai'" class="text-sm font-semibold text-amber-700">
                        Dikumpulkan — menunggu penilaian essay.
                    </p>
                    <Link
                        v-else-if="s.status_attempt === 'berjalan'"
                        :href="`/app/ujian/${s.id}`"
                        class="block w-full rounded-xl bg-emerald-600 py-2.5 text-center text-sm font-bold text-white"
                    >Lanjutkan Ujian</Link>
                    <button
                        v-else-if="s.berlangsung"
                        type="button"
                        class="w-full rounded-xl bg-emerald-600 py-2.5 text-sm font-bold text-white"
                        @click="mulai(s.id)"
                    >Mulai Ujian</button>
                    <p v-else class="text-xs text-stone-400">Belum dibuka / sudah ditutup.</p>
                </div>
            </section>
        </main>
    </div>
</template>
