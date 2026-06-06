<script setup>
import { computed, useId } from 'vue'

/**
 * UiField — pembungkus label + input + hint + error.
 * Memberi id terhubung ke input via slot-prop `fieldId` & `describedby`.
 *
 * <UiField label="Nominal" :error="form.errors.nominal" hint="Min 1">
 *   <template #default="{ fieldId, describedby }">
 *     <UiInput :id="fieldId" :aria-describedby="describedby" v-model="..." />
 *   </template>
 * </UiField>
 */
const props = defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
})

const uid = useId()
const fieldId = computed(() => `f-${uid}`)
const errorId = computed(() => `${fieldId.value}-error`)
const hintId = computed(() => `${fieldId.value}-hint`)
const describedby = computed(() =>
    [props.error ? errorId.value : null, props.hint ? hintId.value : null]
        .filter(Boolean)
        .join(' ') || undefined,
)
</script>

<template>
    <div class="grid gap-1.5">
        <label
            v-if="label"
            :for="fieldId"
            class="text-sm font-semibold text-slate-700"
        >
            {{ label }}
            <span v-if="required" class="text-rose-500">*</span>
        </label>

        <slot :field-id="fieldId" :describedby="describedby" :invalid="!!error" />

        <p v-if="hint && !error" :id="hintId" class="text-xs text-slate-500">
            {{ hint }}
        </p>
        <p
            v-if="error"
            :id="errorId"
            class="flex items-start gap-1 text-xs font-medium text-rose-600"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
