<script setup>
// Beranda Portal SPMB — HUB ringkas (bukan tempat isi form). Form biodata pindah
// ke halaman fokus /spmb/biodata (wizard). Beranda menampilkan status, progres,
// dan jalan pintas ke aksi penting.
import { Head, Link } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';
import MdButton from '@/spmb/components/MdButton.vue';

const props = defineProps({
    candidate: { type: Object, required: true },
    statusCard: { type: Object, required: true },
    stepper: { type: Array, required: true },
    timeline: { type: Array, default: () => [] },
    checklist: { type: Array, required: true },
    mainAction: { type: Object, required: true },
    tracking: { type: Object, required: true },
    bolehDaftarUlang: { type: Boolean, default: false },
    // diterima dari controller, tak dipakai di hub (dipakai di halaman biodata)
    fieldConfiguration: { type: Object, default: () => ({}) },
    biodataSections: { type: Object, default: () => ({}) },
});

const pct = () => {
    const { filled = 0, total = 0 } = props.candidate.completion ?? {};
    return total > 0 ? Math.round((filled / total) * 100) : 0;
};

const quickLinks = [
    { href: '/spmb/berkas', icon: '📄', label: 'Berkas' },
    { href: '/spmb/tagihan', icon: '🧾', label: 'Tagihan' },
    { href: '/spmb/kartu-ujian', icon: '🪪', label: 'Kartu Ujian' },
    { href: '/spmb/pengumuman', icon: '📣', label: 'Pengumuman' },
];
</script>

