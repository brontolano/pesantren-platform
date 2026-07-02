<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'
import UiIcon from '@/Components/Ui/UiIcon.vue'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    child: { type: Object, required: true },
    bills: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    savings: { type: Object, required: true },
    summary: { type: Object, required: true },
})

const activeTab = ref('tagihan')

const currency = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
})

function fmt(v) {
    return currency.format(v || 0)
}

const unpaidBills = computed(() => props.bills.filter(b => b.status !== 'lunas'))
const paidBills = computed(() => props.bills.filter(b => b.status === 'lunas'))

function statusBadge(status) {
    if (status === 'lunas') return { label: 'Lunas', bg: 'bg-emerald-50', text: 'text-emerald-700', iconBg: 'bg-emerald-50', iconText: 'text-emerald-700' }
    if (status === 'sebagian') return { label: 'Dibayar sebagian', bg: 'bg-amber-50', text: 'text-amber-700', iconBg: 'bg-amber-50', iconText: 'text-amber-700' }
    return { label: 'Belum dibayar', bg: 'bg-rose-50', text: 'text-rose-700', iconBg: 'bg-rose-50', iconText: 'text-rose-700' }
}
</script>

<template>
    <div class="space-y-4">
        <Head>
            <title>Keuangan · {{ child.nama }}</title>
        </Head>

        <!-- Tab pills -->
        <div class="flex gap-2">
            <button
                v-for="tab in ['tagihan', 'tabungan', 'riwayat']"
                :key="tab"
                class="rounded-full px-4 py-2 font-jost text-[13px] font-semibold transition"
                :class="activeTab === tab
                    ? 'bg-emerald-600 text-white'
                    : 'bg-white text-slate-600 shadow-[inset_0_0_0_1px_#efe7da]'"
                @click="activeTab = tab"
            >
                {{ tab === 'tagihan' ? 'Tagihan' : tab === 'tabungan' ? 'Tabungan' : 'Riwayat' }}
            </button>
        </div>

        <!-- ==================== TAB: TAGIHAN ==================== -->
        <template v-if="activeTab === 'tagihan'">
            <!-- Total tunggakan -->
            <div class="flex items-center justify-between rounded-[22px] bg-[#f6f0e6] px-5 py-[15px] shadow-[inset_0_0_0_1px_#efe7da]">
                <div>
                    <p class="font-jost text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Total Tunggakan</p>
                    <p class="mt-[3px] font-jost text-[23px] font-bold tabular-nums text-[#111]">{{ fmt(summary.totalOutstanding) }}</p>
                </div>
                <Link
                    v-if="unpaidBills.length"
                    :href="`/wali/santri/${child.id}/tagihan/${unpaidBills[0].id}/bayar`"
                    class="rounded-full bg-emerald-600 px-[18px] py-2.5 font-jost text-[13px] font-semibold text-white shadow-[0_8px_20px_-12px_rgba(5,150,105,0.45)]"
                >
                    Bayar semua
                </Link>
            </div>

            <!-- Bill list -->
            <div class="space-y-[11px]">
                <Link
                    v-for="bill in bills"
                    :key="bill.id"
                    :href="bill.status !== 'lunas' ? `/wali/santri/${child.id}/tagihan/${bill.id}/bayar` : '#'"
                    class="flex items-center gap-[13px] rounded-[22px] bg-white px-[17px] py-[15px] shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)] transition active:scale-[.99]"
                    :class="bill.status === 'lunas' ? 'opacity-75' : ''"
                >
                    <span
                        class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-[14px]"
                        :class="[statusBadge(bill.status).iconBg, statusBadge(bill.status).iconText]"
                    >
                        <UiIcon :name="bill.status === 'lunas' ? 'check-circle' : bill.status === 'sebagian' ? 'clock' : 'receipt'" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-800">{{ bill.jenisTagihan }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ bill.status === 'lunas' ? `Dibayar ${bill.tanggalLunas || '-'}` : `Jatuh tempo ${bill.jatuhTempo || '-'}` }}
                        </p>
                        <!-- #332: rincian komponen syahriah (transparansi) -->
                        <ul v-if="bill.komponen && bill.komponen.length" class="mt-1.5 space-y-0.5 border-l-2 border-[#efe7da] pl-2">
                            <li
                                v-for="(k, ki) in bill.komponen"
                                :key="ki"
                                class="flex items-center justify-between gap-2 text-[11px] text-slate-500"
                            >
                                <span class="truncate">
                                    {{ k.nama }}
                                    <span v-if="k.masukTabungan" class="ml-1 rounded-full bg-emerald-50 px-1.5 py-px text-[9.5px] font-semibold text-emerald-700">→ Tabungan</span>
                                </span>
                                <span class="shrink-0 tabular-nums">{{ fmt(k.nominal) }}</span>
                            </li>
                        </ul>
                        <span
                            class="mt-1.5 inline-flex rounded-full px-2.5 py-[3px] font-jost text-[10.5px] font-semibold"
                            :class="[statusBadge(bill.status).bg, statusBadge(bill.status).text]"
                        >
                            {{ statusBadge(bill.status).label }}
                        </span>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-jost text-[15.5px] font-bold tabular-nums text-[#111]">{{ fmt(bill.nominalAkhir) }}</p>
                        <span v-if="bill.status !== 'lunas'" class="mt-1 flex justify-end text-slate-400">
                            <UiIcon name="chevron-right" class="h-[15px] w-[15px]" />
                        </span>
                    </div>
                </Link>
            </div>

            <!-- Empty state -->
            <div v-if="!bills.length" class="rounded-[22px] bg-white px-5 py-6 text-center shadow-[inset_0_0_0_1px_#efe7da]">
                <UiIcon name="check-circle" class="mx-auto h-8 w-8 text-emerald-500" />
                <p class="mt-2 text-sm font-semibold text-slate-700">Tidak ada tagihan</p>
                <p class="mt-1 text-xs text-slate-500">Semua tagihan anak sudah lunas.</p>
            </div>

            <!-- Tabungan card (bottom, always visible in tagihan tab) -->
            <Link
                :href="`/wali/santri/${child.id}/tabungan`"
                class="flex items-center gap-[13px] rounded-[22px] bg-gradient-to-br from-emerald-600 to-emerald-800 px-[18px] py-[15px] text-white shadow-[0_16px_44px_-24px_rgba(17,17,17,0.4)] transition active:scale-[.99]"
            >
                <span class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-[14px] bg-white/16 ring-1 ring-inset ring-white/25">
                    <UiIcon name="wallet" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-jost text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#e7d3a1]">Tabungan {{ child.nama }}</p>
                    <p class="mt-0.5 font-jost text-lg font-bold tabular-nums">{{ fmt(savings.saldo) }}</p>
                </div>
                <span class="shrink-0 rounded-full bg-white px-4 py-2 font-jost text-[12.5px] font-semibold text-emerald-700">
                    Setor saldo
                </span>
            </Link>
        </template>

        <!-- ==================== TAB: TABUNGAN ==================== -->
        <template v-if="activeTab === 'tabungan'">
            <div class="rounded-[22px] bg-gradient-to-br from-emerald-600 to-emerald-800 px-5 py-5 text-white">
                <p class="font-jost text-[11px] font-semibold uppercase tracking-[0.14em] text-[#e7d3a1]">Saldo Tabungan</p>
                <p class="mt-1 font-jost text-3xl font-bold tabular-nums">{{ fmt(savings.saldo) }}</p>
                <div class="mt-4 flex gap-2">
                    <Link
                        :href="`/wali/santri/${child.id}/topup`"
                        class="rounded-full bg-white px-5 py-2.5 font-jost text-[13px] font-semibold text-emerald-700"
                    >
                        Setor Saldo
                    </Link>
                </div>
            </div>

            <div v-if="savings.mutasi?.length" class="rounded-[22px] bg-white shadow-[inset_0_0_0_1px_#efe7da]">
                <div class="px-[18px] pt-4 pb-2">
                    <h3 class="font-jost text-sm font-semibold text-slate-800">Mutasi Terakhir</h3>
                </div>
                <div class="divide-y divide-[#f6f0e6] px-[18px]">
                    <div v-for="(m, i) in savings.mutasi.slice(0, 10)" :key="i" class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-[13px] font-semibold text-slate-800">{{ m.keterangan }}</p>
                            <p class="text-xs text-slate-500">{{ m.tanggal }}</p>
                        </div>
                        <p class="font-jost text-sm font-bold tabular-nums" :class="m.tipe === 'kredit' ? 'text-emerald-700' : 'text-rose-600'">
                            {{ m.tipe === 'kredit' ? '+' : '-' }}{{ fmt(m.nominal) }}
                        </p>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-[22px] bg-white px-5 py-6 text-center shadow-[inset_0_0_0_1px_#efe7da]">
                <p class="text-sm text-slate-500">Belum ada mutasi tabungan</p>
            </div>
        </template>

        <!-- ==================== TAB: RIWAYAT ==================== -->
        <template v-if="activeTab === 'riwayat'">
            <div v-if="payments.length" class="rounded-[22px] bg-white shadow-[inset_0_0_0_1px_#efe7da,0_6px_24px_-16px_rgba(17,17,17,0.18)]">
                <div class="divide-y divide-[#f6f0e6] px-[18px]">
                    <div v-for="payment in payments" :key="payment.id" class="flex items-center gap-[13px] py-3">
                        <span class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-[13px] bg-emerald-50 text-emerald-700">
                            <UiIcon name="check-circle" class="h-[19px] w-[19px]" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[13.5px] font-semibold text-slate-800">{{ payment.tagihan || 'Pembayaran' }}</p>
                            <p class="text-xs text-slate-500">{{ payment.tanggalPembayaran || '-' }} · {{ payment.metode || '-' }}</p>
                        </div>
                        <p class="shrink-0 font-jost text-sm font-bold tabular-nums text-emerald-700">{{ fmt(payment.nominal) }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-[22px] bg-white px-5 py-6 text-center shadow-[inset_0_0_0_1px_#efe7da]">
                <UiIcon name="receipt" class="mx-auto h-8 w-8 text-slate-400" />
                <p class="mt-2 text-sm font-semibold text-slate-700">Belum ada riwayat</p>
                <p class="mt-1 text-xs text-slate-500">Riwayat pembayaran akan tampil di sini.</p>
            </div>
        </template>
    </div>
</template>
