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
    IconCircle,
    MoneyText,
    StatusBadge,
    QrCanvas,
} from '@/Components/Ui'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    wali: { type: Object, default: () => ({}) },
    children: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
    child: { type: Object, required: true },
    topup: { type: Object, required: true },
    payment: { type: Object, required: true },
    alreadyPaid: { type: Boolean, default: false },
})

const rupiah = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0))

const method = computed(() => props.payment?.method ?? {})
const category = computed(() => method.value.category ?? props.topup?.metodeCategory ?? null)
const instructionsType = computed(() => method.value.instructionsType ?? '')
const steps = computed(() => props.payment?.steps ?? [])
const fee = computed(() => Number(props.payment?.fee || 0))

const CATEGORY_ICON = {
    VA: 'bank',
    EWALLET: 'wallet',
    QRIS: 'qr',
    RETAIL: 'cash',
    CARD: 'card',
}
const categoryIcon = computed(() => CATEGORY_ICON[category.value] || 'card')

// QR fallback ke gambar jika encoder lokal gagal.
const qrFailed = ref(false)

// Salin universal.
const copiedKey = ref(null)
async function copyValue(value, key) {
    if (!value || typeof navigator === 'undefined' || !navigator.clipboard) return
    try {
        await navigator.clipboard.writeText(String(value))
        copiedKey.value = key
        setTimeout(() => {
            if (copiedKey.value === key) copiedKey.value = null
        }, 1800)
    } catch {
        // abaikan bila clipboard tak tersedia
    }
}

// Form kartu DEMO (presentasional, tidak dikirim — konfirmasi tanpa body).
const cardForm = ref({ number: '', name: '', expiry: '', cvv: '' })

// Konfirmasi bayar (POST tanpa body).
const confirmForm = useForm({})
function konfirmasi() {
    confirmForm.post(`/wali/santri/${props.child.id}/topup/${props.topup.id}/konfirmasi`, {
        preserveScroll: false,
    })
}

