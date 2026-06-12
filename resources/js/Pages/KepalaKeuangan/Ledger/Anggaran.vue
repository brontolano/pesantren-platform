<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const data = ref([])

onMounted(async () => {
    try {
        const res = await api.get('/anggaran/realisasi')
        data.value = res.data ?? []
    } catch { /* */ }
    loading.value = false
})
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Anggaran & Realisasi</h1>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="h-20 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
        </div>

        <div v-else-if="data.length" class="space-y-3">
            <div v-for="d in data" :key="d.divisi"
                 class="rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-1 flex items-center justify-between text-sm">
                    <span class="font-medium">{{ d.divisi }}</span>
                    <span class="text-xs text-[#8a9690]">{{ Math.round((d.realisasi / (d.pagu || 1)) * 100) }}%</span>
                </div>
                <div class="mb-2 h-2 overflow-hidden rounded-full bg-[#ece3cf]">
                    <div class="h-full rounded-full transition-all"
                         :class="(d.realisasi / (d.pagu || 1)) > 0.9 ? 'bg-rose-500' : (d.realisasi / (d.pagu || 1)) > 0.8 ? 'bg-amber-500' : 'bg-[#0A5C36]'"
                         :style="{ width: Math.min(100, (d.realisasi / (d.pagu || 1)) * 100) + '%' }"></div>
                </div>
                <div class="flex justify-between text-xs text-[#8a9690]">
                    <span>Realisasi: {{ formatRupiah(d.realisasi) }}</span>
                    <span>Pagu: {{ formatRupiah(d.pagu) }}</span>
                </div>
            </div>
        </div>

        <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-8 text-center text-sm text-[#8a9690]">
            Data anggaran belum tersedia. Realisasi belanja tetap bisa dilihat di Jurnal.
        </div>
    </div>
</template>
