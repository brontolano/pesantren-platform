<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../../Layouts/PortalWaliLayout.vue'
import {
    UiCard,
    SectionCard,
    UiField,
    UiInput,
    UiButton,
    Chip,
    IconCircle,
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
    savings: { type: Object, required: true },
    paymentMethods: { type: Array, default: () => [] },
    presets: { type: Array, default: () => [50000, 100000, 200000, 500000] },
    nominalMin: { type: Number, default: 10000 },
    nominalMax: { type: Number, default: 10000000 },
})

const rupiah = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0))

const saldo = computed(() => Number(props.savings?.saldo || 0))

// Ikon per kategori metode (selaras pustaka UiIcon).
const CATEGORY_ICON = {
    VA: 'bank',
    EWALLET: 'wallet',
    QRIS: 'qr',
    RETAIL: 'cash',
    CARD: 'card',
}

const form = useForm({
    nominal: '',
    metode: '',
})

const nominalNumber = computed(() => Number(form.nominal || 0))
const nominalValid = computed(
    () => nominalNumber.value >= props.nominalMin && nominalNumber.value <= props.nominalMax,
)
const canSubmit = computed(() => nominalValid.value && !!form.metode)

const selectedMethod = computed(() => {
    for (const group of props.paymentMethods) {
        const found = (group.methods || []).find((m) => m.code === form.metode)
        if (found) return found
    }
    return null
})

function pickPreset(value) {
    form.nominal = String(value)
    if (form.errors.nominal) form.clearErrors('nominal')
}

function pickMethod(code) {
    form.metode = code
    if (form.errors.metode) form.clearErrors('metode')
}

// Inisial badge metode (maks 4 huruf, tanpa aset eksternal).
function methodInitials(method) {
    const src = (method.code || method.name || '').toString()
    return src.slice(0, 4).toUpperCase()
}

