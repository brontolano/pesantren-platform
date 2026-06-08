<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
    can: { type: Object, default: () => ({}) },
    userName: { type: String, default: '' },
})

const base = '/admin/keuangan/pwa'
</script>

<template>
    <Head title="Keuangan Lapangan" />
    <div class="min-h-screen bg-[#FDFBF7] p-4">
        <header class="mb-4">
            <h1 class="text-xl font-semibold text-[#0A5C36]">Keuangan Pesantren</h1>
            <p class="text-sm text-gray-500">Halo, {{ userName }}</p>
        </header>

        <div class="grid grid-cols-2 gap-3">
            <a v-if="can.input_belanja" :href="`${base}/catat-belanja`" class="rounded-xl bg-white border p-4 text-center shadow-sm">
                <div class="text-2xl">🧾</div><div class="mt-1 font-medium">Catat Belanja</div>
            </a>
            <a v-if="can.setor_tabungan" :href="`${base}/catat-pemasukan`" class="rounded-xl bg-white border p-4 text-center shadow-sm">
                <div class="text-2xl">💰</div><div class="mt-1 font-medium">Catat Pemasukan</div>
            </a>
            <a v-if="can.view_laporan_pimpinan" :href="`${base}/dasbor`" class="rounded-xl bg-white border p-4 text-center shadow-sm">
                <div class="text-2xl">📊</div><div class="mt-1 font-medium">Dasbor Pimpinan</div>
            </a>
            <a v-if="can.view_slip_sendiri" :href="`${base}/slip-saya`" class="rounded-xl bg-white border p-4 text-center shadow-sm">
                <div class="text-2xl">🧮</div><div class="mt-1 font-medium">Slip Gaji Saya</div>
            </a>
        </div>

        <p class="mt-6 text-xs text-gray-400">Mode lapangan — transaksi tersimpan luring saat sinyal lemah lalu tersinkron otomatis.</p>
    </div>
</template>
