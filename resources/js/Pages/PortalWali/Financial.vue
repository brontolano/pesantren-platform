<script setup>
import { Head } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import {
    UiCard,
    SectionCard,
    ListRow,
    StatusBadge,
    MoneyText,
    UiButton,
    EmptyState,
} from '@/Components/Ui'

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

        <UiCard variant="hero-dark">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70">
                Keuangan
            </p>
            <h2 class="mt-2 text-2xl font-bold leading-tight">
                {{ child.nama }}
            </h2>
            <p class="mt-1 text-sm text-white/75">
                {{ child.kelas || 'Belum ada kelas' }}
            </p>
        </UiCard>

        <section id="tabungan" class="grid scroll-mt-36 grid-cols-2 gap-3">
            <UiCard padding="sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Outstanding
                </p>
                <MoneyText
                    :amount="summary.totalOutstanding"
                    size="xl"
                    class="mt-2 block"
                />
            </UiCard>
            <UiCard padding="sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Saldo Tabungan
                </p>
                <MoneyText
                    :amount="savings.saldo"
                    size="xl"
                    tone="emerald"
                    class="mt-2 block"
                />
            </UiCard>
        </section>

        <SectionCard
            id="tagihan"
            class="scroll-mt-36"
            kicker="Tagihan"
            title="Laporan Tagihan"
        >
            <div v-if="bills.length" class="space-y-3">
                <UiCard
                    v-for="bill in bills"
                    :key="bill.id"
                    padding="sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4 class="text-[15px] font-semibold text-slate-900">
                                {{ bill.jenisTagihan }}
                            </h4>
                            <p class="mt-0.5 text-sm text-slate-500">
                                {{ bill.keterangan || '-' }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <StatusBadge :status="bill.status" size="sm" />
                            <MoneyText
                                :amount="bill.nominalAkhir"
                                size="md"
                                class="mt-1.5 block"
                            />
                        </div>
                    </div>

                    <dl class="mt-3 grid grid-cols-3 gap-2 border-t border-slate-100 pt-3">
                        <div>
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                Sisa
                            </dt>
                            <dd class="mt-0.5">
                                <MoneyText :amount="bill.sisaTagihan" size="sm" tone="rose" />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                Dibayar
                            </dt>
                            <dd class="mt-0.5">
                                <MoneyText :amount="bill.totalDibayar" size="sm" tone="emerald" />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                Jatuh tempo
                            </dt>
                            <dd class="mt-0.5 text-sm font-semibold tabular-nums text-slate-700">
                                {{ bill.jatuhTempo || '-' }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="bill.status !== 'lunas'" class="mt-4 flex gap-2">
                        <UiButton
                            variant="primary"
                            as="link"
                            :href="`/wali/santri/${child.id}/tagihan/${bill.id}/bayar`"
                            block
                            icon-left="card"
                        >
                            Bayar
                        </UiButton>
                        <UiButton
                            variant="secondary"
                            as="link"
                            :href="`/wali/santri/${child.id}/tabungan`"
                            block
                            icon-left="wallet"
                        >
                            Dari Saldo
                        </UiButton>
                    </div>
                </UiCard>
            </div>

            <EmptyState
                v-else
                tone="emerald"
                icon="check-circle"
                title="Tidak ada tagihan"
                message="Semua tagihan anak Anda sudah lunas."
            />
        </SectionCard>

        <SectionCard
            kicker="Pembayaran"
            title="Riwayat Pembayaran"
            flush
        >
            <div v-if="payments.length" class="divide-y divide-slate-100">
                <ListRow
                    v-for="payment in payments"
                    :key="payment.id"
                    :title="payment.tagihan || 'Pembayaran'"
                    :subtitle="`${payment.tanggalPembayaran || '-'} · ${payment.metode || '-'}`"
                >
                    <template #trailing>
                        <MoneyText :amount="payment.nominal" tone="emerald" size="md" />
                    </template>
                </ListRow>
            </div>

            <EmptyState
                v-else
                icon="receipt"
                title="Belum ada pembayaran"
                message="Riwayat pembayaran untuk anak ini akan tampil di sini."
            />
        </SectionCard>
    </div>
</template>
