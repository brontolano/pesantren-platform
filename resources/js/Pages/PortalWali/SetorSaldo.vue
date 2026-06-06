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
})

const rupiah = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0))

const saldo = computed(() => Number(props.savings?.saldo || 0))

const form = useForm({
    nominal: '',
    tanggal_transfer: '',
    nama_pengirim: props.wali?.name ?? '',
    bukti: null,
})

const saldoSetelahSetor = computed(() => saldo.value + Number(form.nominal || 0))

function submit() {
    form.post(`/wali/santri/${props.child.id}/tabungan/topup`, {
        forceFormData: true,
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Setor Saldo</title>
        </Head>

        <section class="rounded-[2rem] bg-slate-950 px-5 py-6 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Setor Saldo
            </p>
            <p class="mt-3 text-sm text-white/75">
                {{ child.nama }} · {{ child.kelas || 'Belum ada kelas' }}
            </p>
            <p class="mt-3 text-sm text-white/60">
                Saldo saat ini
            </p>
            <p class="mt-1 font-serif text-3xl leading-tight">
                {{ rupiah(saldo) }}
            </p>
        </section>

        <form
            class="space-y-3 rounded-[1.75rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
            @submit.prevent="submit"
        >
            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Nominal setor
                <input
                    v-model="form.nominal"
                    type="number"
                    min="1"
                    inputmode="numeric"
                    placeholder="0"
                    class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                >
                <span v-if="form.errors.nominal" class="text-xs font-normal text-rose-600">{{ form.errors.nominal }}</span>
            </label>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Tanggal transfer
                <input
                    v-model="form.tanggal_transfer"
                    type="date"
                    class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                >
                <span v-if="form.errors.tanggal_transfer" class="text-xs font-normal text-rose-600">{{ form.errors.tanggal_transfer }}</span>
            </label>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Nama pengirim
                <input
                    v-model="form.nama_pengirim"
                    type="text"
                    placeholder="Nama di rekening pengirim"
                    class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                >
                <span v-if="form.errors.nama_pengirim" class="text-xs font-normal text-rose-600">{{ form.errors.nama_pengirim }}</span>
            </label>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Bukti transfer
                <input
                    type="file"
                    accept="image/*"
                    class="rounded-2xl border border-slate-200 px-4 py-3 text-sm file:mr-3 file:rounded-full file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-slate-700"
                    @input="form.bukti = $event.target.files[0]"
                >
                <span v-if="form.errors.bukti" class="text-xs font-normal text-rose-600">{{ form.errors.bukti }}</span>
            </label>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                <div class="flex items-center justify-between text-slate-600">
                    <span>Saldo sebelum</span>
                    <span class="font-semibold text-slate-900">{{ rupiah(saldo) }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between text-slate-600">
                    <span>Setoran</span>
                    <span class="font-semibold text-emerald-700">+ {{ rupiah(form.nominal || 0) }}</span>
                </div>
                <div class="mt-2 border-t border-dashed border-slate-200 pt-2 flex items-center justify-between text-slate-600">
                    <span>Saldo sesudah (jika disetujui)</span>
                    <span class="font-semibold text-emerald-700">{{ rupiah(saldoSetelahSetor) }}</span>
                </div>
            </div>

            <p class="text-xs text-slate-400">
                Permintaan setor akan diverifikasi bendahara sebelum saldo bertambah.
            </p>

            <button
                type="submit"
                class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-white disabled:opacity-60"
                :disabled="form.processing"
            >
                Kirim Permintaan Setor
            </button>
        </form>
    </div>
</template>
