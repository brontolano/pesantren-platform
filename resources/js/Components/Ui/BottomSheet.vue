<script setup>
import { watch, onBeforeUnmount } from 'vue'
import UiIcon from './UiIcon.vue'

/**
 * BottomSheet — overlay + panel bawah rounded-3xl (atas), transisi naik.
 * - v-model (open): boolean
 * - title: judul opsional (header dengan tombol tutup)
 * Slot: default (isi), #footer (aksi sticky bawah)
 * Tutup: klik overlay / tombol X / Esc.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: null },
})
const emit = defineEmits(['update:modelValue'])

function close() {
    emit('update:modelValue', false)
}

function onKeydown(e) {
    if (e.key === 'Escape') close()
}

watch(
    () => props.modelValue,
    (open) => {
        if (typeof document === 'undefined') return
        if (open) {
            document.addEventListener('keydown', onKeydown)
            document.body.style.overflow = 'hidden'
        } else {
            document.removeEventListener('keydown', onKeydown)
            document.body.style.overflow = ''
        }
    },
)

onBeforeUnmount(() => {
    if (typeof document === 'undefined') return
    document.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
})
</script>

<template>
    <Teleport to="body">
        <Transition name="sheet-fade">
            <div
                v-if="modelValue"
                class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 backdrop-blur-sm"
                @click.self="close"
            >
                <Transition name="sheet-slide" appear>
                    <div
                        v-if="modelValue"
                        class="w-full max-w-md rounded-t-3xl bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl"
                        role="dialog"
                        aria-modal="true"
                    >
                        <div class="flex justify-center pt-3">
                            <span class="h-1.5 w-10 rounded-full bg-slate-200" />
                        </div>

                        <div
                            v-if="title || $slots.header"
                            class="flex items-center justify-between gap-3 px-5 pt-3 pb-2"
                        >
                            <h3 class="text-base font-bold text-slate-900">
                                <slot name="header">{{ title }}</slot>
                            </h3>
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                                aria-label="Tutup"
                                @click="close"
                            >
                                <UiIcon name="x-circle" class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="max-h-[70vh] overflow-y-auto px-5 pb-5 pt-1">
                            <slot />
                        </div>

                        <div v-if="$slots.footer" class="border-t border-slate-100 px-5 py-4">
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.sheet-fade-enter-active,
.sheet-fade-leave-active {
    transition: opacity 0.2s ease;
}
.sheet-fade-enter-from,
.sheet-fade-leave-to {
    opacity: 0;
}
.sheet-slide-enter-active {
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.sheet-slide-leave-active {
    transition: transform 0.2s ease-in;
}
.sheet-slide-enter-from,
.sheet-slide-leave-to {
    transform: translateY(100%);
}
</style>
