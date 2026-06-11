<script setup>
import { Head } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({
    layout: PortalGuruLayout,
})

defineProps({
    slip: { type: Array, default: () => [] },
    bisaCetak: { type: Boolean, default: false },
})

function rupiah(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0)
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Slip Saya</title>
        </Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Slip Gaji</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Slip Saya</h2>
            <p class="mt-1 text-sm text-slate-500">Hanya slip milik Anda sendiri yang tampil di sini.</p>
        </section>

        <p v-if="slip.length === 0" class="rounded-[1.5rem] bg-white px-5 py-6 text-center text-sm text-slate-400 shadow">
            Belum ada slip gaji.
        </p>

        <div v-for="s in slip" :key="s.id" class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
            <div class="flex items-center justify-between gap-2">
                <p class="text-base font-semibold text-slate-900">{{ s.periode }}<span v-if="s.tipe && s.tipe !== 'gaji'" class="ml-1 text-xs text-slate-400">({{ s.tipe }})</span></p>
                <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="s.status === 'final' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                >
                    {{ s.status === 'final' ? 'Final' : 'Draf' }}
                </span>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                <dt class="text-slate-500">Bruto</dt>
                <dd class="text-right text-slate-700">{{ rupiah(s.bruto) }}</dd>
                <dt class="text-slate-500">PPh 21</dt>
                <dd class="text-right text-slate-700">- {{ rupiah(s.pph21) }}</dd>
                <dt v-if="s.bpjs" class="text-slate-500">BPJS</dt>
                <dd v-if="s.bpjs" class="text-right text-slate-700">- {{ rupiah(s.bpjs) }}</dd>
                <dt v-if="s.potongan_cuti" class="text-slate-500">Potongan Cuti</dt>
                <dd v-if="s.potongan_cuti" class="text-right text-slate-700">- {{ rupiah(s.potongan_cuti) }}</dd>
                <dt class="font-semibold text-slate-900">Neto</dt>
                <dd class="text-right font-semibold text-emerald-700">{{ rupiah(s.neto) }}</dd>
            </dl>

            <a
                v-if="s.print_url && bisaCetak"
                :href="s.print_url"
                target="_blank"
                class="mt-3 block rounded-2xl border border-slate-200 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Cetak Slip
            </a>
        </div>
    </div>
</template>
