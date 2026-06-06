<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

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

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Pembayaran saya
            </p>
            <h2 class="mt-3 font-serif text-3xl leading-tight">
                Status Pembayaran
            </h2>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-[1.4rem] border border-white/10 bg-white/8 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/55">
                        Menunggu
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-amber-300">
                        {{ pendingCount }}
                    </p>
                </div>
                <div class="rounded-[1.4rem] border border-white/10 bg-white/8 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/55">
                        Terverifikasi
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-300">
                        {{ verifiedCount }}
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Riwayat
                </p>
                <h3 class="mt-1 font-serif text-2xl text-slate-900">
                    Pembayaran Terkirim
                </h3>
            </div>

            <div v-if="payments.length" class="grid gap-3">
                <article
                    v-for="payment in payments"
                    :key="payment.id"
                    class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4 class="text-lg font-semibold text-slate-900">
                                {{ payment.tagihan || 'Pembayaran' }}
                            </h4>
                            <p v-if="payment.santriNama" class="mt-1 text-sm text-slate-500">
                                {{ payment.santriNama }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-3 py-1 text-[11px] font-semibold"
                            :class="badgeFor(payment).class"
                        >
                            {{ badgeFor(payment).label }}
                        </span>
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                {{ metodeLabel[payment.metode] || payment.metode || 'Pembayaran' }}
                            </p>
                            <p class="mt-1 text-xl font-semibold text-slate-900">
                                {{ rupiah(payment.nominal) }}
                            </p>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ payment.tanggalPembayaran || '-' }}
                        </p>
                    </div>

                    <p
                        v-if="payment.alasanPenolakan"
                        class="mt-3 rounded-2xl bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800"
                    >
                        <span class="font-semibold">Alasan ditolak:</span>
                        {{ payment.alasanPenolakan }}
                    </p>
                </article>
            </div>

            <div
                v-else
                class="rounded-[1.5rem] border border-dashed border-slate-200 bg-white px-5 py-8 text-center"
            >
                <p class="text-base font-semibold text-slate-700">
                    Belum ada pembayaran
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    Pembayaran yang Anda kirim akan muncul di sini beserta statusnya.
                </p>
            </div>
        </section>
    </div>
</template>
