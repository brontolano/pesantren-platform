<script setup>
import { onMounted, ref, watch } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })

const tahun = ref(String(new Date().getFullYear()))
const loading = ref(true)
const data = ref(null)

async function muat() {
    loading.value = true
    try { data.value = await api.get(`/laporan/rekap-tahunan?tahun=${tahun.value}`) } catch { /* */ }
    loading.value = false
}

onMounted(muat)
</script>

<template>
    <div>
        <h1 class="mb-1 font-['Marcellus',serif] text-lg text-[#0A5C36]">Laporan Keuangan</h1>
        <p class="mb-4 text-xs text-[#8a9690]">Rekap Penerimaan & Penyaluran Tahunan</p>

        <div class="mb-4 flex items-center gap-2">
            <input v-model="tahun" type="number" min="2020" max="2030" @change="muat"
                   class="w-24 rounded-lg border border-[#ece3cf] px-3 py-1.5 text-sm">
            <span class="text-xs text-[#8a9690]">Januari — Desember {{ tahun }}</span>
        </div>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 6" :key="i" class="h-10 animate-pulse rounded-lg bg-[#e8e1d0]/60"></div>
        </div>

        <template v-else-if="data">
            <!-- Penerimaan -->
            <div class="mb-4 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Penerimaan</div>
                <div v-for="r in data.penerimaan" :key="r.kode"
                     class="flex justify-between border-b border-[#ece3cf]/60 px-1 py-2 text-sm last:border-0">
                    <span class="text-[#3a4d42]">{{ r.nama }}</span>
                    <span class="font-semibold">{{ formatRupiahFull(r.nominal) }}</span>
                </div>
                <div class="flex justify-between border-t border-[#ece3cf] pt-2 font-bold text-sm">
                    <span>Total Penerimaan</span>
                    <span class="text-emerald-600">{{ formatRupiahFull(data.total_penerimaan) }}</span>
                </div>
            </div>

            <!-- Penyaluran -->
            <div class="mb-4 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Penggunaan & Penyaluran Dana</div>
                <div v-for="r in data.penyaluran" :key="r.kode"
                     class="flex justify-between border-b border-[#ece3cf]/60 px-1 py-2 text-sm last:border-0">
                    <span class="text-[#3a4d42]">{{ r.nama }}</span>
                    <span class="font-semibold">{{ formatRupiahFull(r.nominal) }}</span>
                </div>
                <div class="flex justify-between border-t border-[#ece3cf] pt-2 font-bold text-sm">
                    <span>Total Penyaluran</span>
                    <span class="text-rose-600">{{ formatRupiahFull(data.total_penyaluran) }}</span>
                </div>
            </div>

            <!-- Surplus / Defisit -->
            <div class="rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-5 text-center text-white"
                 style="border: 1px solid rgba(201,162,74,0.3);">
                <div class="text-[11px] uppercase tracking-wider text-[#e7d3a1]">
                    {{ (data.surplus_defisit ?? 0) >= 0 ? 'Surplus' : 'Defisit' }} Tahun {{ data.tahun }}
                </div>
                <div class="mt-1 font-['Marcellus',serif] text-2xl"
                     :class="(data.surplus_defisit ?? 0) < 0 ? 'text-rose-300' : ''">
                    {{ formatRupiahFull(Math.abs(data.surplus_defisit ?? 0)) }}
                </div>
                <div class="mt-3 flex justify-between text-xs text-[#e7d3a1]/80">
                    <span>Penerimaan: {{ formatRupiahFull(data.total_penerimaan) }}</span>
                    <span>Penyaluran: {{ formatRupiahFull(data.total_penyaluran) }}</span>
                </div>
            </div>

            <!-- Tanda tangan -->
            <div class="mt-6 flex justify-between text-center text-[10px] text-[#8a9690]">
                <div>
                    <div>Mengetahui,</div>
                    <div class="font-semibold">Ketua Yayasan</div>
                    <div class="mt-10 border-t border-[#ece3cf] pt-1">(.....................)</div>
                </div>
                <div>
                    <div>&nbsp;</div>
                    <div class="font-semibold">Bendahara</div>
                    <div class="mt-10 border-t border-[#ece3cf] pt-1">(.....................)</div>
                </div>
            </div>
        </template>

        <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-8 text-center text-sm text-[#8a9690]">
            Tidak ada data untuk tahun {{ tahun }}
        </div>
    </div>
</template>