<template>
    <Head :title="`Portal SPMB ${candidate.nomorPendaftaran}`" />

    <SpmbLayout brand-header>
        <div class="space-y-4">
            <!-- Hero status -->
            <MdCard>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="inline-flex rounded-full bg-[var(--spmb-primary)]/12 px-2.5 py-1 text-[11px] font-semibold text-[var(--spmb-primary)]">
                        {{ statusCard.badge }}
                    </span>
                    <span class="truncate text-[12px] font-medium text-stone-500">Calon: {{ candidate.nama }}</span>
                </div>
                <h1 class="mt-2 text-lg font-bold leading-snug text-stone-900">{{ statusCard.title }}</h1>
                <p class="mt-1 text-[13px] leading-relaxed text-stone-600">{{ statusCard.description }}</p>
                <div class="mt-3 inline-flex items-center gap-2 rounded-xl bg-stone-900 px-3 py-2 text-white">
                    <span class="text-[10px] uppercase tracking-wider text-white/60">No. Pendaftaran</span>
                    <span class="text-[14px] font-bold">{{ candidate.nomorPendaftaran }}</span>
                </div>
            </MdCard>

            <!-- CTA Daftar Ulang (peserta LULUS) -->
            <Link v-if="bolehDaftarUlang" href="/spmb/daftar-ulang" class="block">
                <div class="flex items-center justify-between rounded-2xl bg-violet-600 px-4 py-3.5 text-white shadow-sm active:brightness-95">
                    <span class="text-[15px] font-semibold">🎉 Anda LULUS — Daftar Ulang</span>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                </div>
            </Link>

            <!-- CTA UTAMA: Lengkapi Biodata (ke halaman wizard) -->
            <Link href="/spmb/biodata" class="block">
                <MdCard>
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-[15px] font-bold text-stone-900">Biodata peserta</h2>
                            <p class="mt-0.5 text-[12px] text-stone-500">
                                {{ candidate.completion.filled }} / {{ candidate.completion.total }} data wajib terisi
                            </p>
                        </div>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--spmb-primary)] text-white">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                        </span>
                    </div>
                    <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-stone-200">
                        <div class="h-full rounded-full bg-[var(--spmb-primary)] transition-all" :style="{ width: pct() + '%' }" />
                    </div>
                    <p class="mt-2 text-[12px] font-medium text-[var(--spmb-primary)]">
                        {{ pct() < 100 ? 'Ketuk untuk lengkapi biodata →' : 'Biodata lengkap — ketuk untuk meninjau →' }}
                    </p>
                </MdCard>
            </Link>

            <!-- Aksi cepat (bento) -->
            <div class="grid grid-cols-4 gap-2">
                <Link
                    v-for="q in quickLinks"
                    :key="q.href"
                    :href="q.href"
                    class="flex flex-col items-center gap-1 rounded-2xl border border-stone-200 bg-white px-1 py-3 text-[11px] font-medium text-stone-700 shadow-sm active:bg-stone-50"
                >
                    <span class="text-xl">{{ q.icon }}</span> {{ q.label }}
                </Link>
            </div>

            <!-- Tahap pendaftaran (stepper horizontal) -->
            <div>
                <h2 class="mb-2 px-1 text-[13px] font-semibold text-stone-500">Tahap pendaftaran</h2>
                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" style="scrollbar-width: none">
                    <div
                        v-for="(stepItem, index) in stepper"
                        :key="stepItem.title"
                        class="w-32 shrink-0 rounded-2xl border px-3 py-3"
                        :class="{
                            'border-[var(--spmb-primary)]/30 bg-[var(--spmb-primary)]/8': stepItem.state === 'current',
                            'border-stone-200 bg-white': stepItem.state === 'upcoming',
                            'border-stone-900 bg-stone-900 text-white': stepItem.state === 'done',
                        }"
                    >
                        <div class="text-[10px] font-semibold uppercase tracking-wide" :class="stepItem.state === 'done' ? 'text-white/50' : 'text-stone-400'">
                            Tahap {{ index + 1 }}
                        </div>
                        <div class="mt-1 text-[13px] font-bold leading-tight">{{ stepItem.title }}</div>
                    </div>
                </div>
            </div>

            <!-- Checklist -->
            <MdCard>
                <h2 class="mb-3 text-[15px] font-bold text-stone-900">Checklist peserta</h2>
                <div class="space-y-2.5">
                    <div
                        v-for="item in checklist"
                        :key="item.title"
                        class="flex items-start gap-3 rounded-xl border px-3 py-2.5"
                        :class="item.done ? 'border-emerald-200 bg-emerald-50' : 'border-stone-200 bg-stone-50'"
                    >
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] text-white" :class="item.done ? 'bg-emerald-500' : 'bg-amber-400'">
                            {{ item.done ? '✓' : '!' }}
                        </span>
                        <div>
                            <div class="text-[13px] font-semibold text-stone-900">{{ item.title }}</div>
                            <p class="mt-0.5 text-[12px] leading-relaxed text-stone-600">{{ item.description }}</p>
                        </div>
                    </div>
                </div>
            </MdCard>

            <!-- Riwayat aktivitas -->
            <MdCard v-if="timeline.length">
                <h2 class="mb-3 text-[15px] font-bold text-stone-900">Riwayat aktivitas</h2>
                <ol class="space-y-2.5">
                    <li v-for="(item, idx) in timeline" :key="idx" class="flex items-start gap-3">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :class="idx === 0 ? 'bg-violet-500' : 'bg-stone-300'" />
                        <div>
                            <div class="text-[13px] font-semibold text-stone-900">{{ item.label }}</div>
                            <div class="text-[11px] text-stone-500">{{ item.tanggal }}</div>
                        </div>
                    </li>
                </ol>
            </MdCard>

            <!-- Tautan status tanpa login -->
            <MdCard>
                <h2 class="text-[15px] font-bold text-stone-900">Status cepat tanpa login</h2>
                <p class="mt-1 text-[12px] leading-relaxed text-stone-500">
                    Tautan ini hanya untuk melihat status ringkas. Mengubah biodata atau unggah dokumen tetap wajib login.
                </p>
                <a :href="tracking.url" target="_blank" rel="noopener noreferrer" class="mt-3 block">
                    <MdButton variant="tonal">Buka link status</MdButton>
                </a>
            </MdCard>
        </div>
    </SpmbLayout>
</template>
