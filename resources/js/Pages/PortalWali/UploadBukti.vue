<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

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
    metodePembayaran: {
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

const today = new Date().toISOString().slice(0, 10)

const form = useForm({
    nominal: Number(props.bill.sisaTagihan || 0),
    metode: props.metodePembayaran[0]?.nama ?? '',
    tanggal_pembayaran: today,
    nomor_bukti: '',
    catatan: '',
    bukti_pembayaran: null,
})

const previewUrl = ref(null)

function onFileChange(event) {
    const file = event.target.files?.[0] ?? null
    form.bukti_pembayaran = file
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
        previewUrl.value = null
    }
    if (file) {
        previewUrl.value = URL.createObjectURL(file)
    }
}

function submit() {
    form.post(`/wali/santri/${props.child.id}/tagihan/${props.bill.id}/bayar`, {
        forceFormData: true,
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Unggah Bukti</title>
        </Head>

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Unggah bukti
            </p>
            <h2 class="mt-3 font-serif text-3xl leading-tight">
                {{ bill.jenisTagihan }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-white/75">
                Untuk {{ child.nama }} · Sisa {{ rupiah(bill.sisaTagihan) }}
            </p>
        </section>

        <form
            class="space-y-4 rounded-[1.75rem] border border-black/5 bg-white px-5 py-5 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
            @submit.prevent="submit"
        >
            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Jumlah dibayar
                <input
                    v-model.number="form.nominal"
                    type="number"
                    min="1"
                    inputmode="numeric"
                    class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                >
                <span v-if="form.errors.nominal" class="text-xs font-medium text-rose-600">{{ form.errors.nominal }}</span>
            </label>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Metode pembayaran
                <select v-model="form.metode" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                    <option
                        v-for="metode in metodePembayaran"
                        :key="metode.id"
                        :value="metode.nama"
                    >
                        {{ metode.nama }}
                    </option>
                </select>
                <span v-if="form.errors.metode" class="text-xs font-medium text-rose-600">{{ form.errors.metode }}</span>
            </label>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Tanggal transfer
                <input
                    v-model="form.tanggal_pembayaran"
                    type="date"
                    class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                >
                <span v-if="form.errors.tanggal_pembayaran" class="text-xs font-medium text-rose-600">{{ form.errors.tanggal_pembayaran }}</span>
            </label>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Nomor referensi (opsional)
                <input
                    v-model="form.nomor_bukti"
                    type="text"
                    placeholder="Contoh: no. referensi transfer"
                    class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                >
                <span v-if="form.errors.nomor_bukti" class="text-xs font-medium text-rose-600">{{ form.errors.nomor_bukti }}</span>
            </label>

            <div class="grid gap-2 text-sm font-semibold text-slate-700">
                Foto bukti transfer
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center">
                    <input
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="onFileChange"
                    >
                    <img
                        v-if="previewUrl"
                        :src="previewUrl"
                        alt="Pratinjau bukti"
                        class="max-h-56 rounded-xl object-contain"
                    >
                    <template v-else>
                        <span class="text-sm font-semibold text-emerald-700">Pilih foto</span>
                        <span class="mt-1 text-xs font-normal text-slate-500">
                            Ambil dari galeri atau kamera (maks. 2 MB)
                        </span>
                    </template>
                </label>
                <p v-if="previewUrl" class="text-xs font-normal text-slate-500">
                    Ketuk gambar untuk mengganti foto.
                </p>
                <span v-if="form.errors.bukti_pembayaran" class="text-xs font-medium text-rose-600">{{ form.errors.bukti_pembayaran }}</span>
            </div>

            <label class="grid gap-2 text-sm font-semibold text-slate-700">
                Catatan (opsional)
                <textarea
                    v-model="form.catatan"
                    rows="3"
                    placeholder="Tambahkan keterangan bila perlu"
                    class="rounded-2xl border border-slate-200 px-4 py-3 text-sm"
                ></textarea>
                <span v-if="form.errors.catatan" class="text-xs font-medium text-rose-600">{{ form.errors.catatan }}</span>
            </label>

            <button
                type="submit"
                class="w-full rounded-2xl bg-emerald-600 px-4 py-4 text-sm font-semibold uppercase tracking-[0.18em] text-white shadow-[0_18px_40px_-20px_rgba(5,150,105,0.9)] disabled:opacity-60"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Mengirim…' : 'Kirim Bukti Pembayaran' }}
            </button>
        </form>
    </div>
</template>
