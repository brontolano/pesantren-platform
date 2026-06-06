<script setup>
import { computed } from 'vue'

/**
 * MoneyText — format Rupiah lokal id-ID dengan tabular-nums.
 * - amount: number | string (akan di-Number-kan; aman untuk null)
 * - size: 'sm' | 'md' | 'lg' | 'xl' | '2xl'  (nominal menonjol di kartu saldo/tagihan)
 * - tone: 'default' | 'emerald' | 'rose' | 'muted' | 'inherit'
 * - signed: tampilkan + / - di depan (untuk mutasi)
 * - strong: font-bold (default true)
 */
const props = defineProps({
    amount: { type: [Number, String], default: 0 },
    size: { type: String, default: 'md' },
    tone: { type: String, default: 'default' },
    signed: { type: Boolean, default: false },
    sign: { type: String, default: null }, // 'plus' | 'minus' override
    strong: { type: Boolean, default: true },
})

const numeric = computed(() => Number(props.amount || 0))

const formatted = computed(() =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Math.abs(numeric.value)),
)

const prefix = computed(() => {
    if (props.sign === 'plus') return '+ '
    if (props.sign === 'minus') return '- '
    if (props.signed) return numeric.value < 0 ? '- ' : '+ '
    return ''
})

const sizes = {
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-xl',
    xl: 'text-3xl',
    '2xl': 'text-4xl',
}

const tones = {
    default: 'text-slate-900',
    emerald: 'text-emerald-700',
    rose: 'text-rose-600',
    muted: 'text-slate-500',
    inherit: '',
}

const cls = computed(() => [
    'tabular-nums tracking-tight',
    props.strong ? 'font-bold' : 'font-semibold',
    sizes[props.size] ?? sizes.md,
    tones[props.tone] ?? tones.default,
])
</script>

<template>
    <span :class="cls">{{ prefix }}{{ formatted }}</span>
</template>
