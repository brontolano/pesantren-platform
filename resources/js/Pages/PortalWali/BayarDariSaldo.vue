<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

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

        <section class="rounded-[2rem] bg-slate-950 px-5 py-6 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Bayar dari Saldo
            </p>
            <p class="mt-3 text-sm text-white/75">
                {{ child.nama }} · {{ child.kelas || 'Belum ada kelas' }}
            </p>
            <p class="mt-4 text-sm text-white/60">
                Saldo tabungan
            </p>
            <p class="mt-1 font-serif text-3xl leading-tight">
                {{ rupiah(saldo) }}
            </p>
        </section>

        <section class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
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
            <p class="mt-3 text-sm text-slate-600">
                <span class="font-semibold text-slate-900">Sisa tagihan:</span> {{ rupiah(sisaTagihan) }}
            </p>
        </section>

        <div
            v-if="!hasSaldo"
            class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900"
        >
            Saldo tabungan masih kosong. Lakukan setor saldo terlebih dahulu untuk membayar tagihan dari saldo.
        </div>

        <form
            class="space-y-3 rounded-[1.75rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
            @submit.prevent="submit"
        >
            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Nominal bayar
                <input
                    v-model="form.nominal"
                    type="number"
                    min="1"
                    :max="maxNominal"
                    inputmode="numeric"
                    class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                    :disabled="!hasSaldo"
                >
                <span v-if="form.errors.nominal" class="text-xs font-normal text-rose-600">{{ form.errors.nominal }}</span>
            </label>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                <div class="flex items-center justify-between text-slate-600">
                    <span>Saldo sebelum</span>
                    <span class="font-semibold text-slate-900">{{ rupiah(saldo) }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between text-slate-600">
                    <span>Nominal bayar</span>
                    <span class="font-semibold text-rose-700">- {{ rupiah(form.nominal || 0) }}</span>
                </div>
                <div class="mt-2 border-t border-dashed border-slate-200 pt-2 flex items-center justify-between text-slate-600">
                    <span>Saldo sesudah</span>
                    <span
                        class="font-semibold"
                        :class="saldoSetelahBayar < 0 ? 'text-rose-700' : 'text-emerald-700'"
                    >{{ rupiah(saldoSetelahBayar) }}</span>
                </div>
            </div>

            <button
                type="submit"
                class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-white disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!hasSaldo || !nominalValid || form.processing"
            >
                Konfirmasi Bayar dari Saldo
            </button>
        </form>
    </div>
</template>
