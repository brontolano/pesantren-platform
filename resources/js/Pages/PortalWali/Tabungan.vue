<script setup>
import { computed, ref } from 'vue'
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
    bills: {
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

const saldo = computed(() => Number(props.savings?.saldo || 0))
const transactions = computed(() => props.savings?.transactions ?? [])
const hasSaldo = computed(() => saldo.value > 0)

// --- Setor Saldo (topup) ---
const setorForm = useForm({
    nominal: '',
    tanggal_transfer: '',
    nama_pengirim: props.wali?.name ?? '',
    bukti: null,
})

function submitSetor() {
    setorForm.post(`/wali/santri/${props.child.id}/tabungan/topup`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => setorForm.reset('nominal', 'tanggal_transfer', 'bukti'),
    })
}

// --- Bayar dari Saldo ---
const bayarForm = useForm({
    nominal: '',
})
const activeBillId = ref(null)
const activeBill = computed(() =>
    props.bills.find((bill) => bill.id === activeBillId.value) ?? null,
)
const saldoSetelahBayar = computed(() => {
    const nominal = Number(bayarForm.nominal || 0)
    return saldo.value - nominal
})
const nominalBayarValid = computed(() => {
    const nominal = Number(bayarForm.nominal || 0)
    if (!activeBill.value) return false
    return nominal > 0 && nominal <= saldo.value && nominal <= Number(activeBill.value.sisaTagihan || 0)
})

function bukaBayar(bill) {
    activeBillId.value = bill.id
    bayarForm.clearErrors()
    bayarForm.nominal = Math.min(Number(bill.sisaTagihan || 0), saldo.value)
}

function tutupBayar() {
    activeBillId.value = null
    bayarForm.reset()
}

