<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
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

const badge = computed(
    () => statusBadge[props.bill.status] ?? { label: props.bill.status, class: 'bg-slate-100 text-slate-600' },
)

const canPay = computed(() => props.bill.status === 'belum_bayar' || props.bill.status === 'sebagian')
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Detail Tagihan</title>
        </Head>

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                        Detail tagihan
                    </p>
                    <h2 class="mt-3 font-serif text-3xl leading-tight">
                        {{ bill.jenisTagihan }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-white/75">
                        Untuk {{ child.nama }}
                    </p>
                </div>
                <span
                    class="shrink-0 rounded-full px-3 py-1 text-[11px] font-semibold"
                    :class="badge.class"
                >
                    {{ badge.label }}
                </span>
            </div>

            <div class="mt-4 rounded-[1.4rem] border border-white/10 bg-white/8 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/55">
                    Sisa yang harus dibayar
                </p>
                <p class="mt-1 text-3xl font-semibold text-white">
                    {{ rupiah(bill.sisaTagihan) }}
                </p>
            </div>
        </section>

        <section class="space-y-3 rounded-[1.75rem] border border-black/5 bg-white px-5 py-5 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]">
            <h3 class="font-serif text-xl text-slate-900">
                Rincian
            </h3>

            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Keterangan</dt>
                    <dd class="text-right font-medium text-slate-900">
                        {{ bill.keterangan || '-' }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Total tagihan</dt>
                    <dd class="text-right font-medium text-slate-900">
                        {{ rupiah(bill.nominalAkhir) }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Sudah dibayar</dt>
                    <dd class="text-right font-medium text-emerald-700">
                        {{ rupiah(bill.totalDibayar) }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-dashed border-slate-200 pt-3">
                    <dt class="font-semibold text-slate-700">Sisa tagihan</dt>
                    <dd class="text-right text-lg font-semibold text-slate-900">
                        {{ rupiah(bill.sisaTagihan) }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Jatuh tempo</dt>
                    <dd class="text-right font-medium text-slate-900">
                        {{ bill.jatuhTempo || '-' }}
                    </dd>
                </div>
            </dl>
        </section>

        <section v-if="canPay" class="sticky bottom-24 z-10">
            <Link
                :href="`/wali/santri/${child.id}/tagihan/${bill.id}/cara-bayar`"
                class="block w-full rounded-2xl bg-emerald-600 px-4 py-4 text-center text-sm font-semibold uppercase tracking-[0.18em] text-white shadow-[0_18px_40px_-20px_rgba(5,150,105,0.9)]"
            >
                Bayar Sekarang
            </Link>
        </section>

        <section
            v-else
            class="rounded-[1.5rem] border border-dashed border-emerald-200 bg-emerald-50/60 px-5 py-6 text-center"
        >
            <p class="text-base font-semibold text-emerald-800">
                Tagihan ini sudah selesai
            </p>
            <p class="mt-1 text-sm text-emerald-700/80">
                Tidak ada tindakan yang perlu dilakukan.
            </p>
        </section>
    </div>
</template>
