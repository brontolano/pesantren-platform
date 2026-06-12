<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const jurnals = ref([])

onMounted(async () => {
    try {
        const res = await api.get('/jurnal')
        jurnals.value = res.data ?? []
    } catch { /* */ }
    loading.value = false
})
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Jurnal & Buku Besar</h1>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 5" :key="i" class="h-16 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
        </div>

        <div v-else-if="jurnals.length" class="space-y-2">
            <div v-for="j in jurnals" :key="j.id"
                 class="rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-1 flex items-center justify-between">
                    <span class="text-xs font-semibold text-[#8a9690]">{{ j.nomor }}</span>
                    <span class="font-semibold text-sm text-[#0A5C36]">{{ formatRupiah(j.total_debit) }}</span>
                </div>
                <div v-if="j.deskripsi" class="text-sm font-medium text-[#3a4d42]">{{ j.deskripsi }}</div>
                <div class="mt-1 text-xs text-[#8a9690]">{{ formatTanggal(j.tanggal) }}</div>
            </div>
        </div>

        <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-8 text-center text-sm text-[#8a9690]">
            Belum ada jurnal di periode ini
        </div>
    </div>
</template>
