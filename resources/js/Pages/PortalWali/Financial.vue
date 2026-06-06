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
    StatCard,
    IconCircle,
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

        <UiCard variant="hero-dark" padding="lg">
            <div class="flex items-start gap-3.5">
                <IconCircle icon="user" variant="glass" size="lg" />
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-white/70">
                        <span class="h-1.5 w-1.5 rounded-full bg-white/80" />
                        Keuangan
                    </p>
                    <h2 class="mt-1.5 truncate text-2xl font-extrabold leading-tight tracking-tight">
                        {{ child.nama }}
                    </h2>
                    <p class="mt-0.5 truncate text-sm text-white/75">
                        {{ child.kelas || 'Belum ada kelas' }}
                    </p>
                </div>
            </div>

            <div class="mt-5 rounded-2xl bg-white/10 px-4 py-4 ring-1 ring-inset ring-white/15">
                <p class="text-[11px] font-bold uppercase tracking-wider text-white/70">
                    Total kewajiban
                </p>
                <MoneyText
                    :amount="summary.totalOutstanding"
                    size="xl"
                    tone="inherit"
                    class="mt-1.5 block text-white"
                />
            </div>
        </UiCard>

        <section id="tabungan" class="grid scroll-mt-36 grid-cols-2 gap-3">
            <StatCard label="Outstanding" icon="receipt" tone="rose" hint="Belum terbayar">
                <template #value>
                    <MoneyText :amount="summary.totalOutstanding" size="lg" tone="rose" />
                </template>
            </StatCard>
            <StatCard label="Saldo Tabungan" icon="wallet" tone="emerald" hint="Siap dipakai">
                <template #value>
                    <MoneyText :amount="savings.saldo" size="lg" tone="emerald" />
                </template>
            </StatCard>
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
                        <div class="flex min-w-0 items-start gap-3">
                            <IconCircle icon="receipt" tone="slate" size="md" />
                            <div class="min-w-0">
                                <h4 class="text-[15px] font-bold tracking-tight text-slate-900">
                                    {{ bill.jenisTagihan }}
                                </h4>
                                <p class="mt-0.5 text-sm text-slate-500">
                                    {{ bill.keterangan || '-' }}
                                </p>
                            </div>
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
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Sisa
                            </dt>
                            <dd class="mt-0.5">
                                <MoneyText :amount="bill.sisaTagihan" size="sm" tone="rose" />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Dibayar
                            </dt>
                            <dd class="mt-0.5">
                                <MoneyText :amount="bill.totalDibayar" size="sm" tone="emerald" />
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Jatuh tempo
                            </dt>
                            <dd class="mt-0.5 text-sm font-bold tabular-nums text-slate-700">
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
                    <template #leading>
                        <IconCircle icon="check-circle" tone="emerald" size="md" />
                    </template>
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
