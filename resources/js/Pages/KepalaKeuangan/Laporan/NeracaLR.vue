<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })

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
        <template v-else-if="tab === 'neraca' && neraca">
            <div v-for="section in [
                { nama: 'Aset', items: neraca.aset, total: neraca.total_aset },
                { nama: 'Kewajiban', items: neraca.kewajiban },
                { nama: 'Ekuitas', items: neraca.ekuitas },
            ]" :key="section.nama" class="mb-4 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">{{ section.nama }}</div>
                <div v-for="row in section.items ?? []" :key="row.kode"
                     class="flex justify-between border-b border-[#ece3cf]/60 px-1 py-2 text-sm last:border-0">
                    <span class="text-[#3a4d42]">{{ row.nama }}</span>
                    <span class="font-semibold">{{ formatRupiahFull(row.saldo) }}</span>
                </div>
                <div v-if="section.total != null"
                     class="flex justify-between border-t border-[#ece3cf] pt-2 font-bold text-sm">
                    <span>Total {{ section.nama }}</span>
                    <span class="text-[#0A5C36]">{{ formatRupiahFull(section.total) }}</span>
                </div>
            </div>

            <div v-if="neraca.surplus_berjalan != null"
                 class="mb-4 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="flex justify-between text-sm font-bold">
                    <span>Surplus Berjalan</span>
                    <span :class="neraca.surplus_berjalan >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                        {{ formatRupiahFull(neraca.surplus_berjalan) }}
                    </span>
                </div>
            </div>

            <div class="mb-4 rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-4 text-center text-white"
                 style="border: 1px solid rgba(201,162,74,0.3);">
                <div class="flex justify-between text-xs text-[#e7d3a1]">
                    <span>Total Aset</span>
                    <span>Total Kewajiban + Ekuitas</span>
                </div>
                <div class="flex justify-between font-['Marcellus',serif] text-lg mt-1">
                    <span>{{ formatRupiahFull(neraca.total_aset) }}</span>
                    <span>{{ formatRupiahFull(neraca.total_kewajiban_ekuitas) }}</span>
                </div>
            </div>

            <div class="rounded-lg px-3 py-2 text-center text-sm font-semibold"
                 :class="neraca.seimbang ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                {{ neraca.seimbang ? '✓ Neraca Seimbang' : '✗ Neraca Tidak Seimbang' }}
            </div>
        </template>

        <!-- Laba Rugi -->
        <template v-else-if="tab === 'laba-rugi' && lr">
            <div v-for="section in [
                { nama: 'Pendapatan', items: lr.pendapatan, total: lr.total_pendapatan, warna: 'text-emerald-600' },
                { nama: 'Beban', items: lr.beban, total: lr.total_beban, warna: 'text-rose-600' },
            ]" :key="section.nama" class="mb-4 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">{{ section.nama }}</div>
                <div v-for="row in section.items ?? []" :key="row.kode"
                     class="flex justify-between border-b border-[#ece3cf]/60 px-1 py-2 text-sm last:border-0">
                    <span class="text-[#3a4d42]">{{ row.nama }}</span>
                    <span class="font-semibold">{{ formatRupiahFull(row.saldo) }}</span>
                </div>
                <div class="flex justify-between border-t border-[#ece3cf] pt-2 font-bold text-sm">
                    <span>Total {{ section.nama }}</span>
                    <span :class="section.warna">{{ formatRupiahFull(section.total) }}</span>
                </div>
            </div>

            <div class="rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-4 text-center text-white"
                 style="border: 1px solid rgba(201,162,74,0.3);">
                <div class="text-[11px] uppercase tracking-wider text-[#e7d3a1]">
                    {{ (lr.surplus_defisit ?? 0) >= 0 ? 'Surplus' : 'Defisit' }}
                </div>
                <div class="font-['Marcellus',serif] text-xl"
                     :class="(lr.surplus_defisit ?? 0) < 0 ? 'text-rose-300' : ''">
                    {{ formatRupiahFull(Math.abs(lr.surplus_defisit ?? 0)) }}
                </div>
            </div>
        </template>
    </div>
</template>
