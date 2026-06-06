<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import UiIcon from './UiIcon.vue'

/**
 * UiButton — tombol/aksi utama Portal Wali.
 * - variant: primary | secondary | ghost | danger
 * - size: sm | md | lg
 * - block: lebar penuh
 * - loading: spinner + disabled
 * - as: 'button' (default) | 'link' (Inertia <Link>) | 'a'
 *   (jika `href` diisi tanpa `as`, otomatis jadi Inertia <Link>)
 */
const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    block: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
    href: { type: String, default: null },
    as: { type: String, default: null },
    iconLeft: { type: String, default: null },
    iconRight: { type: String, default: null },
})

const tag = computed(() => {
    if (props.as === 'link') return Link
    if (props.as === 'a') return 'a'
    if (props.as === 'button') return 'button'
    return props.href ? Link : 'button'
})

const isDisabled = computed(() => props.disabled || props.loading)

const base =
    'relative inline-flex items-center justify-center gap-2 font-semibold rounded-xl ' +
    'transition-all duration-150 select-none ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ' +
    'focus-visible:ring-offset-white disabled:cursor-not-allowed'

const sizes = {
    sm: 'min-h-[40px] px-3.5 text-sm',
    md: 'min-h-[44px] px-5 text-sm',
    lg: 'min-h-[52px] px-6 text-base',
}

const variants = {
    primary:
        'bg-[var(--wali-primary,#059669)] text-white shadow-sm ' +
        'hover:brightness-95 active:brightness-90 ' +
        'focus-visible:ring-[var(--wali-primary,#059669)] ' +
        'disabled:opacity-50',
    secondary:
        'bg-white text-[var(--wali-primary,#059669)] ring-1 ring-inset ring-[var(--wali-primary,#059669)] ' +
        'hover:bg-emerald-50 active:bg-emerald-100 ' +
        'focus-visible:ring-[var(--wali-primary,#059669)] ' +
        'disabled:opacity-50',
    ghost:
        'bg-transparent text-slate-700 ' +
        'hover:bg-slate-100 active:bg-slate-200 ' +
        'focus-visible:ring-slate-400 ' +
        'disabled:opacity-50',
    danger:
        'bg-rose-500 text-white shadow-sm ' +
        'hover:bg-rose-600 active:bg-rose-700 ' +
        'focus-visible:ring-rose-500 ' +
        'disabled:opacity-50',
}

const classes = computed(() => [
    base,
    sizes[props.size] ?? sizes.md,
    variants[props.variant] ?? variants.primary,
    props.block ? 'w-full' : '',
])
</script>

<template>
    <component
        :is="tag"
        :href="tag !== 'button' ? href : undefined"
        :type="tag === 'button' ? type : undefined"
        :disabled="tag === 'button' ? isDisabled : undefined"
        :aria-disabled="tag !== 'button' && isDisabled ? 'true' : undefined"
        :aria-busy="loading ? 'true' : undefined"
        :class="[classes, { 'pointer-events-none opacity-50': tag !== 'button' && isDisabled }]"
    >
        <UiIcon v-if="loading" name="spinner" class="h-4 w-4" />
        <UiIcon v-else-if="iconLeft" :name="iconLeft" class="h-4 w-4 shrink-0" />
        <span :class="{ 'opacity-90': loading }"><slot /></span>
        <UiIcon v-if="iconRight && !loading" :name="iconRight" class="h-4 w-4 shrink-0" />
    </component>
</template>
