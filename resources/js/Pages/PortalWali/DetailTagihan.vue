<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import {
    UiCard,
    StatusBadge,
    MoneyText,
    UiButton,
    EmptyState,
} from '@/Components/Ui'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    child: {
        type: Object,
        required: true,
    },
    bill: {
        type: Object,
        required: true,
    },
})

const rupiah = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0))

const statusBadge = {
    belum_bayar: { label: 'Belum dibayar', class: 'bg-rose-100 text-rose-700' },
    sebagian: { label: 'Dibayar sebagian', class: 'bg-amber-100 text-amber-700' },
    lunas: { label: 'Lunas', class: 'bg-emerald-100 text-emerald-700' },
    batal: { label: 'Dibatalkan', class: 'bg-slate-100 text-slate-600' },
}

const badge = computed(
    () => statusBadge[props.bill.status] ?? { label: props.bill.status, class: 'bg-slate-100 text-slate-600' },
)

const canPay = computed(() => props.bill.status === 'belum_bayar' || props.bill.status === 'sebagian')
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Detail Tagihan</title>
        </Head>

        <!-- HERO -->
        <UiCard variant="hero-dark" padding="lg">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70">
                        Detail tagihan
                    </p>
                    <h2 class="mt-2 text-2xl font-bold leading-tight">
                        {{ bill.jenisTagihan }}
                    </h2>
                    <p class="mt-1 text-sm text-white/80">
                        Untuk {{ child.nama }}
                    </p>
                </div>
                <StatusBadge :status="bill.status" size="sm" class="shrink-0" />
            </div>

            <div class="mt-5 rounded-2xl bg-white/10 px-4 py-4 ring-1 ring-inset ring-white/15">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70">
                    Sisa yang harus dibayar
                </p>
                <MoneyText
                    :amount="bill.sisaTagihan"
                    size="xl"
                    tone="inherit"
                    class="mt-1 block text-white"
                />
            </div>
        </UiCard>

        <!-- RINCIAN -->
        <UiCard padding="none">
            <div class="px-5 pt-5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Rincian
                </p>
            </div>
            <dl class="mt-2 divide-y divide-slate-100">
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="text-sm text-slate-500">Keterangan</dt>
                    <dd class="text-right text-sm font-medium text-slate-900">
                        {{ bill.keterangan || '-' }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="text-sm text-slate-500">Total tagihan</dt>
                    <dd class="text-right">
                        <MoneyText :amount="bill.nominalAkhir" size="sm" />
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="text-sm text-slate-500">Sudah dibayar</dt>
                    <dd class="text-right">
                        <MoneyText :amount="bill.totalDibayar" size="sm" tone="emerald" />
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 bg-slate-50/60 px-5 py-3.5">
                    <dt class="text-sm font-semibold text-slate-700">Sisa tagihan</dt>
                    <dd class="text-right">
                        <MoneyText :amount="bill.sisaTagihan" size="lg" />
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="text-sm text-slate-500">Jatuh tempo</dt>
                    <dd class="text-right text-sm font-medium text-slate-900">
                        {{ bill.jatuhTempo || '-' }}
                    </dd>
                </div>
            </dl>
        </UiCard>

        <!-- CTA -->
        <div v-if="canPay" class="sticky bottom-24 z-10">
            <UiButton
                variant="primary"
                size="lg"
                block
                as="link"
                :href="`/wali/santri/${child.id}/tagihan/${bill.id}/cara-bayar`"
                icon-right="arrow-up-right"
            >
                Bayar Sekarang
            </UiButton>
        </div>

        <EmptyState
            v-else
            tone="emerald"
            icon="check-circle"
            title="Tagihan ini sudah selesai"
            message="Tidak ada tindakan yang perlu dilakukan."
        />
    </div>
</template>
