<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import {
    UiCard,
    SectionCard,
    StatusBadge,
    MoneyText,
    EmptyState,
    StatCard,
    IconCircle,
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

        <UiCard variant="hero-dark" padding="lg">
            <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-white/70">
                <span class="h-1.5 w-1.5 rounded-full bg-white/80" />
                Pembayaran saya
            </p>
            <h2 class="mt-2.5 text-2xl font-extrabold leading-tight tracking-tight">
                Status Pembayaran
            </h2>
            <p class="mt-1 text-sm text-white/75">
                Pantau verifikasi setoran yang Anda kirim.
            </p>

            <div class="mt-5 flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-inset ring-white/15">
                <IconCircle icon="inbox" variant="glass" size="md" />
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-white/60">
                        Total terkirim
                    </p>
                    <p class="text-2xl font-extrabold tabular-nums leading-tight">
                        {{ payments.length }}
                    </p>
                </div>
            </div>
        </UiCard>

        <section class="grid grid-cols-2 gap-3">
            <StatCard label="Menunggu" icon="clock" tone="amber" :value="pendingCount" hint="Diproses admin" />
            <StatCard label="Terverifikasi" icon="check-circle" tone="emerald" :value="verifiedCount" hint="Sudah disetujui" />
        </section>

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
                            <h4 class="text-[15px] font-bold tracking-tight text-slate-900">
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
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                {{ metodeLabel[payment.metode] || payment.metode || 'Pembayaran' }}
                            </p>
                            <MoneyText :amount="payment.nominal" size="lg" class="mt-1 block" />
                        </div>
                        <p class="text-xs font-semibold tabular-nums text-slate-500">
                            {{ payment.tanggalPembayaran || '-' }}
                        </p>
                    </div>

                    <div
                        v-if="payment.alasanPenolakan"
                        class="mt-3 flex items-start gap-3 rounded-2xl bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-700 ring-1 ring-inset ring-rose-100"
                    >
                        <IconCircle icon="x-circle" tone="rose" size="sm" />
                        <p class="min-w-0">
                            <span class="font-bold">Alasan ditolak:</span>
                            {{ payment.alasanPenolakan }}
                        </p>
                    </div>
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
