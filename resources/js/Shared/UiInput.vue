<script setup>
import { computed } from 'vue'

/**
 * UiInput (Shared form-kit, refresh 2026-07 pasca palet violet #440) —
 * varian input yang lebih "lega": radius lebih besar (rounded-3xl), shadow
 * berlapis (--shadow-lift) alih-alih hairline datar, dan focus ring violet
 * (brand primary baru). Menggantikan `@/Components/Ui/UiInput.vue` di
 * halaman baru lintas peran (Wali/Guru/Akuntan/BaitulMaal dst) — komponen
 * lama tetap ada & dipakai halaman existing (zero-churn).
 *
 * - type: text | number | date | email | tel | password | textarea
 * - invalid: ring rose (error state, biasanya dari UiField)
 */
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: null },
    inputmode: { type: String, default: null },
    rows: { type: [String, Number], default: 3 },
    min: { type: [String, Number], default: null },
    max: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:modelValue'])

const isTextarea = computed(() => props.type === 'textarea')

const baseClass = computed(() => [
    'w-full rounded-3xl bg-white px-4 text-sm font-medium text-slate-900 transition-all duration-200',
    'placeholder:font-normal placeholder:text-slate-400',
    'ring-1 ring-inset shadow-[var(--shadow-lift)] focus:outline-none focus:ring-2 focus:ring-offset-0',
    props.invalid
        ? 'ring-rose-300 focus:ring-rose-500'
        : 'ring-slate-200 hover:ring-slate-300 focus:ring-violet-500',
    props.disabled ? 'cursor-not-allowed bg-slate-50 text-slate-400 shadow-none' : '',
    isTextarea.value ? 'py-3 leading-6' : 'min-h-[48px] py-3',
])

function onInput(e) {
    emit('update:modelValue', e.target.value)
}
</script>

<template>
    <textarea
        v-if="isTextarea"
        :value="modelValue"
        :rows="rows"
        :placeholder="placeholder"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :class="baseClass"
        @input="onInput"
    />
    <input
        v-else
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :inputmode="inputmode"
        :min="min"
        :max="max"
        :aria-invalid="invalid || undefined"
        :class="baseClass"
        @input="onInput"
    >
</template>
</content>
