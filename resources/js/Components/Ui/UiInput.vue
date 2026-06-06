<script setup>
import { computed } from 'vue'

/**
 * UiInput — input konsisten (text/number/date/file/textarea/select-via-slot).
 * Mendukung v-model. Untuk file, gunakan @change dan jangan v-model.
 * - type: text | number | date | email | tel | password | file | textarea
 * - invalid: ring merah (untuk error state, biasanya dari UiField)
 * - leading: teks awalan (mis. "Rp") via slot #leading
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
    accept: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'change'])

const isTextarea = computed(() => props.type === 'textarea')
const isFile = computed(() => props.type === 'file')

const baseClass = computed(() => [
    'w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 transition-all duration-200',
    'placeholder:font-normal placeholder:text-slate-400',
    'ring-1 ring-inset focus:outline-none focus:ring-2 focus:ring-offset-0',
    props.invalid
        ? 'ring-rose-300 focus:ring-rose-500'
        : 'ring-slate-200 hover:ring-slate-300 focus:ring-[var(--wali-primary,#059669)]',
    props.disabled ? 'cursor-not-allowed bg-slate-50 text-slate-400' : '',
    isTextarea.value ? 'py-3 leading-6' : 'min-h-[48px] py-3',
    isFile.value
        ? 'file:mr-3 file:rounded-full file:border-0 file:bg-emerald-50 file:px-3.5 file:py-2 file:text-xs file:font-bold file:text-emerald-700'
        : '',
])

function onInput(e) {
    emit('update:modelValue', e.target.value)
}
function onFile(e) {
    emit('change', e)
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
        v-else-if="isFile"
        type="file"
        :accept="accept"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :class="baseClass"
        @change="onFile"
    >
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
