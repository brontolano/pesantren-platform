<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import { UiCard, StatusBadge, MoneyText, UiField, UiInput, UiButton, IconCircle } from '@/Components/Ui'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    wali: {
        type: Object,
        default: () => ({}),
    },
    children: {
        type: Array,
        default: () => [],
    },
    navigation: {
        type: Array,
        default: () => [],
    },
    child: {
        type: Object,
        required: true,
    },
    savings: {
        type: Object,
        required: true,
    },
    bill: {
        type: Object,
        required: true,
    },
})

import { formatRupiah as rupiah } from '@/Shared/format'

const saldo = computed(() => Number(props.savings?.saldo || 0))
const sisaTagihan = computed(() => Number(props.bill?.sisaTagihan || 0))
const hasSaldo = computed(() => saldo.value > 0)
const maxNominal = computed(() => Math.min(saldo.value, sisaTagihan.value))

const form = useForm({
    nominal: Math.min(sisaTagihan.value, saldo.value) || '',
})

const saldoSetelahBayar = computed(() => saldo.value - Number(form.nominal || 0))

const nominalValid = computed(() => {
    const nominal = Number(form.nominal || 0)
    return nominal > 0 && nominal <= saldo.value && nominal <= sisaTagihan.value
})

function submit() {
    form.post(`/wali/santri/${props.child.id}/tagihan/${props.bill.id}/bayar-saldo`, {
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Bayar dari Saldo</title>
        </Head>

        <!-- HERO -->
        <UiCard variant="hero-dark" padding="lg">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                        Bayar dari Saldo
                    </p>
                    <p class="mt-2 truncate text-sm text-white/80">
                        {{ child.nama }} · {{ child.kelas || 'Belum ada kelas' }}
                    </p>
                </div>
                <IconCircle icon="wallet" variant="glass" size="md" />
            </div>
            <p class="mt-5 text-[11px] font-bold uppercase tracking-wide text-white/60">
                Saldo tabungan
            </p>
            <MoneyText :amount="saldo" size="xl" tone="inherit" class="mt-1 block text-white" />
        </UiCard>

        <!-- KARTU TAGIHAN -->
        <UiCard padding="sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h4 class="text-base font-bold tracking-tight text-slate-900">
                        {{ bill.jenisTagihan }}
                    </h4>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ bill.keterangan || '-' }}
                    </p>
                </div>
                <StatusBadge :status="bill.status" size="sm" class="shrink-0" />
            </div>
            <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Sisa tagihan</span>
                <MoneyText :amount="sisaTagihan" size="lg" tone="rose" />
            </div>
        </UiCard>

        <!-- BANNER SALDO KOSONG -->
        <UiCard v-if="!hasSaldo" variant="muted" padding="sm">
            <div class="flex items-start gap-3">
                <IconCircle icon="info" tone="amber" size="md" />
                <p class="pt-0.5 text-sm font-medium text-amber-900">
                    Saldo tabungan masih kosong. Lakukan setor saldo terlebih dahulu untuk membayar tagihan dari saldo.
                </p>
            </div>
        </UiCard>

        <!-- FORM BAYAR -->
        <UiCard padding="lg">
            <form class="space-y-4" @submit.prevent="submit">
                <UiField label="Nominal bayar" :error="form.errors.nominal">
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            v-model="form.nominal"
                            type="number"
                            :min="1"
                            :max="maxNominal"
                            inputmode="numeric"
                            :disabled="!hasSaldo"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>

                <!-- Ringkasan saldo sebelum -> sesudah -->
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-100">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        Ringkasan Pembayaran
                    </p>
                    <div class="mt-3 flex items-center justify-between text-sm font-medium text-slate-600">
                        <span>Saldo sebelum</span>
                        <MoneyText :amount="saldo" size="sm" />
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm font-medium text-slate-600">
                        <span>Nominal bayar</span>
                        <MoneyText :amount="form.nominal || 0" size="sm" tone="rose" sign="minus" />
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t border-dashed border-slate-200 pt-3">
                        <span class="text-sm font-semibold text-slate-700">Saldo sesudah</span>
                        <MoneyText
                            :amount="saldoSetelahBayar"
                            size="lg"
                            :tone="saldoSetelahBayar < 0 ? 'rose' : 'emerald'"
                        />
                    </div>
                </div>

                <UiButton
                    variant="primary"
                    block
                    type="submit"
                    :disabled="!hasSaldo || !nominalValid"
                    :loading="form.processing"
                    icon-left="wallet"
                >
                    Konfirmasi Bayar dari Saldo
                </UiButton>
            </form>
        </UiCard>
    </div>
</template>