function submit() {
    form.post(`/wali/santri/${props.child.id}/topup`, {
        preserveScroll: false,
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Top Up Tabungan</title>
        </Head>

        <!-- HERO -->
        <UiCard variant="hero-dark" padding="lg">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                        Top Up Tabungan
                    </p>
                    <p class="mt-2 truncate text-sm text-white/80">
                        {{ child.nama }} · {{ child.kelas || 'Belum ada kelas' }}
                    </p>
                </div>
                <IconCircle icon="wallet" variant="glass" size="md" />
            </div>
            <p class="mt-5 text-[11px] font-bold uppercase tracking-wide text-white/60">
                Saldo saat ini
            </p>
            <MoneyText :amount="saldo" size="xl" tone="inherit" class="mt-1 block text-white" />
            <p class="mt-3 text-xs leading-5 text-white/70">
                Pilih nominal lalu metode pembayaran. Saldo bertambah setelah pembayaran dikonfirmasi.
            </p>
        </UiCard>

        <!-- NOMINAL -->
        <SectionCard kicker="Langkah 1" title="Nominal Top Up">
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <Chip
                        v-for="value in presets"
                        :key="value"
                        :active="nominalNumber === Number(value)"
                        type="button"
                        @click="pickPreset(value)"
                    >
                        {{ rupiah(value) }}
                    </Chip>
                </div>

                <UiField
                    label="Atau masukkan nominal sendiri"
                    :error="form.errors.nominal"
                    :hint="`Minimal ${rupiah(nominalMin)} · maksimal ${rupiah(nominalMax)}`"
                >
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            v-model="form.nominal"
                            type="number"
                            :min="nominalMin"
                            :max="nominalMax"
                            inputmode="numeric"
                            placeholder="0"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>

                <div
                    v-if="nominalNumber > 0"
                    class="flex items-center justify-between rounded-2xl bg-[#f6f0e6] px-4 py-3 ring-1 ring-[var(--wali-border,#efe7da)]"
                >
                    <span class="text-sm font-semibold text-slate-600">Akan menambah saldo</span>
                    <MoneyText :amount="nominalNumber" size="lg" tone="emerald" sign="plus" />
                </div>
            </div>
        </SectionCard>

        <!-- KATALOG METODE -->
        <section class="space-y-4">
            <div class="px-0.5">
                <p class="inline-flex items-center gap-1.5 font-jost text-[11px] font-semibold uppercase tracking-wider text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-[var(--wali-primary,#059669)]" />
                    Langkah 2
                </p>
                <h3 class="mt-1 font-jost text-lg font-bold leading-tight tracking-tight text-slate-900">
                    Pilih Metode Pembayaran
                </h3>
                <p v-if="form.errors.metode" class="mt-1 text-xs font-medium text-rose-600" role="alert">
                    {{ form.errors.metode }}
                </p>
            </div>

            <div v-for="group in paymentMethods" :key="group.category" class="space-y-2.5">
                <p class="px-1 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                    {{ group.label }}
                </p>
                <div class="grid gap-2.5">
                    <button
                        v-for="method in group.methods"
                        :key="method.code"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-3xl bg-white px-4 py-3.5 text-left shadow-soft ring-1 transition-all duration-200 active:scale-[.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--wali-primary,#059669)]"
                        :class="form.metode === method.code
                            ? 'ring-2 ring-[var(--wali-primary,#059669)]'
                            : 'ring-[var(--wali-border,#efe7da)] hover:ring-slate-300'"
                        :aria-pressed="form.metode === method.code"
                        @click="pickMethod(method.code)"
                    >
                        <!-- Badge inisial berwarna dari method.color (tanpa aset eksternal) -->
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-[11px] font-extrabold tracking-tight text-white"
                            :style="{ backgroundColor: method.color || '#059669' }"
                        >
                            {{ methodInitials(method) }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <IconCircle
                                    :icon="CATEGORY_ICON[method.category] || 'card'"
                                    tone="slate"
                                    size="sm"
                                    class="!h-6 !w-6 !rounded-lg"
                                />
                                <span class="truncate text-[15px] font-bold tracking-tight text-slate-900">
                                    {{ method.name }}
                                </span>
                            </span>
                            <span class="mt-0.5 block text-xs font-medium text-slate-500">
                                {{ method.fee > 0 ? `Biaya admin ${rupiah(method.fee)}` : 'Gratis biaya admin' }}
                            </span>
                        </span>

                        <!-- Radio indikator -->
                        <span
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
                            :class="form.metode === method.code
                                ? 'border-[var(--wali-primary,#059669)] bg-[var(--wali-primary,#059669)]'
                                : 'border-slate-300 bg-white'"
                            aria-hidden="true"
                        >
                            <span v-if="form.metode === method.code" class="h-2 w-2 rounded-full bg-white" />
                        </span>
                    </button>
                </div>
            </div>
        </section>

        <!-- RINGKASAN + CTA (sticky) -->
        <div class="sticky bottom-24 z-10 space-y-3">
            <UiCard v-if="selectedMethod && nominalNumber > 0" padding="sm">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-500">Metode</span>
                    <span class="font-bold text-slate-900">{{ selectedMethod.name }}</span>
                </div>
                <div class="mt-1.5 flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-500">Total bayar</span>
                    <MoneyText :amount="nominalNumber + Number(selectedMethod.fee || 0)" size="md" />
                </div>
                <p v-if="Number(selectedMethod.fee || 0) > 0" class="mt-1 text-right text-[11px] text-slate-400">
                    Termasuk biaya admin {{ rupiah(selectedMethod.fee) }} (saldo bertambah {{ rupiah(nominalNumber) }})
                </p>
            </UiCard>

            <UiButton
                variant="primary"
                size="lg"
                block
                type="button"
                :disabled="!canSubmit"
                :loading="form.processing"
                icon-right="chevron-right"
                @click="submit"
            >
                Lanjut Bayar
            </UiButton>

            <p class="text-center text-[11px] font-medium text-slate-400">
                Mode demo — pembayaran disimulasikan, tidak ada transaksi nyata.
            </p>
        </div>
    </div>
</template>
