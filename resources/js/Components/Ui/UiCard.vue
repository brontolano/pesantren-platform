<script setup>
import { computed } from 'vue'

/**
 * UiCard — permukaan dasar premium.
 * - variant: 'default' (kartu putih rounded-3xl + soft layered shadow + ring tipis) |
 *            'hero-dark' (gradient brand + dekorasi glow lembut, teks putih, sudut membulat besar) |
 *            'muted' (slate-50 tanpa shadow)
 * - padding: 'none' | 'sm' | 'md' | 'lg'
 * - flat: matikan shadow (untuk kartu di dalam kartu)
 * Slot: default, #header, #footer
 */
const props = defineProps({
    variant: { type: String, default: 'default' },
    padding: { type: String, default: 'md' },
    flat: { type: Boolean, default: false },
})

const isHero = computed(() => props.variant === 'hero-dark')

const surfaces = {
    default: 'bg-white ring-1 ring-slate-100 text-slate-900',
    'hero-dark': 'bg-brand-grad-hero text-white ring-1 ring-emerald-900/10',
    muted: 'bg-slate-50 ring-1 ring-slate-100 text-slate-900',
}

const paddings = {
    none: '',
    sm: 'p-4',
    md: 'p-5',
    lg: 'p-6',
}

const shadowClass = computed(() => {
    if (props.flat) return ''
    if (isHero.value) return 'shadow-soft-lg'
    return 'shadow-soft'
})

const rootClass = computed(() => [
    'relative overflow-hidden',
    isHero.value ? 'rounded-[1.75rem]' : 'rounded-3xl',
    surfaces[props.variant] ?? surfaces.default,
    shadowClass.value,
])

const bodyPad = computed(() => paddings[props.padding] ?? paddings.md)
const dividerClass = computed(() =>
    isHero.value ? 'border-white/15' : 'border-slate-100',
)
</script>

<template>
    <div :class="rootClass">
        <!-- Dekorasi glow lembut untuk hero (radial blobs, presentasional) -->
        <template v-if="isHero">
            <span
                aria-hidden="true"
                class="pointer-events-none absolute -right-12 -top-16 h-44 w-44 rounded-full bg-white/20 blur-3xl"
            />
            <span
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-16 -left-10 h-40 w-40 rounded-full bg-teal-300/25 blur-3xl"
            />
        </template>

        <div class="relative">
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
    </div>
</template>
