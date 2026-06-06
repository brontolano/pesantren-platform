<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import PortalWaliLayout from '../../Layouts/PortalWaliLayout.vue'

defineOptions({
    layout: PortalWaliLayout,
})

const props = defineProps({
    wali: {
        type: Object,
        required: true,
    },
    children: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        required: true,
    },
})

const todayLabel = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date())

const currencyFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
})

const totalOutstandingLabel = computed(() => currencyFormatter.format(props.summary.totalOutstanding || 0))
const totalSavingsLabel = computed(() => currencyFormatter.format(props.summary.totalSavings || 0))
const nextDueAmountLabel = computed(() => currencyFormatter.format(props.summary.nextDueBill?.amount || 0))

function formatCurrency(value) {
    return currencyFormatter.format(value || 0)
}

function resolveActionClass(tone) {
    if (tone === 'emerald') {
        return 'bg-emerald-600 text-white'
    }

    if (tone === 'amber') {
        return 'bg-amber-500 text-slate-950'
    }

    return 'bg-slate-900 text-white'
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Beranda Wali</title>
        </Head>

        <section class="overflow-hidden rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Keuangan Wali
            </p>
            <h2 class="mt-3 font-jost text-3xl leading-tight">
                Ringkasan yang perlu dibereskan hari ini
            </h2>
            <p class="mt-2 text-sm leading-6 text-white/75">
                {{ todayLabel }} · Total kewajiban, saldo, dan status transaksi semua anak tampil di satu layar.
            </p>

            <div class="mt-5 grid grid-cols-2 gap-3">
                <div class="rounded-[1.5rem] bg-white/8 px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/55">
                        Total Tunggakan
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ totalOutstandingLabel }}
                    </p>
                </div>
                <div class="rounded-[1.5rem] bg-white/8 px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/55">
                        Saldo Tabungan
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ totalSavingsLabel }}
                    </p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-white/72">
                <div class="rounded-[1.35rem] border border-white/10 bg-white/8 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/50">
                        Menunggu Verifikasi
                    </p>
                    <p class="mt-2 text-xl font-semibold text-white">
                        {{ summary.pendingTransactions }}
                    </p>
                </div>
                <div class="rounded-[1.35rem] border border-white/10 bg-white/8 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/50">
                        Anak Perlu Ditindak
                    </p>
                    <p class="mt-2 text-xl font-semibold text-white">
                        {{ summary.childrenNeedingAttention }}
                    </p>
                </div>
            </div>

            <div class="mt-4 rounded-[1.5rem] border border-amber-300/20 bg-amber-300/10 px-4 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-200/80">
                            Langkah Utama
                        </p>
                        <h3 class="mt-2 font-jost text-2xl text-white">
                            {{ summary.primaryAction?.label || 'Pantau Keuangan Anak' }}
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-white/75">
                            <template v-if="summary.nextDueBill">
                                Tagihan terdekat {{ summary.nextDueBill.label }} jatuh tempo dengan sisa {{ nextDueAmountLabel }}.
                            </template>
                            <template v-else>
                                Semua anak yang terhubung sudah tampil di beranda ini.
                            </template>
                        </p>
                    </div>
                    <Link
                        v-if="summary.primaryAction?.href"
                        :href="summary.primaryAction.href"
                        class="shrink-0 rounded-[1.25rem] px-4 py-3 text-sm font-semibold"
                        :class="resolveActionClass(summary.primaryAction.tone)"
                    >
                        Buka
                    </Link>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex items-end justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                        Per Anak
                    </p>
                    <h3 class="mt-1 font-jost text-2xl text-slate-900">
                        Finance Home Santri
                    </h3>
                </div>
            </div>

            <div
                v-if="children.length"
                class="grid gap-3"
            >
                <article
                    v-for="child in children"
                    :key="child.id"
                    class="rounded-[1.75rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">
                                {{ child.hubungan }}
                            </p>
                            <h4 class="mt-2 font-jost text-2xl text-slate-900">
                                {{ child.nama }}
                            </h4>
                            <p class="mt-1 text-sm text-slate-500">
                                NIS {{ child.nis }} · {{ child.kelas?.nama || 'Belum ditempatkan' }}
                            </p>
                        </div>
                        <div
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="child.status === 'aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                        >
                            {{ child.status }}
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 rounded-[1.25rem] bg-slate-50 px-4 py-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                                Tunggakan
                            </p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">
                                {{ formatCurrency(child.finance?.totalOutstanding) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">
                                Saldo
                            </p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">
                                {{ formatCurrency(child.finance?.savingsBalance) }}
                            </p>
                        </div>
                        <div class="text-sm text-slate-600">
                            <span class="font-semibold text-slate-900">Tagihan aktif:</span>
                            {{ child.finance?.outstandingBillsCount || 0 }}
                        </div>
                        <div class="text-sm text-slate-600">
                            <span class="font-semibold text-slate-900">Pending:</span>
                            {{ child.finance?.pendingPaymentsCount || 0 }}
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2 rounded-[1.25rem] border border-black/5 bg-white px-4 py-4 text-sm text-slate-600">
                        <p v-if="child.finance?.nextDueBill">
                            <span class="font-semibold text-slate-900">Jatuh tempo terdekat:</span>
                            {{ child.finance.nextDueBill.label }} · {{ child.finance.nextDueBill.dueDate || '-' }}
                        </p>
                        <p v-else>
                            <span class="font-semibold text-slate-900">Status:</span>
                            Tidak ada tagihan aktif yang menunggu dibayar.
                        </p>
                        <p v-if="child.isPrimary" class="font-semibold text-emerald-700">
                            Akses utama wali
                        </p>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs font-semibold uppercase tracking-[0.16em]">
                        <Link
                            :href="child.finance?.primaryAction?.href || `/wali/santri/${child.id}/keuangan#tagihan`"
                            class="rounded-2xl px-3 py-3 text-center"
                            :class="resolveActionClass(child.finance?.primaryAction?.tone)"
                        >
                            {{ child.finance?.primaryAction?.label || 'Buka Keuangan' }}
                        </Link>
                        <Link
                            :href="`/wali/santri/${child.id}`"
                            class="rounded-2xl bg-slate-100 px-3 py-3 text-center text-slate-700"
                        >
                            Profil
                        </Link>
                        <Link
                            :href="`/wali/santri/${child.id}/akademik`"
                            class="rounded-2xl bg-emerald-50 px-3 py-3 text-center text-emerald-700"
                        >
                            Akademik
                        </Link>
                        <Link
                            :href="`/wali/santri/${child.id}/keuangan#tagihan`"
                            class="rounded-2xl bg-amber-50 px-3 py-3 text-center text-amber-700"
                        >
                            Keuangan
                        </Link>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-[1.75rem] border border-dashed border-slate-300 bg-white px-4 py-6 text-sm leading-6 text-slate-600"
            >
                Belum ada data santri yang terhubung ke akun wali ini.
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3">
            <Link
                href="/wali/notifikasi"
                class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                    Notifikasi
                </p>
                <p class="mt-2 font-jost text-xl text-slate-900">
                    Pembayaran Saya
                </p>
            </Link>
            <Link
                href="/wali/izin"
                class="rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                    Perizinan
                </p>
                <p class="mt-2 font-jost text-xl text-slate-900">
                    Layanan Wali
                </p>
            </Link>
        </section>
    </div>
</template>
