<script setup>
import UiIcon from '@/Components/Ui/UiIcon.vue'

/**
 * SegmentedControl — toggle 2-3 opsi (jenis setoran, hasil munaqasyah, status
 * kitab, arah gerbang, metode bayar). v-model.
 *   <SegmentedControl v-model="jenis" :options="[{value:'ziyadah',label:'Ziyadah'},...]" />
 * options: [{ value, label, icon? }]
 */
defineProps({
    modelValue: { type: [String, Number, Boolean], default: null },
    options: { type: Array, default: () => [] },
})
defineEmits(['update:modelValue'])
</script>

<template>
    <div
        class="inline-flex w-full items-center gap-1 rounded-full bg-white p-1 shadow-[inset_0_0_0_1px_#efe7da]"
        role="group"
    >
        <button
            v-for="opt in options"
            :key="String(opt.value)"
            type="button"
            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-full px-4 py-2 font-jost text-[13px] font-semibold tracking-tight transition-all duration-200 active:scale-[.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--brand-primary)]"
            :class="opt.value === modelValue
                ? 'bg-[var(--brand-primary)] text-white shadow-brand'
                : 'text-slate-600 hover:text-slate-900'"
            :aria-pressed="opt.value === modelValue"
            @click="$emit('update:modelValue', opt.value)"
        >
            <UiIcon v-if="opt.icon" :name="opt.icon" class="h-4 w-4 shrink-0" />
            {{ opt.label }}
        </button>
    </div>
</template>
