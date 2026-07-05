<script setup>
import { computed } from 'vue'
import UiIcon from './UiIcon.vue'

/**
 * IconCircle — lingkaran ikon bertint (komponen premium reusable).
 * Dipakai sebagai leading di ListRow, header MetodeCard, dekorasi StatCard, dll.
 * - icon: nama UiIcon
 * - tone: 'violet' | 'emerald' | 'teal' | 'amber' | 'orange' | 'rose' | 'blue' | 'slate'
 *   ('violet' = brand-primary baru, refresh 2026-07; tone lain tetap ada
 *   sebagai variasi kategori, bukan dihapus — cuma bukan default lagi)
 * - variant: 'tint' (bg lembut, default) | 'gradient' (fill gradient brand, ikon putih) | 'glass' (untuk di atas hero)
 * - size: 'sm' | 'md' | 'lg'
 */
const props = defineProps({
    icon: { type: String, required: true },
    tone: { type: String, default: 'violet' },
    variant: { type: String, default: 'tint' },
    size: { type: String, default: 'md' },
})

const TINTS = {
    violet: 'bg-violet-50 text-violet-600 ring-violet-100',
    emerald: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
    teal: 'bg-teal-50 text-teal-600 ring-teal-100',
    amber: 'bg-amber-50 text-amber-600 ring-amber-100',
    orange: 'bg-orange-50 text-orange-600 ring-orange-100',
    rose: 'bg-rose-50 text-rose-600 ring-rose-100',
    blue: 'bg-blue-50 text-blue-600 ring-blue-100',
    slate: 'bg-slate-100 text-slate-500 ring-slate-200',
}

// Bulat penuh (rounded-full) — bahasa bentuk "tombol ikon lingkaran" ala
// referensi Shoppe, diadopsi lintas Pixio (warna tetap emerald/tint brand).
const SIZES = {
    sm: { box: 'h-9 w-9 rounded-full', icon: 'h-4.5 w-4.5' },
    md: { box: 'h-11 w-11 rounded-full', icon: 'h-5 w-5' },
    lg: { box: 'h-14 w-14 rounded-full', icon: 'h-7 w-7' },
}

const sizeMeta = computed(() => SIZES[props.size] ?? SIZES.md)

const skin = computed(() => {
    if (props.variant === 'gradient') {
        return 'bg-brand-grad text-white shadow-brand ring-0'
    }
    if (props.variant === 'glass') {
        return 'bg-white/15 text-white ring-1 ring-inset ring-white/20'
    }
    return `${TINTS[props.tone] ?? TINTS.violet} ring-1 ring-inset`
})
</script>

<template>
    <span
        class="flex shrink-0 items-center justify-center transition-all duration-200"
        :class="[sizeMeta.box, skin]"
    >
        <UiIcon :name="icon" :class="sizeMeta.icon" />
    </span>
</template>
