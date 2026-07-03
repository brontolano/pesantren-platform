<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { HeroHeader, SectionCard, EmptyState, UiButton, StatusBadge } from '@/Shared'

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
    <div class="min-h-screen bg-[#fffaf3] pb-10">
        <HeroHeader :name="nama" greeting="Ujian Online">
            <template #action>
                <Link href="/app" class="text-xs text-white/80">← Beranda</Link>
            </template>
        </HeroHeader>

        <main class="mx-auto -mt-7 max-w-md space-y-3 px-5">
            <p v-if="errors.sesi" class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errors.sesi }}</p>

            <EmptyState
                v-if="sesi.length === 0"
                icon="document"
                title="Belum ada jadwal ujian"
                message="Belum ada jadwal ujian untuk kelasmu."
            />

            <SectionCard v-for="s in sesi" :key="s.id">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-stone-800">{{ s.nama }}</p>
                        <p class="text-xs text-stone-400">{{ s.pelajaran }} · {{ s.durasi_menit }} menit</p>
                        <p class="mt-1 text-xs text-stone-500">{{ s.mulai_at }} → {{ s.selesai_at }}</p>
                    </div>
                    <StatusBadge
                        :status="s.berlangsung ? 'berlangsung' : 'nonaktif'"
                        :label="s.berlangsung ? 'Dibuka' : 'Tutup'"
                        size="sm"
                    />
                </div>

                <div class="mt-3">
                    <p v-if="s.status_attempt === 'selesai'" class="text-sm font-semibold text-emerald-700">
                        Selesai — Nilai: {{ s.nilai_final ?? '—' }}
                    </p>
                    <p v-else-if="s.status_attempt === 'menunggu_nilai'" class="text-sm font-semibold text-amber-700">
                        Dikumpulkan — menunggu penilaian essay.
                    </p>
                    <UiButton
                        v-else-if="s.status_attempt === 'berjalan'"
                        :href="`/app/ujian/${s.id}`"
                        block
                    >Lanjutkan Ujian</UiButton>
                    <UiButton
                        v-else-if="s.berlangsung"
                        type="button"
                        block
                        @click="mulai(s.id)"
                    >Mulai Ujian</UiButton>
                    <p v-else class="text-xs text-stone-400">Belum dibuka / sudah ditutup.</p>
                </div>
            </SectionCard>
        </main>
    </div>
</template>