function submitBayar() {
    if (!activeBill.value) return
    bayarForm.post(`/wali/santri/${props.child.id}/tagihan/${activeBill.value.id}/bayar-saldo`, {
        preserveScroll: true,
        onSuccess: () => tutupBayar(),
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Tabungan Santri</title>
        </Head>

        <section class="rounded-[2rem] bg-slate-950 px-5 py-6 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Saldo Tabungan
            </p>
            <p class="mt-3 text-sm text-white/75">
                {{ child.nama }} · {{ child.kelas || 'Belum ada kelas' }}
            </p>
            <p class="mt-3 font-serif text-4xl leading-tight">
                {{ rupiah(saldo) }}
            </p>
        </section>

        <!-- Mutasi -->
        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Mutasi
                </p>
                <h3 class="mt-1 font-serif text-2xl text-slate-900">
                    Riwayat Tabungan
                </h3>
            </div>

            <div v-if="transactions.length" class="grid gap-3">
                <article
                    v-for="trx in transactions"
                    :key="trx.id"
                    class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span
                                class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                :class="trx.tipe === 'setor'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-rose-50 text-rose-700'"
                            >
                                {{ trx.tipe === 'setor' ? 'Setor' : 'Tarik' }}
                            </span>
                            <p class="mt-2 text-sm text-slate-500">
                                {{ trx.keterangan || '-' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ trx.tanggal || '-' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p
                                class="text-base font-semibold"
                                :class="trx.tipe === 'setor' ? 'text-emerald-700' : 'text-rose-700'"
                            >
                                {{ trx.tipe === 'setor' ? '+' : '-' }}{{ rupiah(trx.nominal) }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                Saldo: {{ rupiah(trx.saldoSesudah) }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>
            <p v-else class="rounded-[1.5rem] border border-dashed border-slate-200 bg-white/60 px-4 py-6 text-center text-sm text-slate-400">
                Belum ada mutasi tabungan.
            </p>
        </section>

        <!-- Setor Saldo -->
        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Setor Saldo
                </p>
                <h3 class="mt-1 font-serif text-2xl text-slate-900">
                    Top Up Tabungan
                </h3>
            </div>

            <form
                class="space-y-3 rounded-[1.75rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                @submit.prevent="submitSetor"
            >
                <label class="grid gap-2 text-sm font-semibold text-slate-700">
                    Nominal setor
                    <input
                        v-model="setorForm.nominal"
                        type="number"
                        min="1"
                        inputmode="numeric"
                        placeholder="0"
                        class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                    >
                    <span v-if="setorForm.errors.nominal" class="text-xs font-normal text-rose-600">{{ setorForm.errors.nominal }}</span>
                </label>

                <label class="grid gap-2 text-sm font-semibold text-slate-700">
                    Tanggal transfer
                    <input
                        v-model="setorForm.tanggal_transfer"
                        type="date"
                        class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                    >
                    <span v-if="setorForm.errors.tanggal_transfer" class="text-xs font-normal text-rose-600">{{ setorForm.errors.tanggal_transfer }}</span>
                </label>

                <label class="grid gap-2 text-sm font-semibold text-slate-700">
                    Nama pengirim
                    <input
                        v-model="setorForm.nama_pengirim"
                        type="text"
                        placeholder="Nama di rekening pengirim"
                        class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                    >
                    <span v-if="setorForm.errors.nama_pengirim" class="text-xs font-normal text-rose-600">{{ setorForm.errors.nama_pengirim }}</span>
                </label>

                <label class="grid gap-2 text-sm font-semibold text-slate-700">
                    Bukti transfer
                    <input
                        type="file"
                        accept="image/*"
                        class="rounded-2xl border border-slate-200 px-4 py-3 text-sm file:mr-3 file:rounded-full file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-slate-700"
                        @input="setorForm.bukti = $event.target.files[0]"
                    >
                    <span v-if="setorForm.errors.bukti" class="text-xs font-normal text-rose-600">{{ setorForm.errors.bukti }}</span>
                </label>

                <button
                    type="submit"
                    class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-white disabled:opacity-60"
                    :disabled="setorForm.processing"
                >
                    Kirim Permintaan Setor
                </button>
            </form>
        </section>

        <!-- Bayar dari Saldo -->
        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Bayar dari Saldo
                </p>
                <h3 class="mt-1 font-serif text-2xl text-slate-900">
                    Lunasi Tagihan
                </h3>
            </div>

            <div v-if="!hasSaldo" class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                Saldo tabungan masih kosong. Lakukan setor saldo terlebih dahulu untuk membayar tagihan dari saldo.
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
                    <p class="mt-3 text-sm text-slate-600">
                        <span class="font-semibold text-slate-900">Sisa tagihan:</span> {{ rupiah(bill.sisaTagihan) }}
                    </p>

                    <button
                        v-if="activeBillId !== bill.id"
                        type="button"
                        class="mt-3 w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!hasSaldo"
                        @click="bukaBayar(bill)"
                    >
                        Bayar dari Saldo
                    </button>

                    <!-- Form konfirmasi bayar (saldo sebelum -> sesudah) -->
                    <div v-else class="mt-4 space-y-3 rounded-2xl bg-slate-50 px-4 py-4">
                        <label class="grid gap-2 text-sm font-semibold text-slate-700">
                            Nominal bayar
                            <input
                                v-model="bayarForm.nominal"
                                type="number"
                                min="1"
                                :max="Math.min(Number(bill.sisaTagihan), saldo)"
                                inputmode="numeric"
                                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm"
                            >
                            <span v-if="bayarForm.errors.nominal" class="text-xs font-normal text-rose-600">{{ bayarForm.errors.nominal }}</span>
                        </label>

                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <div class="flex items-center justify-between text-slate-600">
                                <span>Saldo sebelum</span>
                                <span class="font-semibold text-slate-900">{{ rupiah(saldo) }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-slate-600">
                                <span>Nominal bayar</span>
                                <span class="font-semibold text-rose-700">- {{ rupiah(bayarForm.nominal || 0) }}</span>
                            </div>
                            <div class="mt-2 border-t border-dashed border-slate-200 pt-2 flex items-center justify-between text-slate-600">
                                <span>Saldo sesudah</span>
                                <span
                                    class="font-semibold"
                                    :class="saldoSetelahBayar < 0 ? 'text-rose-700' : 'text-emerald-700'"
                                >{{ rupiah(saldoSetelahBayar) }}</span>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-600"
                                @click="tutupBayar"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                class="flex-[2] rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-white disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!nominalBayarValid || bayarForm.processing"
                                @click="submitBayar"
                            >
                                Konfirmasi Bayar
                            </button>
                        </div>
                    </div>
                </article>
            </div>
            <p v-else class="rounded-[1.5rem] border border-dashed border-slate-200 bg-white/60 px-4 py-6 text-center text-sm text-slate-400">
                Tidak ada tagihan yang perlu dibayar.
            </p>
        </section>
    </div>
</template>
