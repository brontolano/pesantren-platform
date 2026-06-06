<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import {
    UiCard,
    SectionCard,
    ListRow,
    StatusBadge,
    MoneyText,
    EmptyState,
    StatCard,
    IconCircle,
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

const statusBadge = {
    belum_bayar: { label: 'Belum dibayar', class: 'bg-rose-100 text-rose-700' },
    sebagian: { label: 'Dibayar sebagian', class: 'bg-amber-100 text-amber-700' },
    lunas: { label: 'Lunas', class: 'bg-emerald-100 text-emerald-700' },
    batal: { label: 'Dibatalkan', class: 'bg-slate-100 text-slate-600' },
}

const badgeFor = (status) => statusBadge[status] ?? { label: status, class: 'bg-slate-100 text-slate-600' }

const unpaidBills = computed(() =>
    props.bills.filter((bill) => bill.status === 'belum_bayar' || bill.status === 'sebagian'),
)

const totalTagihan = computed(() =>
    unpaidBills.value.reduce((sum, bill) => sum + Number(bill.sisaTagihan || 0), 0),
)
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Tagihan</title>
        </Head>

        <!-- HERO -->
        <UiCard variant="hero-dark" padding="lg">
            <div class="flex items-start gap-3">
                <IconCircle icon="receipt" variant="glass" size="md" />
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                        Tagihan
                    </p>
                    <h2 class="mt-1 truncate text-2xl font-extrabold leading-tight tracking-tight">
                        {{ child.nama }}
                    </h2>
                    <p class="mt-1 text-sm text-white/80">
                        {{ child.kelas || 'Belum ada kelas' }}
                    </p>
                </div>
            </div>

            <div class="mt-5 rounded-2xl bg-white/10 px-4 py-4 ring-1 ring-inset ring-white/15">
                <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">
                    Total yang perlu dibayar
                </p>
                <MoneyText
                    :amount="totalTagihan"
                    size="xl"
                    tone="inherit"
                    class="mt-1 block text-white"
                />
                <p class="mt-1.5 text-xs font-medium text-white/70">
                    {{ unpaidBills.length }} tagihan menunggu pembayaran
                </p>
            </div>
        </UiCard>

        <!-- RINGKASAN STAT -->
        <section v-if="bills.length" class="grid grid-cols-2 gap-3">
            <StatCard label="Belum Lunas" icon="receipt" tone="rose">
                <template #value>
                    <MoneyText :amount="totalTagihan" size="lg" tone="rose" />
                </template>
            </StatCard>
            <StatCard
                label="Total Tagihan"
                icon="document"
                tone="slate"
                :value="String(bills.length)"
                hint="Termasuk yang sudah lunas"
            />
        </section>

        <!-- DAFTAR TAGIHAN -->
        <SectionCard
            v-if="bills.length"
            kicker="Daftar tagihan"
            title="Tagihan Santri"
            flush
        >
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="bill in bills"
                    :key="bill.id"
                    :href="`/wali/santri/${child.id}/tagihan/${bill.id}`"
                    :title="bill.jenisTagihan"
                    :subtitle="bill.keterangan || 'Tidak ada keterangan'"
                    chevron
                >
                    <template #leading>
                        <IconCircle
                            icon="receipt"
                            :tone="bill.status === 'lunas' ? 'emerald' : bill.status === 'sebagian' ? 'amber' : 'rose'"
                            size="md"
                        />
                    </template>
                    <template #trailing>
                        <StatusBadge :status="bill.status" size="sm" />
                    </template>

                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                                Sisa tagihan
                            </p>
                            <MoneyText
                                :amount="bill.sisaTagihan"
                                size="lg"
                                :tone="bill.status === 'lunas' ? 'emerald' : 'default'"
                                class="mt-0.5 block"
                            />
                        </div>
                        <div class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">
                            <UiIcon name="calendar" class="h-3.5 w-3.5" />
                            {{ bill.jatuhTempo || '-' }}
                        </div>
                    </div>
                </ListRow>
            </div>
        </SectionCard>

        <!-- EMPTY STATE -->
        <EmptyState
            v-else
            tone="emerald"
            icon="check-circle"
            title="Tidak ada tagihan"
            message="Semua tagihan anak Anda sudah lunas."
        />
    </div>
</template>
