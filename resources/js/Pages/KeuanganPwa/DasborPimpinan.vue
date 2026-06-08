<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'

const data = ref(null)
const error = ref('')

function rp(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID')
}

onMounted(async () => {
    try {
        const res = await fetch('/api/keuangan/dashboard-pimpinan', {
            headers: { Accept: 'application/json' }, credentials: 'same-origin',
        })
        if (res.ok) data.value = await res.json()
        else error.value = 'Gagal memuat data.'
    } catch (e) { error.value = 'Tidak ada koneksi.' }
})
</script>

<template>
    <Head title="Dasbor Pimpinan" />
    <div class="min-h-screen bg-[#FDFBF7] p-4">
        <h1 class="text-lg font-semibold text-[#0A5C36] mb-3">Dasbor Pimpinan</h1>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <div v-else-if="data" class="grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-white border p-4"><div class="text-xs text-gray-500">Kas Konsolidasi</div><div class="text-lg font-semibold">{{ rp(data.kas_konsolidasi) }}</div></div>
            <div class="rounded-xl bg-white border p-4"><div class="text-xs text-gray-500">Surplus/Defisit</div><div class="text-lg font-semibold">{{ rp(data.surplus_defisit) }}</div></div>
            <div class="rounded-xl bg-white border p-4"><div class="text-xs text-gray-500">Pemasukan</div><div class="text-lg font-semibold">{{ rp(data.total_pendapatan) }}</div></div>
            <div class="rounded-xl bg-white border p-4"><div class="text-xs text-gray-500">Pengeluaran</div><div class="text-lg font-semibold">{{ rp(data.total_beban) }}</div></div>
            <div class="rounded-xl bg-white border p-4 col-span-2"><div class="text-xs text-gray-500">Tunggakan SPP</div><div class="text-lg font-semibold text-amber-700">{{ rp(data.tunggakan) }}</div></div>
        </div>
        <p v-else class="text-sm text-gray-400">Memuat...</p>
    </div>
</template>
