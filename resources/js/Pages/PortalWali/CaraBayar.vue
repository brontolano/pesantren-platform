<script setup>
import { ref } from 'vue'
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

        <section class="rounded-[2rem] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_60px_-40px_rgba(15,23,42,0.8)]">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">
                Cara bayar
            </p>
            <h2 class="mt-3 font-serif text-3xl leading-tight">
                {{ bill.jenisTagihan }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-white/75">
                Untuk {{ child.nama }}
            </p>
            <div class="mt-4 rounded-[1.4rem] border border-white/10 bg-white/8 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/55">
                    Jumlah yang harus ditransfer
                </p>
                <p class="mt-1 text-3xl font-semibold text-white">
                    {{ rupiah(bill.sisaTagihan) }}
                </p>
            </div>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">
                    Pilih tujuan transfer
                </p>
                <h3 class="mt-1 font-serif text-2xl text-slate-900">
                    Cara Membayar
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Transfer ke salah satu rekening di bawah, lalu unggah bukti pembayaran.
                </p>
            </div>

            <div v-if="metodePembayaran.length" class="grid gap-3">
                <article
                    v-for="metode in metodePembayaran"
                    :key="metode.id"
                    class="overflow-hidden rounded-[1.5rem] border border-black/5 bg-white shadow-[0_20px_45px_-40px_rgba(15,23,42,0.7)]"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left"
                        @click="toggle(metode.id)"
                    >
                        <div class="min-w-0">
                            <span class="inline-block rounded-full bg-slate-100 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-600">
                                {{ tipeLabel[metode.tipe] || metode.tipe }}
                            </span>
                            <h4 class="mt-2 text-lg font-semibold text-slate-900">
                                {{ metode.nama }}
                            </h4>
                        </div>
                        <span
                            class="shrink-0 text-2xl leading-none text-slate-400 transition"
                            :class="openId === metode.id ? 'rotate-45' : ''"
                        >+</span>
                    </button>

                    <div v-if="openId === metode.id" class="space-y-3 border-t border-slate-100 px-4 py-4">
                        <div v-if="metode.gambarUrl" class="flex justify-center">
                            <img
                                :src="metode.gambarUrl"
                                :alt="metode.nama"
                                class="max-h-56 rounded-2xl border border-slate-100 object-contain"
                            >
                        </div>

                        <div v-if="metode.nomor" class="rounded-2xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Nomor tujuan
                            </p>
                            <div class="mt-1 flex items-center justify-between gap-3">
                                <p class="text-lg font-semibold tracking-wide text-slate-900">
                                    {{ metode.nomor }}
                                </p>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white"
                                    @click="copyValue(metode.nomor)"
                                >
                                    Salin
                                </button>
                            </div>
                        </div>

                        <div v-if="metode.atasNama" class="text-sm">
                            <span class="text-slate-500">Atas nama:</span>
                            <span class="ml-1 font-semibold text-slate-900">{{ metode.atasNama }}</span>
                        </div>

                        <p v-if="metode.instruksi" class="rounded-2xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                            {{ metode.instruksi }}
                        </p>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-[1.5rem] border border-dashed border-slate-200 bg-white px-5 py-8 text-center"
            >
                <p class="text-base font-semibold text-slate-700">
                    Belum ada metode pembayaran
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    Silakan hubungi bagian keuangan pesantren.
                </p>
            </div>
        </section>

        <section class="sticky bottom-24 z-10">
            <Link
                :href="`/wali/santri/${child.id}/tagihan/${bill.id}/bayar`"
                class="block w-full rounded-2xl bg-emerald-600 px-4 py-4 text-center text-sm font-semibold uppercase tracking-[0.18em] text-white shadow-[0_18px_40px_-20px_rgba(5,150,105,0.9)]"
            >
                Sudah Transfer, Unggah Bukti
            </Link>
        </section>
    </div>
</template>
