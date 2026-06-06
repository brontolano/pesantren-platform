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
        ? { ring: 'ring-rose-100', iconWrap: 'bg-rose-50 text-rose-600', icon: 'x-circle', bar: 'bg-gradient-to-b from-rose-400 to-rose-600' }
        : { ring: 'ring-emerald-100', iconWrap: 'bg-emerald-50 text-emerald-600', icon: 'check-circle', bar: 'bg-brand-grad' },
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
                    class="relative flex w-full items-start gap-3 overflow-hidden rounded-2xl bg-white/90 py-3 pl-4 pr-3 shadow-soft-lg ring-1 backdrop-blur-xl"
                    :class="tone.ring"
                    role="status"
                    aria-live="polite"
                >
                    <span aria-hidden="true" class="absolute inset-y-2 left-0 w-1 rounded-full" :class="tone.bar" />
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl" :class="tone.iconWrap">
                        <UiIcon :name="tone.icon" class="h-5 w-5" />
                    </span>
                    <p class="flex-1 pt-1 text-sm font-semibold leading-5 text-slate-800">
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
