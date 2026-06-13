<script setup>
import { computed } from 'vue'

/**
 * LoadingState — skeleton animate-pulse pengganti teks "Memuat…".
 * variant: 'card' (default) | 'list' | 'stat' | 'hero'
 * count  : jumlah placeholder
 */
const props = defineProps({
    variant: { type: String, default: 'card' },
    count: { type: Number, default: 3 },
})

const items = computed(() => Array.from({ length: Math.max(1, props.count) }))
</script>

<template>
    <div class="animate-pulse" role="status" aria-label="Memuat" aria-live="polite">
        <!-- Grid KPI 2 kolom -->
        <div v-if="variant === 'stat'" class="grid grid-cols-2 gap-3">
            <div
                v-for="(_, i) in items"
                :key="i"
                class="rounded-3xl bg-white p-5 ring-1 ring-slate-100 shadow-soft"
            >
                <div class="h-3 w-20 rounded-full bg-slate-100" />
                <div class="mt-3 h-7 w-24 rounded-lg bg-slate-100" />
            </div>
        </div>

        <!-- Hero placeholder (kartu gradient tinggi) -->
        <div
            v-else-if="variant === 'hero'"
            class="rounded-3xl bg-slate-100 px-6 py-8"
        >
            <div class="h-4 w-28 rounded-full bg-slate-200/80" />
            <div class="mt-4 h-9 w-44 rounded-xl bg-slate-200/80" />
        </div>

        <!-- Daftar baris -->
        <div v-else-if="variant === 'list'" class="space-y-3">
            <div
                v-for="(_, i) in items"
                :key="i"
                class="flex items-center gap-3 rounded-2xl bg-white p-4 ring-1 ring-slate-100 shadow-soft"
            >
                <div class="h-11 w-11 shrink-0 rounded-2xl bg-slate-100" />
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="h-3.5 w-2/3 rounded-full bg-slate-100" />
                    <div class="h-3 w-1/3 rounded-full bg-slate-100" />
                </div>
            </div>
        </div>

        <!-- Kartu konten generik -->
        <div v-else class="space-y-4">
            <div
                v-for="(_, i) in items"
                :key="i"
                class="rounded-3xl bg-white p-5 ring-1 ring-slate-100 shadow-soft"
            >
                <div class="h-4 w-1/2 rounded-full bg-slate-100" />
                <div class="mt-3 space-y-2">
                    <div class="h-3 w-full rounded-full bg-slate-100" />
                    <div class="h-3 w-5/6 rounded-full bg-slate-100" />
                </div>
            </div>
        </div>
    </div>
</template>
