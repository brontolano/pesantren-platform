<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import UiIcon from './UiIcon.vue'

/**
 * FlashToast — toast sukses/error dari flash session Laravel.
 * Defensif: membaca beberapa kemungkinan lokasi prop tanpa mengubah backend.
 *   page.props.flash.success / .error  ATAU  page.props.success / .error
 * Muncul otomatis saat flash berubah, hilang setelah `duration` ms.
 * Murni presentasional; tidak menyentuh route/endpoint.
 */
const props = defineProps({
    duration: { type: Number, default: 4000 },
})

const page = usePage()

function pick(...candidates) {
    for (const c of candidates) {
        if (typeof c === 'string' && c.trim()) return c
    }
    return null
}

const successMsg = computed(() =>
    pick(page.props.flash?.success, page.props.success),
)
const errorMsg = computed(() =>
    pick(page.props.flash?.error, page.props.error, page.props.flash?.danger),
)

const visible = ref(false)
const current = ref({ type: 'success', message: '' })
let timer = null

function show(type, message) {
    current.value = { type, message }
    visible.value = true
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => (visible.value = false), props.duration)
}

watch(
    [successMsg, errorMsg],
    ([ok, err]) => {
        if (err) show('error', err)
        else if (ok) show('success', ok)
    },
    { immediate: true },
)

const tone = computed(() =>
    current.value.type === 'error'
        ? { ring: 'ring-rose-100', bg: 'bg-white', icon: 'x-circle', color: 'text-rose-600', bar: 'bg-rose-500' }
        : { ring: 'ring-emerald-100', bg: 'bg-white', icon: 'check-circle', color: 'text-emerald-600', bar: 'bg-emerald-500' },
)

function dismiss() {
    visible.value = false
    if (timer) clearTimeout(timer)
}
</script>

<template>
    <Teleport to="body">
        <Transition name="toast-pop">
            <div
                v-if="visible"
                class="fixed inset-x-0 top-3 z-[60] mx-auto flex w-full max-w-md justify-center px-4"
            >
                <div
                    class="flex w-full items-start gap-3 overflow-hidden rounded-2xl py-3 pl-3 pr-3 shadow-lg ring-1"
                    :class="[tone.bg, tone.ring]"
                    role="status"
                    aria-live="polite"
                >
                    <span class="mt-0.5 shrink-0" :class="tone.color">
                        <UiIcon :name="tone.icon" class="h-5 w-5" />
                    </span>
                    <p class="flex-1 pt-0.5 text-sm font-medium leading-5 text-slate-800">
                        {{ current.message }}
                    </p>
                    <button
                        type="button"
                        class="-mr-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                        aria-label="Tutup notifikasi"
                        @click="dismiss"
                    >
                        <UiIcon name="x-circle" class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.toast-pop-enter-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-pop-leave-active {
    transition: all 0.2s ease-in;
}
.toast-pop-enter-from,
.toast-pop-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}
</style>
