<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal, formatWaktu } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const items = ref([])
const processing = ref(null)

async function fetchData() {
    loading.value = true
    try {
        const res = await api.get('/pembayaran/pending')
        items.value = res.data ?? []
    } catch { /* */ }
    loading.value = false
}

async function verifikasi(id) {
    if (processing.value) return
    processing.value = id
    try {
        await api.post(`/pembayaran/${id}/verifikasi`)
        items.value = items.value.filter(i => i.id !== id)
    } catch { /* */ }
    processing.value = null
}

async function tolak(id) {
    if (processing.value) return
    const alasan = prompt('Alasan penolakan:')
    if (!alasan) return
    processing.value = id
    try {
        await api.post(`/pembayaran/${id}/tolak`, { alasan })
        items.value = items.value.filter(i => i.id !== id)
    } catch { /* */ }
    processing.value = null
}

onMounted(fetchData)
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Verifikasi Pembayaran</h1>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="h-20 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
        </div>

        <div v-else-if="items.length" class="space-y-3">
            <div v-for="item in items" :key="item.id"
                 class="rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-2 flex items-start justify-between">
                    <div>
                        <div class="text-sm font-medium">{{ item.nama_santri }}</div>
                        <div class="text-xs text-[#8a9690]">{{ item.metode ?? 'Transfer' }} · {{ formatTanggal(item.tanggal) }} {{ formatWaktu(item.tanggal) }}</div>
                    </div>
                    <div class="text-sm font-bold text-[#0A5C36]">{{ formatRupiah(item.nominal) }}</div>
                </div>
                <div class="flex gap-2">
                    <button @click="verifikasi(item.id)"
                            :disabled="processing === item.id"
                            class="flex-1 rounded-lg bg-[#0A5C36] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#08472a] disabled:opacity-50">
                        Verifikasi
                    </button>
                    <button @click="tolak(item.id)"
                            :disabled="processing === item.id"
                            class="flex-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100 disabled:opacity-50">
                        Tolak
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-8 text-center text-sm text-[#8a9690]">
            Semua pembayaran sudah diverifikasi
        </div>
    </div>
</template>
