<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../../Layouts/PortalWaliLayout.vue'
import {
    UiCard,
    UiButton,
    MoneyText,
} from '@/Components/Ui'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    wali: { type: Object, default: () => ({}) },
    children: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
    child: { type: Object, required: true },
    topup: { type: Object, required: true },
    saldoBaru: { type: [Number, String], default: 0 },
    tabunganUrl: { type: String, required: true },
})

const rupiah = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0))

const mutasiUrl = computed(() => `/wali/santri/${props.child.id}/tabungan`)
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Top Up Berhasil</title>
        </Head>

        <!-- KARTU SUKSES -->
        <UiCard padding="lg" class="text-center">
            <div class="flex flex-col items-center">
                <!-- Badge sukses gradient -->
                <span class="relative flex h-20 w-20 items-center justify-center">
                    <span aria-hidden="true" class="absolute inset-0 rounded-full bg-emerald-100/70 blur-xl" />
                    <span class="relative flex h-20 w-20 items-center justify-center rounded-full bg-brand-grad text-white shadow-brand ring-4 ring-white success-pop">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-9 w-9">
                            <path d="m4.5 12.75 6 6 9-13.5" class="check-draw" />
                        </svg>
                    </span>
                </span>

                <h2 class="mt-5 font-jost text-2xl font-extrabold tracking-tight text-slate-900">
                    Top Up Berhasil
                </h2>
                <p class="mt-1.5 text-sm text-slate-500">
                    Saldo tabungan {{ child.nama }} sudah bertambah.
                </p>

                <!-- Nominal top up -->
                <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 ring-1 ring-inset ring-emerald-100">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Nominal</span>
                    <MoneyText :amount="topup.nominal" size="md" tone="emerald" sign="plus" />
                </div>
            </div>
        </UiCard>

        <!-- SALDO BARU -->
        <UiCard variant="hero-dark" padding="lg" class="text-center">
            <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                Saldo Tabungan Sekarang
            </p>
            <MoneyText :amount="saldoBaru" size="2xl" tone="inherit" class="mt-2 block text-white" />
            <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold text-white/85 ring-1 ring-inset ring-white/15">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-200" />
                Saldo siap dipakai · {{ topup.metodeName }}
            </p>
        </UiCard>

        <!-- CTA -->
        <div class="space-y-3">
            <UiButton
                as="link"
                :href="tabunganUrl"
                variant="primary"
                size="lg"
                block
                icon-left="wallet"
            >
                Kembali ke Tabungan
            </UiButton>
            <UiButton
                as="link"
                :href="mutasiUrl"
                variant="secondary"
                size="lg"
                block
                icon-left="receipt"
            >
                Lihat Mutasi
            </UiButton>
        </div>

        <p class="text-center text-[11px] font-medium text-slate-400">
            Mode demo: pembayaran disimulasikan.
        </p>
    </div>
</template>

<style scoped>
.success-pop {
    animation: success-pop 0.45s cubic-bezier(0.18, 0.89, 0.32, 1.28) both;
}
@keyframes success-pop {
    0% { transform: scale(0.4); opacity: 0; }
    60% { transform: scale(1.08); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}
.check-draw {
    stroke-dasharray: 30;
    stroke-dashoffset: 30;
    animation: check-draw 0.5s ease-out 0.25s forwards;
}
@keyframes check-draw {
    to { stroke-dashoffset: 0; }
}
@media (prefers-reduced-motion: reduce) {
    .success-pop,
    .check-draw {
        animation: none;
    }
    .check-draw {
        stroke-dashoffset: 0;
    }
}
</style>
