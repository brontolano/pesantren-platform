<script setup>
import { Head } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'

const form = reactive({ tagihan_id: '', nominal: '', metode: 'tunai' })
const status = ref('')

async function simpan() {
    status.value = 'Menyimpan...'
    try {
        const res = await fetch('/api/keuangan/pemasukan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(form),
        })
        const data = await res.json().catch(() => ({}))
        status.value = res.ok ? `Pembayaran tercatat & dibukukan (Rp ${data.nominal}).` : 'Gagal: ' + (data.message || res.status)
    } catch (e) {
        status.value = 'Tidak ada koneksi. Coba lagi saat online.'
    }
}
</script>

<template>
    <Head title="Catat Pemasukan" />
    <div class="min-h-screen bg-[#FDFBF7] p-4">
        <h1 class="text-lg font-semibold text-[#0A5C36] mb-3">Catat Pemasukan (Tunai)</h1>
        <form @submit.prevent="simpan" class="space-y-3">
            <input v-model="form.tagihan_id" type="number" placeholder="ID Tagihan" class="w-full rounded-lg border p-2" required />
            <input v-model="form.nominal" type="number" placeholder="Nominal bayar" class="w-full rounded-lg border p-2" required />
            <button type="submit" class="w-full rounded-lg bg-[#0A5C36] text-white p-3 font-medium">Catat & Verifikasi</button>
        </form>
        <p v-if="status" class="mt-3 text-sm text-gray-600">{{ status }}</p>
    </div>
</template>
