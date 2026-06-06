<script setup>
/**
 * PageHeading — judul section di dalam konten (bukan hero).
 * - kicker: label kecil uppercase di atas judul (emerald)
 * - title: judul utama
 * - subtitle: deskripsi
 * Slot: #right (aksi/tautan di kanan, mis. "Lihat semua")
 */
defineProps({
    kicker: { type: String, default: null },
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
})
</script>

<template>
    <div class="flex items-end justify-between gap-3">
        <div class="min-w-0">
            <p
                v-if="kicker"
                class="inline-flex items-center gap-1.5 font-jost text-[11px] font-semibold uppercase tracking-wider text-emerald-700"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-[var(--wali-primary,#059669)]" />
                {{ kicker }}
            </p>
            <h3 class="mt-1 font-jost text-lg font-bold leading-tight tracking-tight text-slate-900">
                {{ title }}
            </h3>
            <p v-if="subtitle" class="mt-1 text-sm text-slate-500">
                {{ subtitle }}
            </p>
        </div>
        <div v-if="$slots.right" class="shrink-0">
            <slot name="right" />
        </div>
    </div>
</template>
