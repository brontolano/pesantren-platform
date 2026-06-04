<script setup>
import { Head } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

defineOptions({
    layout: PortalWaliLayout,
})

defineProps({
    child: {
        type: Object,
        required: true,
    },
    bills: {
        type: Array,
        default: () => [],
    },
    payments: {
        type: Array,
        default: () => [],
    },
    savings: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Keuangan Santri</title>
        </Head>

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Keuangan
            </p>
            <h2 class="mt-3 font-serif text-3xl leading-tight">
                {{ child.nama }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-white/75">
                {{ child.kelas || 'Belum ada kelas' }}
            </p>
        </section>

        <section class="grid grid-cols-2 gap-3">
            <div class="rounded-[1.5rem] bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                    Outstanding
                </p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ summary.totalOutstanding }}
                </p>
            </div>
            <div class="rounded-[1.5rem] bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                    Saldo Tabungan
                </p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ savings.saldo }}
                </p>
            </div>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Tagihan
                </p>
                <h3 class="mt-1 font-serif text-2xl text-slate-900">
                    Laporan Tagihan
                </h3>
            </div>

            <div v-if="bills.length" class="grid gap-3">
                <article
                    v-for="bill in bills"
                    :key="bill.id"
                    class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-lg font-semibold text-slate-900">
                                {{ bill.jenisTagihan }}
                            </h4>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ bill.keterangan || '-' }}
                            </p>
                        </div>
                        <div class="rounded-full bg-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-700">
                            {{ bill.status }}
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-slate-600">
                        <p><span class="font-semibold text-slate-900">Nominal:</span> {{ bill.nominalAkhir }}</p>
                        <p><span class="font-semibold text-slate-900">Sisa:</span> {{ bill.sisaTagihan }}</p>
                        <p><span class="font-semibold text-slate-900">Dibayar:</span> {{ bill.totalDibayar }}</p>
                        <p><span class="font-semibold text-slate-900">Jatuh tempo:</span> {{ bill.jatuhTempo || '-' }}</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Pembayaran
                </p>
                <h3 class="mt-1 font-serif text-2xl text-slate-900">
                    Riwayat Pembayaran
                </h3>
            </div>

            <div v-if="payments.length" class="grid gap-3">
                <article
                    v-for="payment in payments"
                    :key="payment.id"
                    class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 text-sm shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <p class="font-semibold text-slate-900">
                        {{ payment.tagihan || 'Pembayaran' }}
                    </p>
                    <p class="mt-1 text-slate-500">
                        {{ payment.tanggalPembayaran || '-' }} · {{ payment.metode || '-' }}
                    </p>
                    <p class="mt-2 font-semibold text-emerald-700">
                        {{ payment.nominal }}
                    </p>
                </article>
            </div>
        </section>
    </div>
</template>
