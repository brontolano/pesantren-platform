<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import { UiCard, UiField, UiInput, UiButton, MoneyText, IconCircle } from '@/Components/Ui'

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

import { formatRupiah as rupiah } from '@/Shared/format'

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

        <!-- HERO -->
        <UiCard variant="hero-dark" padding="lg">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                        Setor Saldo
                    </p>
                    <p class="mt-2 truncate text-sm text-white/80">
                        {{ child.nama }} · {{ child.kelas || 'Belum ada kelas' }}
                    </p>
                </div>
                <IconCircle icon="plus" variant="glass" size="md" />
            </div>
            <p class="mt-5 text-[11px] font-bold uppercase tracking-wide text-white/60">
                Saldo saat ini
            </p>
            <MoneyText :amount="saldo" size="xl" tone="inherit" class="mt-1 block text-white" />
        </UiCard>

        <!-- FORM SETOR -->
        <UiCard padding="lg">
            <form class="space-y-4" @submit.prevent="submit">
                <UiField label="Nominal setor" :error="form.errors.nominal">
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            v-model="form.nominal"
                            type="number"
                            :min="1"
                            inputmode="numeric"
                            placeholder="0"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>

                <UiField label="Tanggal transfer" :error="form.errors.tanggal_transfer">
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            v-model="form.tanggal_transfer"
                            type="date"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>

                <UiField label="Nama pengirim" :error="form.errors.nama_pengirim">
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            v-model="form.nama_pengirim"
                            type="text"
                            placeholder="Nama di rekening pengirim"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>

                <UiField label="Bukti transfer" :error="form.errors.bukti" hint="Foto/struk transfer (format gambar).">
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            type="file"
                            accept="image/*"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                            @change="form.bukti = $event.target.files[0]"
                        />
                    </template>
                </UiField>

                <!-- Ringkasan saldo sebelum -> sesudah -->
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-100">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        Ringkasan Setoran
                    </p>
                    <div class="mt-3 flex items-center justify-between text-sm font-medium text-slate-600">
                        <span>Saldo sebelum</span>
                        <MoneyText :amount="saldo" size="sm" />
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm font-medium text-slate-600">
                        <span>Setoran</span>
                        <MoneyText :amount="form.nominal || 0" size="sm" tone="emerald" sign="plus" />
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t border-dashed border-slate-200 pt-3">
                        <span class="text-sm font-semibold text-slate-700">Saldo sesudah</span>
                        <MoneyText :amount="saldoSetelahSetor" size="lg" tone="emerald" />
                    </div>
                </div>

                <p class="flex items-start gap-1.5 text-xs font-medium text-slate-400">
                    <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-slate-300" />
                    Permintaan setor akan diverifikasi bendahara sebelum saldo bertambah.
                </p>

                <UiButton variant="primary" block type="submit" :loading="form.processing" icon-left="plus">
                    Kirim Permintaan Setor
                </UiButton>
            </form>
        </UiCard>
    </div>
</template>