const checkoutUrl = computed(() => `/wali/santri/${props.child.id}/topup`)
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Instruksi Pembayaran</title>
        </Head>

        <!-- HERO ringkas: nominal + metode -->
        <UiCard variant="hero-dark" padding="lg">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                        Selesaikan Pembayaran
                    </p>
                    <p class="mt-2 truncate text-sm text-white/80">
                        {{ method.name || topup.metodeName }}
                    </p>
                </div>
                <IconCircle :icon="categoryIcon" variant="glass" size="md" />
            </div>

            <div class="mt-4 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                    Total yang dibayar
                </p>
                <MoneyText :amount="payment.total" size="xl" tone="inherit" class="mt-1 block text-white" />
                <div class="mt-2 flex items-center justify-between text-[12px] text-white/75">
                    <span>Top up saldo {{ rupiah(payment.nominal) }}</span>
                    <span>{{ fee > 0 ? `Admin ${rupiah(fee)}` : 'Tanpa biaya admin' }}</span>
                </div>
            </div>
        </UiCard>

        <!-- Status sudah dibayar -->
        <UiCard v-if="alreadyPaid" padding="md">
            <div class="flex items-start gap-3">
                <IconCircle icon="check-circle" tone="emerald" size="md" />
                <div class="min-w-0">
                    <p class="text-[15px] font-bold text-slate-900">Pembayaran sudah dikonfirmasi</p>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Top up ini sudah berhasil. Saldo tabungan telah bertambah.
                    </p>
                    <UiButton
                        as="link"
                        :href="`/wali/santri/${child.id}/topup/${topup.id}/sukses`"
                        variant="primary"
                        size="sm"
                        class="mt-3"
                        icon-right="chevron-right"
                    >
                        Lihat Ringkasan
                    </UiButton>
                </div>
            </div>
        </UiCard>

        <template v-else>
            <!-- DETAIL: Virtual Account -->
            <SectionCard
                v-if="instructionsType === 'va_number'"
                kicker="Virtual Account"
                :title="payment.bankName || method.name"
            >
                <div class="space-y-4">
                    <div class="rounded-2xl bg-[#f6f0e6] px-4 py-3 ring-1 ring-[var(--wali-border,#efe7da)]">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                            Nomor Virtual Account
                        </p>
                        <div class="mt-1 flex items-center justify-between gap-3">
                            <p class="text-xl font-extrabold tabular-nums tracking-wide text-slate-900">
                                {{ payment.vaNumber }}
                            </p>
                            <UiButton
                                type="button"
                                variant="primary"
                                size="sm"
                                :icon-left="copiedKey === 'va' ? 'check' : 'copy'"
                                @click="copyValue(payment.vaNumber, 'va')"
                            >
                                {{ copiedKey === 'va' ? 'Tersalin' : 'Salin' }}
                            </UiButton>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- DETAIL: QRIS -->
            <SectionCard
                v-else-if="instructionsType === 'qris'"
                kicker="QRIS"
                title="Scan untuk Membayar"
            >
                <div class="space-y-4">
                    <div class="flex flex-col items-center">
                        <div class="rounded-3xl bg-white p-4 ring-1 ring-[var(--wali-border,#efe7da)] shadow-soft">
                            <QrCanvas
                                v-if="payment.qrString && !qrFailed"
                                :value="payment.qrString"
                                :size="232"
                                @error="qrFailed = true"
                            />
                            <img
                                v-else-if="payment.qrImageUrl"
                                :src="payment.qrImageUrl"
                                alt="Kode QR pembayaran QRIS"
                                width="232"
                                height="232"
                                class="block rounded-xl"
                            >
                            <div
                                v-else
                                class="flex h-[232px] w-[232px] flex-col items-center justify-center gap-2 rounded-xl bg-slate-50 text-slate-400"
                            >
                                <IconCircle icon="qr" tone="slate" size="lg" />
                                <span class="text-xs font-medium">QR tidak tersedia</span>
                            </div>
                        </div>
                        <p class="mt-3 text-center text-sm font-medium text-slate-600">
                            Scan di aplikasi e-wallet atau m-banking yang mendukung QRIS.
                        </p>
                    </div>

                    <div class="rounded-2xl bg-[#f6f0e6] px-4 py-3 ring-1 ring-[var(--wali-border,#efe7da)]">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                                    Kode pembayaran
                                </p>
                                <p class="mt-0.5 truncate text-base font-bold tabular-nums text-slate-900">
                                    {{ payment.paymentCode }}
                                </p>
                            </div>
                            <UiButton
                                type="button"
                                variant="secondary"
                                size="sm"
                                :icon-left="copiedKey === 'code' ? 'check' : 'copy'"
                                @click="copyValue(payment.paymentCode, 'code')"
                            >
                                {{ copiedKey === 'code' ? 'Tersalin' : 'Salin' }}
                            </UiButton>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- DETAIL: Retail (Alfamart / Indomaret) -->
            <SectionCard
                v-else-if="instructionsType === 'retail_code'"
                kicker="Gerai Retail"
                :title="method.name"
            >
                <div class="space-y-4">
                    <div class="rounded-2xl bg-[#f6f0e6] px-4 py-3 ring-1 ring-[var(--wali-border,#efe7da)]">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                            Kode pembayaran
                        </p>
                        <div class="mt-1 flex items-center justify-between gap-3">
                            <p class="text-xl font-extrabold tabular-nums tracking-wide text-slate-900">
                                {{ payment.retailCode }}
                            </p>
                            <UiButton
                                type="button"
                                variant="primary"
                                size="sm"
                                :icon-left="copiedKey === 'retail' ? 'check' : 'copy'"
                                @click="copyValue(payment.retailCode, 'retail')"
                            >
                                {{ copiedKey === 'retail' ? 'Tersalin' : 'Salin' }}
                            </UiButton>
                        </div>
                    </div>
                    <p class="flex gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900 ring-1 ring-amber-100">
                        <IconCircle icon="info" tone="amber" size="sm" class="!h-6 !w-6 !rounded-lg shrink-0" />
                        <span>Tunjukkan kode ini ke kasir {{ method.name }}. Biaya admin {{ rupiah(fee) }} sudah termasuk dalam total.</span>
                    </p>
                </div>
            </SectionCard>

            <!-- DETAIL: E-Wallet -->
            <SectionCard
                v-else-if="instructionsType === 'ewallet_redirect'"
                kicker="E-Wallet"
                :title="method.name"
            >
                <div class="space-y-4">
                    <div class="rounded-2xl bg-[#f6f0e6] px-4 py-3 ring-1 ring-[var(--wali-border,#efe7da)]">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                            Kode referensi
                        </p>
                        <div class="mt-1 flex items-center justify-between gap-3">
                            <p class="text-lg font-extrabold tabular-nums tracking-wide text-slate-900">
                                {{ payment.paymentCode }}
                            </p>
                            <UiButton
                                type="button"
                                variant="secondary"
                                size="sm"
                                :icon-left="copiedKey === 'ref' ? 'check' : 'copy'"
                                @click="copyValue(payment.paymentCode, 'ref')"
                            >
                                {{ copiedKey === 'ref' ? 'Tersalin' : 'Salin' }}
                            </UiButton>
                        </div>
                    </div>
                    <UiButton
                        v-if="payment.ewalletDeeplink"
                        as="a"
                        :href="payment.ewalletDeeplink"
                        variant="primary"
                        block
                        icon-left="wallet"
                    >
                        Buka Aplikasi {{ method.name }}
                    </UiButton>
                </div>
            </SectionCard>

            <!-- DETAIL: Kartu (form dummy) -->
            <SectionCard
                v-else-if="instructionsType === 'card_form'"
                kicker="Kartu Kredit / Debit"
                title="Data Kartu (Simulasi)"
            >
                <div class="space-y-4">
                    <UiField label="Nomor kartu">
                        <template #default="{ fieldId }">
                            <UiInput
                                :id="fieldId"
                                v-model="cardForm.number"
                                type="text"
                                inputmode="numeric"
                                placeholder="0000 0000 0000 0000"
                            />
                        </template>
                    </UiField>
                    <UiField label="Nama pada kartu">
                        <template #default="{ fieldId }">
                            <UiInput
                                :id="fieldId"
                                v-model="cardForm.name"
                                type="text"
                                placeholder="Nama lengkap"
                            />
                        </template>
                    </UiField>
                    <div class="grid grid-cols-2 gap-3">
                        <UiField label="Berlaku (MM/YY)">
                            <template #default="{ fieldId }">
                                <UiInput :id="fieldId" v-model="cardForm.expiry" type="text" placeholder="12/28" />
                            </template>
                        </UiField>
                        <UiField label="CVV">
                            <template #default="{ fieldId }">
                                <UiInput :id="fieldId" v-model="cardForm.cvv" type="text" inputmode="numeric" placeholder="123" />
                            </template>
                        </UiField>
                    </div>
                    <p class="text-xs font-medium text-slate-400">
                        Data kartu tidak dikirim ke mana pun — hanya simulasi tampilan demo.
                    </p>
                </div>
            </SectionCard>

            <!-- LANGKAH PEMBAYARAN -->
            <SectionCard v-if="steps.length" kicker="Panduan" title="Langkah Pembayaran">
                <ol class="space-y-3">
                    <li
                        v-for="(step, i) in steps"
                        :key="i"
                        class="flex items-start gap-3"
                    >
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-50 text-xs font-extrabold text-violet-700 ring-1 ring-inset ring-violet-100">
                            {{ i + 1 }}
                        </span>
                        <span class="pt-0.5 text-sm leading-6 text-slate-700">{{ step }}</span>
                    </li>
                </ol>
            </SectionCard>

            <!-- CATATAN DEMO -->
            <p class="flex items-center justify-center gap-1.5 text-[11px] font-medium text-slate-400">
                <span class="h-1.5 w-1.5 rounded-full bg-slate-300" />
                Mode demo: pembayaran disimulasikan.
            </p>

            <!-- CTA UTAMA -->
            <div class="sticky bottom-24 z-10">
                <UiButton
                    variant="primary"
                    size="lg"
                    block
                    type="button"
                    :loading="confirmForm.processing"
                    icon-left="check"
                    @click="konfirmasi"
                >
                    Saya Sudah Bayar (Simulasi)
                </UiButton>
            </div>
        </template>
    </div>
</template>
