<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import { UiCard, StatusBadge, MoneyText, UiField, UiInput, UiButton } from '@/Components/Ui'

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

const rupiah = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0))

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
        <UiCard variant="hero-dark">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70">
                Bayar dari Saldo
            </p>
            <p class="mt-2 text-sm text-white/80">
                {{ child.nama }} · {{ child.kelas || 'Belum ada kelas' }}
            </p>
            <p class="mt-4 text-[11px] font-semibold uppercase tracking-wide text-white/60">
                Saldo tabungan
            </p>
            <MoneyText :amount="saldo" size="xl" tone="inherit" class="mt-1 block text-white" />
        </UiCard>

        <!-- KARTU TAGIHAN -->
        <UiCard padding="sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h4 class="text-base font-bold text-slate-900">
                        {{ bill.jenisTagihan }}
                    </h4>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ bill.keterangan || '-' }}
                    </p>
                </div>
                <StatusBadge :status="bill.status" size="sm" class="shrink-0" />
            </div>
            <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Sisa tagihan</span>
                <MoneyText :amount="sisaTagihan" size="lg" />
            </div>
        </UiCard>

        <!-- BANNER SALDO KOSONG -->
        <UiCard v-if="!hasSaldo" variant="muted" padding="sm">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                </span>
                <p class="text-sm text-amber-900">
                    Saldo tabungan masih kosong. Lakukan setor saldo terlebih dahulu untuk membayar tagihan dari saldo.
                </p>
            </div>
        </UiCard>

        <!-- FORM BAYAR -->
        <UiCard padding="sm">
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
                <div class="rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-100">
                    <div class="flex items-center justify-between text-sm text-slate-600">
                        <span>Saldo sebelum</span>
                        <MoneyText :amount="saldo" size="sm" />
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm text-slate-600">
                        <span>Nominal bayar</span>
                        <MoneyText :amount="form.nominal || 0" size="sm" tone="rose" sign="minus" />
                    </div>
                    <div class="mt-2 flex items-center justify-between border-t border-dashed border-slate-200 pt-2 text-sm text-slate-600">
                        <span>Saldo sesudah</span>
                        <MoneyText
                            :amount="saldoSetelahBayar"
                            size="sm"
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
