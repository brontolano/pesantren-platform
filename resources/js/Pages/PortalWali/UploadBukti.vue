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
    StatCard,
    IconCircle,
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
            <div class="flex items-start gap-3">
                <IconCircle icon="upload" variant="glass" size="lg" />
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                        Unggah bukti
                    </p>
                    <h2 class="mt-1 text-2xl font-extrabold leading-tight tracking-tight">
                        {{ bill.jenisTagihan }}
                    </h2>
                    <p class="mt-1 text-sm text-white/75">
                        Untuk {{ child.nama }}
                    </p>
                </div>
            </div>

            <div class="mt-4 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                    Sisa yang harus dibayar
                </p>
                <MoneyText
                    :amount="bill.sisaTagihan"
                    size="xl"
                    tone="inherit"
                    class="mt-1 block text-white"
                />
            </div>
            <!-- #332: rincian komponen syahriah (transparansi) -->
            <div v-if="bill.komponen && bill.komponen.length" class="mt-3 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">Rincian tagihan</p>
                <ul class="mt-2 space-y-1">
                    <li
                        v-for="(k, ki) in bill.komponen"
                        :key="ki"
                        class="flex items-center justify-between gap-2 text-[12.5px] text-white/85"
                    >
                        <span class="truncate">
                            {{ k.nama }}
                            <span v-if="k.masukTabungan" class="ml-1 rounded-full bg-white/15 px-1.5 py-px text-[9.5px] font-semibold text-violet-200">→ Tabungan</span>
                        </span>
                        <MoneyText :amount="k.nominal" size="sm" tone="inherit" class="shrink-0" />
                    </li>
                </ul>
            </div>
        </UiCard>

        <!-- RINGKASAN -->
        <section class="grid grid-cols-2 gap-3">
            <StatCard label="Tagihan" icon="receipt" tone="rose" compact>
                <template #value>
                    <MoneyText :amount="bill.sisaTagihan" size="lg" tone="rose" />
                </template>
            </StatCard>
            <StatCard label="Yang Dibayar" icon="wallet" tone="emerald" compact>
                <template #value>
                    <MoneyText :amount="form.nominal" size="lg" tone="emerald" />
                </template>
            </StatCard>
        </section>

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
                                class="min-h-[48px] w-full appearance-none rounded-2xl bg-white py-3 pl-4 pr-11 text-sm font-medium text-slate-900 ring-1 ring-inset transition-all duration-200 focus:outline-none focus:ring-2"
                                :class="invalid ? 'ring-rose-300 focus:ring-rose-500' : 'ring-slate-200 hover:ring-slate-300 focus:ring-[var(--wali-primary,#7c3aed)]'"
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
                                class="pointer-events-none absolute right-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
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
                            class="group flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/70 px-4 py-8 text-center transition-all duration-200 hover:border-violet-300 hover:bg-violet-50/40 focus-within:border-violet-300 focus-within:ring-2 focus-within:ring-[var(--wali-primary,#7c3aed)]"
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
                                class="max-h-56 rounded-2xl object-contain ring-1 ring-slate-100"
                            >
                            <template v-else>
                                <IconCircle
                                    icon="camera"
                                    tone="emerald"
                                    variant="gradient"
                                    size="lg"
                                    class="transition-transform duration-200 group-hover:scale-105"
                                />
                                <span class="mt-3 text-sm font-bold text-violet-700">Pilih foto</span>
                                <span class="mt-1 text-xs font-normal text-slate-500">
                                    Ambil dari galeri atau kamera (maks. 2 MB)
                                </span>
                            </template>
                        </label>
                        <p v-if="previewUrl" class="mt-2 flex items-center gap-1.5 text-xs font-medium text-slate-500">
                            <UiIcon name="upload" class="h-3.5 w-3.5 text-violet-600" />
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
