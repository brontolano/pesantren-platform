<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const tab = ref('neraca')
const loading = ref(true)
const neraca = ref(null)
const lr = ref(null)

onMounted(async () => {
    try {
        const [n, l] = await Promise.all([
            api.get('/laporan/neraca'),
            api.get('/laporan/laba-rugi'),
        ])
        neraca.value = n
        lr.value = l
    } catch { /* */ }
    loading.value = false
})
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Neraca & Laba Rugi</h1>

        <!-- Tabs -->
        <div class="mb-4 flex rounded-xl border border-[#ece3cf] bg-white p-1">
            <button v-for="t in ['neraca','laba-rugi']" :key="t"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-bold capitalize transition"
                    :class="tab === t ? 'bg-[#0A5C36] text-white' : 'text-[#3a4d42]'"
                    @click="tab = t">
                {{ t === 'neraca' ? 'Neraca' : 'Laba Rugi' }}
            </button>
        </div>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 6" :key="i" class="h-10 animate-pulse rounded-lg bg-[#e8e1d0]/60"></div>
        </div>

        <!-- Neraca -->
        <div v-else-if="tab === 'neraca' && neraca">
            <div v-for="section in neraca.sections ?? []" :key="section.nama" class="mb-4">
                <div class="mb-1 text-xs font-bold uppercase tracking-wider text-[#8a9690]">{{ section.nama }}</div>
                <div v-for="row in section.items ?? []" :key="row.nama"
                     class="flex justify-between border-b border-[#ece3cf] px-1 py-2 text-sm">
                    <span :class="row.level > 0 ? 'pl-4 text-[#8a9690]' : 'font-medium'">{{ row.nama }}</span>
                    <span class="font-semibold">{{ formatRupiahFull(row.saldo) }}</span>
                </div>
            </div>
            <div v-if="neraca.seimbang" class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-center text-xs font-bold text-emerald-700">
                Seimbang
            </div>
        </div>

        <!-- Laba Rugi -->
        <div v-else-if="tab === 'laba-rugi' && lr">
            <div v-for="section in lr.sections ?? []" :key="section.nama" class="mb-4">
                <div class="mb-1 text-xs font-bold uppercase tracking-wider text-[#8a9690]">{{ section.nama }}</div>
                <div v-for="row in section.items ?? []" :key="row.nama"
                     class="flex justify-between border-b border-[#ece3cf] px-1 py-2 text-sm">
                    <span :class="row.level > 0 ? 'pl-4 text-[#8a9690]' : 'font-medium'">{{ row.nama }}</span>
                    <span class="font-semibold">{{ formatRupiahFull(row.saldo) }}</span>
                </div>
            </div>
            <div v-if="lr.surplus != null" class="mt-2 rounded-lg px-3 py-2 text-center text-xs font-bold"
                 :class="lr.surplus >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                {{ lr.surplus >= 0 ? 'Surplus' : 'Defisit' }}: {{ formatRupiahFull(Math.abs(lr.surplus)) }}
            </div>
        </div>
    </div>
</template>
