<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import {
    UiCard,
    UiField,
    UiInput,
    UiButton,
    MoneyText,
    UiIcon,
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

        <!-- HERO -->
        <UiCard variant="hero-dark" padding="lg">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">
                Unggah bukti
            </p>
            <h2 class="mt-2 text-2xl font-bold leading-tight">
                {{ bill.jenisTagihan }}
            </h2>
            <p class="mt-2 flex flex-wrap items-center gap-x-1.5 text-sm text-white/75">
                <span>Untuk {{ child.nama }} · Sisa</span>
                <MoneyText
                    :amount="bill.sisaTagihan"
                    size="sm"
                    tone="inherit"
                    class="text-white"
                />
            </p>
        </UiCard>

        <!-- FORM -->
        <UiCard padding="lg">
            <form class="space-y-4" @submit.prevent="submit">
                <UiField label="Jumlah dibayar" :error="form.errors.nominal">
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            v-model.number="form.nominal"
                            type="number"
                            :min="1"
                            inputmode="numeric"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>

                <UiField label="Metode pembayaran" :error="form.errors.metode">
                    <template #default="{ fieldId, describedby, invalid }">
                        <div class="relative">
                            <select
                                :id="fieldId"
                                v-model="form.metode"
                                :aria-describedby="describedby"
                                :aria-invalid="invalid || undefined"
                                class="min-h-[44px] w-full appearance-none rounded-xl bg-white py-3 pl-4 pr-10 text-sm text-slate-900 ring-1 ring-inset transition focus:outline-none focus:ring-2"
                                :class="invalid ? 'ring-rose-300 focus:ring-rose-500' : 'ring-slate-200 focus:ring-[var(--wali-primary,#059669)]'"
                            >
                                <option
                                    v-for="metode in metodePembayaran"
                                    :key="metode.id"
                                    :value="metode.nama"
                                >
                                    {{ metode.nama }}
                                </option>
                            </select>
                            <UiIcon
                                name="chevron-down"
                                class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                            />
                        </div>
                    </template>
                </UiField>

                <UiField label="Tanggal transfer" :error="form.errors.tanggal_pembayaran">
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            v-model="form.tanggal_pembayaran"
                            type="date"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>

                <UiField label="Nomor referensi (opsional)" :error="form.errors.nomor_bukti">
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            v-model="form.nomor_bukti"
                            type="text"
                            placeholder="Contoh: no. referensi transfer"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>

                <UiField label="Foto bukti transfer" :error="form.errors.bukti_pembayaran">
                    <template #default="{ describedby }">
                        <label
                            class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 px-4 py-7 text-center transition hover:border-emerald-300 hover:bg-emerald-50/40 focus-within:ring-2 focus-within:ring-[var(--wali-primary,#059669)]"
                        >
                            <input
                                type="file"
                                accept="image/*"
                                class="sr-only"
                                :aria-describedby="describedby"
                                @change="onFileChange"
                            >
                            <img
                                v-if="previewUrl"
                                :src="previewUrl"
                                alt="Pratinjau bukti"
                                class="max-h-56 rounded-xl object-contain"
                            >
                            <template v-else>
                                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                    <UiIcon name="camera" class="h-6 w-6" />
                                </span>
                                <span class="mt-3 text-sm font-semibold text-emerald-700">Pilih foto</span>
                                <span class="mt-1 text-xs font-normal text-slate-500">
                                    Ambil dari galeri atau kamera (maks. 2 MB)
                                </span>
                            </template>
                        </label>
                        <p v-if="previewUrl" class="mt-1.5 flex items-center gap-1 text-xs font-normal text-slate-500">
                            <UiIcon name="upload" class="h-3.5 w-3.5" />
                            Ketuk gambar untuk mengganti foto.
                        </p>
                    </template>
                </UiField>

                <UiField label="Catatan (opsional)" :error="form.errors.catatan">
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            v-model="form.catatan"
                            type="textarea"
                            :rows="3"
                            placeholder="Tambahkan keterangan bila perlu"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>

                <UiButton
                    type="submit"
                    variant="primary"
                    size="lg"
                    block
                    :loading="form.processing"
                    icon-left="upload"
                >
                    {{ form.processing ? 'Mengirim…' : 'Kirim Bukti Pembayaran' }}
                </UiButton>
            </form>
        </UiCard>
    </div>
</template>
