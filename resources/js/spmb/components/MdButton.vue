<script setup>
// Tombol ala Material: varian primary/tonal/ghost/danger, efek tekan (active
// scale), state loading. Default block (lebar penuh) untuk ergonomi ponsel.
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary|tonal|ghost|danger
    type: { type: String, default: 'button' },
    block: { type: Boolean, default: true },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const variantClass = computed(() => ({
    primary: 'bg-[var(--spmb-primary)] text-white shadow-sm active:brightness-95',
    tonal: 'bg-[var(--spmb-primary)]/12 text-[var(--spmb-primary)] active:bg-[var(--spmb-primary)]/20',
    ghost: 'bg-transparent text-stone-700 active:bg-black/5',
    danger: 'bg-red-600 text-white shadow-sm active:brightness-95',
}[props.variant] ?? ''));
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex h-12 items-center justify-center gap-2 rounded-full px-5 text-[15px] font-semibold transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
        :class="[variantClass, block ? 'w-full' : '']"
    >
        <svg v-if="loading" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <slot />
    </button>
</template>
