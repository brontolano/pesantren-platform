<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import {
    UiCard,
    SectionCard,
    ListRow,
    StatusBadge,
    MoneyText,
    EmptyState,
} from '@/Components/Ui'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    payments: {
        type: Array,
        default: () => [],
    },
})

const rupiah = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0))

const metodeLabel = {
    tunai: 'Tunai',
    transfer: 'Transfer',
    qris: 'QRIS',
    saldo_tabungan: 'Saldo Tabungan',
}

function badgeFor(payment) {
    if (payment.alasanPenolakan) {
        return { label: 'Ditolak', class: 'bg-rose-100 text-rose-700' }
    }
    if (payment.isVerified) {
        return { label: 'Terverifikasi', class: 'bg-emerald-100 text-emerald-700' }
    }
    return { label: 'Menunggu verifikasi', class: 'bg-amber-100 text-amber-700' }
}

const verifiedCount = computed(() => props.payments.filter((p) => p.isVerified).length)
const pendingCount = computed(() => props.payments.filter((p) => !p.isVerified && !p.alasanPenolakan).length)
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Pembayaran Saya</title>
        </Head>

        <UiCard variant="hero-dark">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70">
                Pembayaran saya
            </p>
            <h2 class="mt-2 text-2xl font-bold leading-tight">
                Status Pembayaran
            </h2>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-inset ring-white/15">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">
                        Menunggu
                    </p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-amber-300">
                        {{ pendingCount }}
                    </p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-inset ring-white/15">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">
                        Terverifikasi
                    </p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-emerald-300">
                        {{ verifiedCount }}
                    </p>
                </div>
            </div>
        </UiCard>

        <SectionCard
            kicker="Riwayat"
            title="Pembayaran Terkirim"
        >
            <div v-if="payments.length" class="space-y-3">
                <UiCard
                    v-for="payment in payments"
                    :key="payment.id"
                    padding="sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4 class="text-[15px] font-semibold text-slate-900">
                                {{ payment.tagihan || 'Pembayaran' }}
                            </h4>
                            <p v-if="payment.santriNama" class="mt-0.5 text-sm text-slate-500">
                                {{ payment.santriNama }}
                            </p>
                        </div>
                        <StatusBadge
                            class="shrink-0"
                            size="sm"
                            :status="payment.alasanPenolakan
                                ? 'ditolak'
                                : (payment.isVerified ? 'terverifikasi' : 'menunggu')"
                        />
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-3 border-t border-slate-100 pt-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                {{ metodeLabel[payment.metode] || payment.metode || 'Pembayaran' }}
                            </p>
                            <MoneyText :amount="payment.nominal" size="lg" class="mt-1 block" />
                        </div>
                        <p class="text-xs font-medium tabular-nums text-slate-500">
                            {{ payment.tanggalPembayaran || '-' }}
                        </p>
                    </div>

                    <p
                        v-if="payment.alasanPenolakan"
                        class="mt-3 rounded-xl bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-700 ring-1 ring-inset ring-rose-100"
                    >
                        <span class="font-semibold">Alasan ditolak:</span>
                        {{ payment.alasanPenolakan }}
                    </p>
                </UiCard>
            </div>

            <EmptyState
                v-else
                icon="inbox"
                title="Belum ada pembayaran"
                message="Pembayaran yang Anda kirim akan muncul di sini beserta statusnya."
            />
        </SectionCard>
    </div>
</template>
