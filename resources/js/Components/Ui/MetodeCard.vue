<script setup>
import { computed, ref } from 'vue'
import UiIcon from './UiIcon.vue'

/**
 * MetodeCard — kartu metode pembayaran (accordion).
 * Menampilkan: tipe (bank/ewallet/qris/tunai), nama, nomor + salin,
 * atas nama, instruksi, gambar (QR / logo).
 * - metode: objek { id, tipe, nama, nomor, atasNama, instruksi, gambarUrl }
 * - open: boolean (kontrol terbuka/tutup dari induk)
 * Event: @toggle (klik header)
 */
const props = defineProps({
    metode: { type: Object, required: true },
    open: { type: Boolean, default: false },
})
defineEmits(['toggle'])

const TIPE = {
    bank: { label: 'Transfer Bank', icon: 'bank' },
    ewallet: { label: 'E-Wallet', icon: 'wallet' },
    qris: { label: 'QRIS', icon: 'qr' },
    tunai: { label: 'Tunai', icon: 'cash' },
}
const tipe = computed(() => TIPE[props.metode.tipe] ?? { label: props.metode.tipe, icon: 'card' })

const copied = ref(false)
async function copyValue(value) {
    if (!value || typeof navigator === 'undefined' || !navigator.clipboard) return
    try {
        await navigator.clipboard.writeText(value)
        copied.value = true
        setTimeout(() => (copied.value = false), 1800)
    } catch {
        // abaikan bila clipboard tidak tersedia
    }
}
</script>

<template>
    <article class="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-100 shadow-sm">
        <button
            type="button"
            class="flex w-full items-center gap-3 px-4 py-4 text-left transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--wali-primary,#059669)]"
            :aria-expanded="open"
            @click="$emit('toggle')"
        >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                <UiIcon :name="tipe.icon" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    {{ tipe.label }}
                </p>
                <h4 class="truncate text-base font-bold text-slate-900">
                    {{ metode.nama }}
                </h4>
            </div>
            <UiIcon
                name="chevron-down"
                class="h-5 w-5 shrink-0 text-slate-400 transition-transform duration-200"
                :class="open ? 'rotate-180' : ''"
            />
        </button>

        <Transition name="metode-expand">
            <div v-if="open" class="space-y-3 border-t border-slate-100 px-4 py-4">
                <div v-if="metode.gambarUrl" class="flex justify-center">
                    <img
                        :src="metode.gambarUrl"
                        :alt="metode.nama"
                        class="max-h-56 rounded-2xl ring-1 ring-slate-100 object-contain"
                    >
                </div>

                <div v-if="metode.nomor" class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        Nomor tujuan
                    </p>
                    <div class="mt-1 flex items-center justify-between gap-3">
                        <p class="text-lg font-bold tabular-nums tracking-wide text-slate-900">
                            {{ metode.nomor }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[var(--wali-primary,#059669)] px-3 py-2 text-xs font-semibold text-white transition hover:brightness-95 active:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[var(--wali-primary,#059669)]"
                            @click="copyValue(metode.nomor)"
                        >
                            <UiIcon :name="copied ? 'check' : 'copy'" class="h-3.5 w-3.5" />
                            {{ copied ? 'Tersalin' : 'Salin' }}
                        </button>
                    </div>
                </div>

                <div v-if="metode.atasNama" class="text-sm">
                    <span class="text-slate-500">Atas nama:</span>
                    <span class="ml-1 font-semibold text-slate-900">{{ metode.atasNama }}</span>
                </div>

                <p
                    v-if="metode.instruksi"
                    class="flex gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900 ring-1 ring-amber-100"
                >
                    <UiIcon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                    <span>{{ metode.instruksi }}</span>
                </p>
            </div>
        </Transition>
    </article>
</template>

<style scoped>
.metode-expand-enter-active,
.metode-expand-leave-active {
    transition: all 0.22s ease;
    overflow: hidden;
}
.metode-expand-enter-from,
.metode-expand-leave-to {
    opacity: 0;
    max-height: 0;
}
.metode-expand-enter-to,
.metode-expand-leave-from {
    max-height: 30rem;
}
</style>
