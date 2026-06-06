<script setup>
/**
 * EmptyState — kondisi kosong yang ramah: ikon + judul + pesan + 1 aksi (slot).
 * - icon: nama UiIcon (default 'inbox')
 * - title, message
 * - tone: 'slate' (default) | 'emerald' (mis. semua lunas)
 * Slot: #action (tombol opsional)
 */
import { computed } from 'vue'
import UiIcon from './UiIcon.vue'

const props = defineProps({
    icon: { type: String, default: 'inbox' },
    title: { type: String, default: 'Belum ada data' },
    message: { type: String, default: null },
    tone: { type: String, default: 'slate' },
})

const tones = {
    slate: {
        wrap: 'border-slate-200 bg-white',
        circle: 'bg-slate-100 text-slate-400',
        title: 'text-slate-700',
        message: 'text-slate-500',
    },
    emerald: {
        wrap: 'border-emerald-200 bg-emerald-50/60',
        circle: 'bg-emerald-100 text-emerald-600',
        title: 'text-emerald-800',
        message: 'text-emerald-700/80',
    },
}
const t = computed(() => tones[props.tone] ?? tones.slate)
</script>

<template>
    <div
        class="flex flex-col items-center rounded-2xl border border-dashed px-6 py-10 text-center"
        :class="t.wrap"
    >
        <span class="flex h-14 w-14 items-center justify-center rounded-full" :class="t.circle">
            <UiIcon :name="icon" class="h-7 w-7" />
        </span>
        <p class="mt-4 text-base font-bold" :class="t.title">
            {{ title }}
        </p>
        <p v-if="message" class="mt-1 max-w-xs text-sm" :class="t.message">
            {{ message }}
        </p>
        <div v-if="$slots.action" class="mt-5 w-full max-w-[15rem]">
            <slot name="action" />
        </div>
    </div>
</template>
