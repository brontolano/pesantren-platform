<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import {
    UiCard,
    PageHeading,
    MetodeCard,
    EmptyState,
    UiButton,
    MoneyText,
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

const tipeLabel = {
    bank: 'Transfer Bank',
    ewallet: 'E-Wallet',
    qris: 'QRIS',
    tunai: 'Tunai',
}

const openId = ref(props.metodePembayaran[0]?.id ?? null)

function toggle(id) {
    openId.value = openId.value === id ? null : id
}

async function copyValue(value) {
    if (!value || typeof navigator === 'undefined' || !navigator.clipboard) {
        return
    }
    try {
        await navigator.clipboard.writeText(value)
    } catch {
        // abaikan bila clipboard tidak tersedia
    }
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Cara Bayar</title>
        </Head>

        <!-- HERO -->
        <UiCard variant="hero-dark" padding="lg">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">
                Cara bayar
            </p>
            <h2 class="mt-2 text-2xl font-bold leading-tight">
                {{ bill.jenisTagihan }}
            </h2>
            <p class="mt-1 text-sm text-white/75">
                Untuk {{ child.nama }}
            </p>
            <div class="mt-4 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/60">
                    Jumlah yang harus ditransfer
                </p>
                <MoneyText
                    :amount="bill.sisaTagihan"
                    size="xl"
                    tone="inherit"
                    class="mt-1 block text-white"
                />
            </div>
        </UiCard>

        <!-- DAFTAR METODE -->
        <section class="space-y-3">
            <PageHeading
                kicker="Pilih tujuan transfer"
                title="Cara Membayar"
                subtitle="Transfer ke salah satu rekening di bawah, lalu unggah bukti pembayaran."
            />

            <div v-if="metodePembayaran.length" class="grid gap-3">
                <MetodeCard
                    v-for="metode in metodePembayaran"
                    :key="metode.id"
                    :metode="metode"
                    :open="openId === metode.id"
                    @toggle="toggle(metode.id)"
                />
            </div>

            <EmptyState
                v-else
                icon="bank"
                title="Belum ada metode pembayaran"
                message="Silakan hubungi bagian keuangan pesantren."
            />
        </section>

        <!-- CTA -->
        <section class="sticky bottom-24 z-10">
            <UiButton
                variant="primary"
                size="lg"
                block
                as="link"
                :href="`/wali/santri/${child.id}/tagihan/${bill.id}/bayar`"
                icon-left="upload"
            >
                Sudah Transfer, Unggah Bukti
            </UiButton>
        </section>
    </div>
</template>
