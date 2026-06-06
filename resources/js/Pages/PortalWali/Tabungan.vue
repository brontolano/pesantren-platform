<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import {
    UiCard,
    SectionCard,
    ListRow,
    MoneyText,
    UiField,
    UiInput,
    UiButton,
    EmptyState,
    BottomSheet,
    StatusBadge,
    StatCard,
    IconCircle,
} from '@/Components/Ui'

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

// --- Top Up Saldo (checkout marketplace) ---
const topupUrl = computed(() => `/wali/santri/${props.child.id}/topup`)

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

// --- UI: kontrol bottom-sheet konfirmasi bayar (presentasional saja) ---
// Terbuka saat ada tagihan aktif; menutup memanggil tutupBayar() yang sudah ada.
const bayarSheetOpen = computed({
    get: () => activeBillId.value !== null,
    set: (open) => {
        if (!open) tutupBayar()
    },
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Tabungan Santri</title>
        </Head>

        <!-- HERO: kartu saldo besar -->
        <UiCard variant="hero-dark" padding="lg">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                        Saldo Tabungan
                    </p>
                    <p class="mt-2 truncate text-sm text-white/80">
                        {{ child.nama }} · {{ child.kelas || 'Belum ada kelas' }}
                    </p>
                </div>
                <IconCircle icon="wallet" variant="glass" size="md" />
            </div>
            <MoneyText :amount="saldo" size="2xl" tone="inherit" class="mt-5 block text-white" />
            <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold text-white/85 ring-1 ring-inset ring-white/15">
                <span class="h-1.5 w-1.5 rounded-full" :class="hasSaldo ? 'bg-emerald-200' : 'bg-amber-200'" />
                {{ hasSaldo ? 'Saldo siap dipakai' : 'Belum ada saldo' }}
            </p>
        </UiCard>

        <!-- RINGKASAN -->
        <section class="grid grid-cols-2 gap-3">
            <StatCard label="Mutasi" icon="receipt" tone="teal" :value="String(transactions.length)" hint="riwayat tercatat" />
            <StatCard label="Tagihan Aktif" icon="card" tone="amber" :value="String(bills.length)" hint="bisa dari saldo" />
        </section>

        <!-- MUTASI -->
        <SectionCard kicker="Mutasi" title="Riwayat Tabungan" :flush="transactions.length > 0">
            <div v-if="transactions.length" class="divide-y divide-slate-100">
                <ListRow
                    v-for="trx in transactions"
                    :key="trx.id"
                    :title="trx.keterangan || (trx.tipe === 'setor' ? 'Setoran saldo' : 'Penarikan saldo')"
                    :subtitle="trx.tanggal || '-'"
                >
                    <template #leading>
                        <IconCircle
                            :icon="trx.tipe === 'setor' ? 'arrow-down-left' : 'arrow-up-right'"
                            :tone="trx.tipe === 'setor' ? 'emerald' : 'rose'"
                            size="md"
                        />
                    </template>
                    <template #trailing>
                        <MoneyText
                            :amount="trx.nominal"
                            :sign="trx.tipe === 'setor' ? 'plus' : 'minus'"
                            :tone="trx.tipe === 'setor' ? 'emerald' : 'rose'"
                        />
                        <p class="mt-0.5 text-[11px] font-medium text-slate-400">
                            Saldo: {{ rupiah(trx.saldoSesudah) }}
                        </p>
                    </template>
                </ListRow>
            </div>
            <EmptyState
                v-else
                icon="wallet"
                title="Belum ada mutasi"
                message="Riwayat setor dan tarik tabungan akan tampil di sini."
            />
        </SectionCard>

        <!-- TOP UP SALDO -->
        <SectionCard kicker="Setor Saldo" title="Top Up Tabungan">
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <IconCircle icon="plus" tone="emerald" size="md" />
                    <div class="min-w-0">
                        <p class="text-[15px] font-bold tracking-tight text-slate-900">
                            Top up instan via banyak metode
                        </p>
                        <p class="mt-0.5 text-sm leading-6 text-slate-500">
                            Virtual Account, e-wallet, QRIS, hingga gerai retail. Pilih nominal, bayar, saldo langsung bertambah.
                        </p>
                    </div>
                </div>

                <UiButton
                    as="link"
                    :href="topupUrl"
                    variant="primary"
                    block
                    icon-left="wallet"
                    icon-right="chevron-right"
                >
                    Top Up Saldo
                </UiButton>
            </div>
        </SectionCard>

        <!-- BAYAR DARI SALDO -->
        <SectionCard kicker="Bayar dari Saldo" title="Lunasi Tagihan">
            <div class="space-y-3">
                <UiCard v-if="!hasSaldo" variant="muted" padding="sm">
                    <div class="flex items-start gap-3">
                        <IconCircle icon="info" tone="amber" size="md" />
                        <p class="pt-0.5 text-sm font-medium text-amber-900">
                            Saldo tabungan masih kosong. Lakukan setor saldo terlebih dahulu untuk membayar tagihan dari saldo.
                        </p>
                    </div>
                </UiCard>

                <template v-if="bills.length">
                    <UiCard v-for="bill in bills" :key="bill.id" padding="sm">
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
                            <MoneyText :amount="bill.sisaTagihan" size="lg" tone="rose" />
                        </div>

                        <UiButton
                            variant="primary"
                            block
                            type="button"
                            class="mt-3"
                            :disabled="!hasSaldo"
                            icon-left="wallet"
                            @click="bukaBayar(bill)"
                        >
                            Bayar dari Saldo
                        </UiButton>
                    </UiCard>
                </template>

                <EmptyState
                    v-else
                    tone="emerald"
                    icon="check-circle"
                    title="Tidak ada tagihan"
                    message="Semua tagihan anak Anda sudah lunas."
                />
            </div>
        </SectionCard>

        <!-- BOTTOM SHEET: konfirmasi bayar dari saldo -->
        <BottomSheet v-model="bayarSheetOpen" title="Konfirmasi Bayar dari Saldo">
            <div v-if="activeBill" class="space-y-4">
                <div class="rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-100">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-900">
                                {{ activeBill.jenisTagihan }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                {{ activeBill.keterangan || '-' }}
                            </p>
                        </div>
                        <StatusBadge :status="activeBill.status" size="sm" class="shrink-0" />
                    </div>
                    <div class="mt-2 flex items-center justify-between border-t border-slate-200 pt-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Sisa tagihan</span>
                        <MoneyText :amount="activeBill.sisaTagihan" size="sm" />
                    </div>
                </div>

                <UiField label="Nominal bayar" :error="bayarForm.errors.nominal">
                    <template #default="{ fieldId, describedby, invalid }">
                        <UiInput
                            :id="fieldId"
                            v-model="bayarForm.nominal"
                            type="number"
                            :min="1"
                            :max="Math.min(Number(activeBill.sisaTagihan), saldo)"
                            inputmode="numeric"
                            :invalid="invalid"
                            :aria-describedby="describedby"
                        />
                    </template>
                </UiField>

                <div class="rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-100">
                    <div class="flex items-center justify-between text-sm text-slate-600">
                        <span>Saldo sebelum</span>
                        <MoneyText :amount="saldo" size="sm" />
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm text-slate-600">
                        <span>Nominal bayar</span>
                        <MoneyText :amount="bayarForm.nominal || 0" size="sm" tone="rose" sign="minus" />
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
            </div>

            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="secondary" class="flex-1" type="button" @click="tutupBayar">
                        Batal
                    </UiButton>
                    <UiButton
                        variant="primary"
                        class="flex-[2]"
                        type="button"
                        :disabled="!nominalBayarValid"
                        :loading="bayarForm.processing"
                        icon-left="check"
                        @click="submitBayar"
                    >
                        Konfirmasi Bayar
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </div>
</template>
