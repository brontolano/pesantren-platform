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

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Tagihan
            </p>
            <h2 class="mt-3 font-serif text-3xl leading-tight">
                {{ child.nama }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-white/75">
                {{ child.kelas || 'Belum ada kelas' }}
            </p>
            <div class="mt-4 rounded-[1.4rem] border border-white/10 bg-white/8 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/55">
                    Total yang perlu dibayar
                </p>
                <p class="mt-1 text-2xl font-semibold text-white">
                    {{ rupiah(totalTagihan) }}
                </p>
            </div>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Daftar tagihan
                </p>
                <h3 class="mt-1 font-serif text-2xl text-slate-900">
                    Tagihan Santri
                </h3>
            </div>

            <div v-if="bills.length" class="grid gap-3">
                <Link
                    v-for="bill in bills"
                    :key="bill.id"
                    :href="`/wali/santri/${child.id}/tagihan/${bill.id}`"
                    class="block rounded-[1.5rem] border border-black/5 bg-white px-4 py-4 shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)] transition active:scale-[0.99]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4 class="text-lg font-semibold text-slate-900">
                                {{ bill.jenisTagihan }}
                            </h4>
                            <p class="mt-1 truncate text-sm text-slate-500">
                                {{ bill.keterangan || 'Tidak ada keterangan' }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-3 py-1 text-[11px] font-semibold"
                            :class="badgeFor(bill.status).class"
                        >
                            {{ badgeFor(bill.status).label }}
                        </span>
                    </div>

                    <div class="mt-3 flex items-end justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Sisa tagihan
                            </p>
                            <p class="mt-1 text-xl font-semibold text-slate-900">
                                {{ rupiah(bill.sisaTagihan) }}
                            </p>
                        </div>
                        <p class="text-xs text-slate-500">
                            Jatuh tempo {{ bill.jatuhTempo || '-' }}
                        </p>
                    </div>
                </Link>
            </div>

            <div
                v-else
                class="rounded-[1.5rem] border border-dashed border-emerald-200 bg-emerald-50/60 px-5 py-8 text-center"
            >
                <p class="text-base font-semibold text-emerald-800">
                    Tidak ada tagihan
                </p>
                <p class="mt-1 text-sm text-emerald-700/80">
                    Semua tagihan anak Anda sudah lunas.
                </p>
            </div>
        </section>
    </div>
</template>
