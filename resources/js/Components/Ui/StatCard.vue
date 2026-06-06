<script setup>
import { computed } from 'vue'
import IconCircle from './IconCircle.vue'

/**
 * StatCard — mini kartu statistik premium: ikon bertint + angka besar + label.
 * Cocok untuk grid ringkasan (outstanding, saldo, jumlah menunggu/terverifikasi).
 * - icon: nama UiIcon (opsional; jika ada tampil IconCircle)
 * - tone: warna aksen ikon & angka -> 'emerald' | 'teal' | 'amber' | 'rose' | 'blue' | 'slate'
 * - label: teks kecil uppercase di atas/bawah
 * - value: konten utama via prop string ATAU slot #value (mis. <MoneyText/>)
 * - hint: keterangan kecil opsional di bawah
 * - compact: padding lebih rapat
 * Slot: #value (override angka, mis. MoneyText), default (konten tambahan)
 */
const props = defineProps({
    icon: { type: String, default: null },
    tone: { type: String, default: 'emerald' },
    label: { type: String, default: null },
    value: { type: [String, Number], default: null },
    hint: { type: String, default: null },
    compact: { type: Boolean, default: false },
})

const VALUE_TONES = {
    emerald: 'text-emerald-700',
    teal: 'text-teal-700',
    amber: 'text-amber-600',
    rose: 'text-rose-600',
    blue: 'text-blue-600',
    slate: 'text-slate-900',
}
const valueTone = computed(() => VALUE_TONES[props.tone] ?? VALUE_TONES.slate)
</script>

<template>
    <div
        class="flex flex-col rounded-3xl bg-white ring-1 ring-slate-100 shadow-soft transition-all duration-200"
        :class="compact ? 'p-4' : 'p-5'"
    >
        <div class="flex items-center justify-between gap-2">
            <p
                v-if="label"
                class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
            >
                {{ label }}
            </p>
            <IconCircle v-if="icon" :icon="icon" :tone="tone" size="sm" />
        </div>

        <div class="mt-2.5 leading-none">
            <slot name="value">
                <span
                    class="text-2xl font-extrabold tabular-nums tracking-tight"
                    :class="valueTone"
                >
                    {{ value }}
                </span>
            </slot>
        </div>

        <p v-if="hint" class="mt-1.5 text-xs font-medium text-slate-400">
            {{ hint }}
        </p>
        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>
