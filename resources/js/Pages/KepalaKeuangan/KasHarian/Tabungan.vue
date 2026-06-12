<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const data = ref(null)

onMounted(async () => {
    try { data.value = await api.get('/tabungan/ringkasan') } catch { /* */ }
    loading.value = false
})
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Tabungan Santri</h1>

        <div v-if="loading" class="space-y-3">
            <div class="h-24 animate-pulse rounded-2xl bg-[#e8e1d0]/60"></div>
            <div class="h-40 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
        </div>

        <template v-else-if="data">
            <div class="mb-4 rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-5 text-white"
                 style="border: 1px solid rgba(201,162,74,0.3);">
                <div class="text-[11px] uppercase tracking-wider text-[#e7d3a1]">Total Dana Titipan</div>
                <div class="font-['Marcellus',serif] text-2xl">{{ formatRupiah(data.totalSaldo) }}</div>
                <div class="mt-1 text-xs opacity-75">{{ data.jumlahRekening ?? 0 }} rekening aktif</div>
            </div>

            <h2 class="mb-2 text-sm font-semibold text-[#3a4d42]">Mutasi Terakhir</h2>
            <div v-if="data.mutasiTerakhir?.length" class="space-y-2">
                <div v-for="m in data.mutasiTerakhir" :key="m.id"
                     class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                    <div>
                        <div class="text-sm font-medium">{{ m.nama_santri }}</div>
                        <div class="text-xs text-[#8a9690]">{{ m.jenis }}</div>
                    </div>
                    <span :class="m.jenis === 'setor' ? 'text-emerald-600' : 'text-rose-600'" class="text-sm font-semibold">
                        {{ m.jenis === 'setor' ? '+' : '−' }}{{ formatRupiah(m.nominal) }}
                    </span>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-6 text-center text-sm text-[#8a9690]">
                Belum ada mutasi
            </div>
        </template>
    </div>
</template>
