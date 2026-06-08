<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'

const slip = ref([])
const error = ref('')

function rp(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID')
}

onMounted(async () => {
    try {
        const res = await fetch('/api/keuangan/slip-saya', {
            headers: { Accept: 'application/json' }, credentials: 'same-origin',
        })
        if (res.ok) slip.value = (await res.json()).slip || []
        else error.value = 'Gagal memuat slip.'
    } catch (e) { error.value = 'Tidak ada koneksi.' }
})
</script>

<template>
    <Head title="Slip Gaji Saya" />
    <div class="min-h-screen bg-[#FDFBF7] p-4">
        <h1 class="text-lg font-semibold text-[#0A5C36] mb-3">Slip Gaji Saya</h1>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <div v-else class="space-y-3">
            <div v-for="s in slip" :key="s.periode" class="rounded-xl bg-white border p-4">
                <div class="font-medium">{{ s.periode }}</div>
                <div class="text-sm text-gray-600">Bruto {{ rp(s.bruto) }} · PPh21 {{ rp(s.pph21) }}</div>
                <div class="text-lg font-semibold text-[#0A5C36]">Diterima {{ rp(s.neto) }}</div>
            </div>
            <p v-if="slip.length === 0" class="text-sm text-gray-400">Belum ada slip final.</p>
        </div>
    </div>
</template>
