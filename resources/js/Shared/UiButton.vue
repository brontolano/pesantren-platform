<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import UiIcon from '@/Components/Ui/UiIcon.vue'

/**
 * UiButton (Shared form-kit, refresh 2026-07 pasca palet violet #440) —
 * tombol/aksi lintas peran. Primary = solid violet (brand baru); Secondary =
 * outline/ghost. Sama API-nya dengan `@/Components/Ui/UiButton.vue` (kit
 * lama Portal Wali, masih emerald var fallback) agar migrasi halaman mulus.
 *
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
    'relative inline-flex items-center justify-center gap-2 font-jost font-semibold tracking-tight rounded-full ' +
    'transition-all duration-200 select-none active:scale-[.97] ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ' +
    'focus-visible:ring-offset-white disabled:cursor-not-allowed disabled:active:scale-100'

const sizes = {
    sm: 'min-h-[40px] px-5 text-sm',
    md: 'min-h-[46px] px-6 text-sm',
    lg: 'min-h-[54px] px-7 text-base',
}

const variants = {
    // Primary = FILLED violet solid + shadow brand lembut.
    primary:
        'bg-violet-600 text-white shadow-[var(--shadow-brand)] ' +
        'hover:bg-violet-700 active:bg-violet-700 ' +
        'focus-visible:ring-violet-500 ' +
        'disabled:opacity-50 disabled:shadow-none',
    // Secondary = OUTLINE violet 1px, bg transparan.
    secondary:
        'bg-transparent text-violet-700 ring-1 ring-inset ring-violet-300 ' +
        'hover:bg-violet-50 hover:ring-violet-400 active:bg-violet-100 ' +
        'focus-visible:ring-violet-500 ' +
        'disabled:opacity-50',
    // Ghost = transparan tanpa border, hover halus.
    ghost:
        'bg-transparent text-slate-700 ' +
        'hover:bg-slate-900/[0.04] active:bg-slate-900/[0.08] ' +
        'focus-visible:ring-slate-400 ' +
        'disabled:opacity-50',
    // Danger = OUTLINE rose (minimalis), kontras tegas.
    danger:
        'bg-transparent text-rose-600 ring-1 ring-inset ring-rose-300 ' +
        'hover:bg-rose-50 hover:ring-rose-400 active:bg-rose-100 ' +
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
</content>
