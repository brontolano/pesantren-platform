<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const data = ref(null)

onMounted(async () => {
    try { data.value = await api.get('/laporan/arus-kas') } catch { /* */ }
    loading.value = false
})
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Arus Kas & Tutup Buku</h1>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 5" :key="i" class="h-12 animate-pulse rounded-lg bg-[#e8e1d0]/60"></div>
        </div>

        <template v-else-if="data">
            <div v-for="section in data.sections ?? []" :key="section.nama" class="mb-4">
                <div class="mb-1 text-xs font-bold uppercase tracking-wider text-[#8a9690]">{{ section.nama }}</div>
                <div v-for="row in section.items ?? []" :key="row.nama"
                     class="flex justify-between border-b border-[#ece3cf] px-1 py-2 text-sm">
                    <span>{{ row.nama }}</span>
                    <span class="font-semibold" :class="row.saldo < 0 ? 'text-rose-600' : ''">{{ formatRupiahFull(row.saldo) }}</span>
                </div>
            </div>

            <div v-if="data.netCashFlow != null"
                 class="mb-5 rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-4 text-center text-white"
                 style="border: 1px solid rgba(201,162,74,0.3);">
                <div class="text-[11px] uppercase tracking-wider text-[#e7d3a1]">Arus Kas Bersih</div>
                <div class="font-['Marcellus',serif] text-xl">{{ formatRupiahFull(data.netCashFlow) }}</div>
            </div>

            <!-- Tutup Buku section placeholder -->
            <div class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-6 text-center text-sm text-[#8a9690]">
                Fitur Tutup Buku — akan dibangun di Fase 6
            </div>
        </template>
    </div>
</template>
