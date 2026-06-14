<script setup>
// Field form serba-guna ala Material: label di atas, kontrol sesuai `type`,
// teks bantuan + error di bawah. Touch target tinggi (h-12) untuk ponsel.
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    type: { type: String, default: 'text' }, // text|tel|email|number|date|textarea|select
    label: { type: String, default: '' },
    options: { type: Array, default: () => [] }, // [{value,label}]
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    help: { type: String, default: '' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const onInput = (e) => emit('update:modelValue', e.target.value);

const controlClass = computed(() => [
    'w-full rounded-xl border bg-white px-3.5 text-[15px] text-stone-900 outline-none transition placeholder:text-stone-400',
    'focus:border-[var(--spmb-primary)] focus:ring-2 focus:ring-[var(--spmb-primary)]/25',
    'disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-500',
    props.error ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-300',
    props.type === 'textarea' ? 'py-3' : 'h-12',
]);

const inputmode = computed(() => {
    if (props.type === 'number') return 'numeric';
    if (props.type === 'tel') return 'tel';
    if (props.type === 'email') return 'email';
    return undefined;
});
</script>

<template>
    <label class="block">
        <span v-if="label" class="mb-1.5 block text-[13px] font-medium text-stone-700">
            {{ label }}<span v-if="required" class="text-red-500"> *</span>
        </span>

        <select
            v-if="type === 'select'"
            :value="modelValue"
            :disabled="disabled"
            :class="controlClass"
            @change="onInput"
        >
            <option value="">— Pilih —</option>
            <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>

        <textarea
            v-else-if="type === 'textarea'"
            :value="modelValue"
            :disabled="disabled"
            :placeholder="placeholder"
            rows="3"
            :class="controlClass"
            @input="onInput"
        ></textarea>

        <input
            v-else
            :type="type === 'number' ? 'number' : type === 'date' ? 'date' : type === 'email' ? 'email' : type === 'tel' ? 'tel' : 'text'"
            :inputmode="inputmode"
            :value="modelValue"
            :disabled="disabled"
            :placeholder="placeholder"
            :class="controlClass"
            @input="onInput"
        >

        <span v-if="error" class="mt-1 block text-[12px] font-medium text-red-600">{{ error }}</span>
        <span v-else-if="help" class="mt-1 block text-[12px] text-stone-500">{{ help }}</span>
    </label>
</template>
