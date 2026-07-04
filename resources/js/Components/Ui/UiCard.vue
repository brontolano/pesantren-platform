<script setup>
import { computed } from 'vue'
import IslamicPattern from './IslamicPattern.vue'

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
    // Surface putih + border tipis hangat (Pixio), shadow sangat lembut.
    default: 'bg-white ring-1 ring-[var(--wali-border,#efe7da)] text-slate-900',
    // hero-dark: emerald solid-elegan, TETAP gelap/kontras (halaman pakai teks putih di dalamnya).
    'hero-dark': 'bg-brand-grad-hero text-white ring-1 ring-emerald-900/10',
    muted: 'bg-[#f6f0e6] ring-1 ring-[var(--wali-border,#efe7da)] text-slate-900',
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
    return 'shadow-lift'
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
        <!-- Aksen highlight tipis untuk hero (minimalis, bukan glow berat) -->
        <template v-if="isHero">
            <!-- Tekstur islami subtil agar semua kartu hero konsisten -->
            <IslamicPattern :color="'#ffffff'" :opacity="0.05" :size="54" />
            <span
                aria-hidden="true"
                class="pointer-events-none absolute -right-16 -top-20 h-44 w-44 rounded-full bg-white/10 blur-2xl"
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
