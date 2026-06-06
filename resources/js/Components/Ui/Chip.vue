<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import UiIcon from './UiIcon.vue'

/**
 * Chip — pill toggle / segmented item premium.
 * Dipakai untuk switcher anak, filter status, tab sekunder.
 * - active: status terpilih (pill gradient/solid) vs inaktif (putih ring)
 * - href: jika diisi -> Inertia <Link>, jika tidak -> <button> (emit @click)
 * - icon: ikon kecil kiri opsional
 * - tone: 'brand' (gradient saat aktif, default) | 'solid' (emerald solid) | 'soft' (emerald-50)
 * - size: 'sm' | 'md'
 * Slot: default (label)
 */
const props = defineProps({
    active: { type: Boolean, default: false },
    href: { type: String, default: null },
    icon: { type: String, default: null },
    tone: { type: String, default: 'brand' },
    size: { type: String, default: 'md' },
})
defineEmits(['click'])

const tag = computed(() => (props.href ? Link : 'button'))

const SIZES = {
    sm: 'px-3 py-1.5 text-xs gap-1.5',
    md: 'px-4 py-2 text-sm gap-2',
}

const activeSkin = computed(() => {
    if (props.tone === 'soft') return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100'
    if (props.tone === 'solid') return 'bg-[var(--wali-primary,#059669)] text-white shadow-brand'
    return 'bg-brand-grad text-white shadow-brand'
})
</script>

<template>
    <component
        :is="tag"
        :href="href || undefined"
        :type="tag === 'button' ? 'button' : undefined"
        class="inline-flex shrink-0 items-center justify-center rounded-full font-bold tracking-tight transition-all duration-200 select-none active:scale-[.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[var(--wali-primary,#059669)]"
        :class="[
            SIZES[size] ?? SIZES.md,
            active
                ? activeSkin
                : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:text-slate-800',
        ]"
        :aria-pressed="tag === 'button' ? active : undefined"
        :aria-current="href && active ? 'page' : undefined"
        @click="$emit('click')"
    >
        <UiIcon v-if="icon" :name="icon" class="h-3.5 w-3.5 shrink-0" />
        <slot />
    </component>
</template>
