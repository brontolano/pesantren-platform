<script setup>
import { computed } from 'vue'

/**
 * UiCard — permukaan dasar.
 * - variant: 'default' (kartu putih rounded-2xl shadow-sm ring) |
 *            'hero-dark' (gradient emerald gelap, teks putih) |
 *            'muted' (slate-50 tanpa shadow)
 * - padding: 'none' | 'sm' | 'md' | 'lg'
 * Slot: default, #header, #footer
 */
const props = defineProps({
    variant: { type: String, default: 'default' },
    padding: { type: String, default: 'md' },
})

const surfaces = {
    default: 'bg-white ring-1 ring-slate-100 shadow-sm text-slate-900',
    'hero-dark':
        'bg-gradient-to-br from-emerald-700 to-emerald-900 text-white shadow-md ring-1 ring-emerald-900/20',
    muted: 'bg-slate-50 ring-1 ring-slate-100 text-slate-900',
}

const paddings = {
    none: '',
    sm: 'p-4',
    md: 'p-5',
    lg: 'p-6',
}

const rootClass = computed(() => [
    'rounded-2xl overflow-hidden',
    surfaces[props.variant] ?? surfaces.default,
])

const bodyPad = computed(() => paddings[props.padding] ?? paddings.md)
const dividerClass = computed(() =>
    props.variant === 'hero-dark' ? 'border-white/15' : 'border-slate-100',
)
</script>

<template>
    <div :class="rootClass">
        <div v-if="$slots.header" :class="['border-b px-5 py-4', dividerClass]">
            <slot name="header" />
        </div>
        <div :class="bodyPad">
            <slot />
        </div>
        <div v-if="$slots.footer" :class="['border-t px-5 py-4', dividerClass]">
            <slot name="footer" />
        </div>
    </div>
</template>
