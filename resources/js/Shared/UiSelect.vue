<script setup>
import { computed } from 'vue'

/**
 * UiSelect (Shared form-kit, refresh 2026-07) — pengganti pola
 * copy-paste `const selectClass = '...'` yang tersebar di banyak halaman
 * (BaitulMaal/Beasiswa, KeuanganPwa/CatatBelanja, Konselor/CatatSesi, dst).
 * Radius lebih besar (rounded-3xl), shadow berlapis (--shadow-lift), focus
 * ring violet. Menerima <option> via slot default agar tetap fleksibel.
 *
 * <UiSelect v-model="form.santri_id" required>
 *   <option value="" disabled>Pilih santri</option>
 *   <option v-for="s in santriList" :key="s.id" :value="s.id">{{ s.nama }}</option>
 * </UiSelect>
 */
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const baseClass = computed(() => [
    'w-full rounded-3xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 transition-all duration-200',
    'ring-1 ring-inset shadow-[var(--shadow-lift)] focus:outline-none focus:ring-2 focus:ring-offset-0',
    props.invalid
        ? 'ring-rose-300 focus:ring-rose-500'
        : 'ring-slate-200 hover:ring-slate-300 focus:ring-violet-500',
    props.disabled ? 'cursor-not-allowed bg-slate-50 text-slate-400 shadow-none' : '',
])

function onChange(e) {
    emit('update:modelValue', e.target.value)
}
</script>

<template>
    <select
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        :aria-invalid="invalid || undefined"
        :class="baseClass"
        @change="onChange"
    >
        <slot />
    </select>
</template>
</content>
