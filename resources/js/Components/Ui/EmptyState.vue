<script setup>
/**
 * EmptyState — kondisi kosong yang ramah & ilustratif premium:
 * ikon dalam lingkaran gradient lembut + judul + pesan + 1 aksi (slot).
 * - icon: nama UiIcon (default 'inbox')
 * - title, message
 * - tone: 'slate' (default) | 'emerald' (mis. semua lunas)
 * Slot: #action (tombol opsional)
 */
import { computed } from 'vue'
import UiIcon from './UiIcon.vue'
import IslamicPattern from './IslamicPattern.vue'

const props = defineProps({
    icon: { type: String, default: 'inbox' },
    title: { type: String, default: 'Belum ada data' },
    message: { type: String, default: null },
    tone: { type: String, default: 'slate' },
})

const tones = {
    slate: {
        wrap: 'bg-white ring-slate-100',
        halo: 'bg-slate-100',
        circle: 'bg-gradient-to-br from-slate-100 to-slate-200 text-slate-400 ring-white',
        title: 'text-slate-800',
        message: 'text-slate-500',
        pattern: '#94a3b8', // slate-400
    },
    emerald: {
        wrap: 'bg-emerald-50/50 ring-emerald-100',
        halo: 'bg-emerald-100/70',
        circle: 'bg-gradient-to-br from-emerald-100 to-teal-100 text-emerald-600 ring-white',
        title: 'text-emerald-800',
        message: 'text-emerald-700/80',
        pattern: '#059669', // emerald-600
    },
}
const t = computed(() => tones[props.tone] ?? tones.slate)
</script>

<template>
    <div
        class="relative flex flex-col items-center overflow-hidden rounded-3xl px-6 py-11 text-center ring-1 shadow-soft"
        :class="t.wrap"
    >
        <span
            aria-hidden="true"
            class="pointer-events-none absolute -top-10 left-1/2 h-32 w-32 -translate-x-1/2 rounded-full blur-3xl opacity-60"
            :class="t.halo"
        />
        <!-- Medali pola geometris islami lembut di belakang ikon -->
        <span
            aria-hidden="true"
            class="pointer-events-none absolute left-1/2 top-[1.35rem] h-28 w-28 -translate-x-1/2 overflow-hidden rounded-full [mask-image:radial-gradient(circle,#000_38%,transparent_72%)]"
        >
            <IslamicPattern :color="t.pattern" :opacity="0.14" :size="34" />
        </span>
        <span
            class="relative flex h-16 w-16 items-center justify-center rounded-2xl ring-4 shadow-soft"
            :class="t.circle"
        >
            <UiIcon :icon="icon" :name="icon" class="h-7 w-7" />
        </span>
        <p class="relative mt-4 text-base font-bold tracking-tight" :class="t.title">
            {{ title }}
        </p>
        <p v-if="message" class="relative mt-1.5 max-w-xs text-sm leading-6" :class="t.message">
            {{ message }}
        </p>
        <div v-if="$slots.action" class="relative mt-5 w-full max-w-[15rem]">
            <slot name="action" />
        </div>
    </div>
</template>
