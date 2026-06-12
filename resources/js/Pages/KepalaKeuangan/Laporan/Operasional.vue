<script setup>
import { onMounted, ref, watch } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })

const tabs = [
    { key: 'harian', label: 'Harian' },
    { key: 'mingguan', label: 'Mingguan' },
    { key: 'bulanan', label: 'Bulanan' },
]

const aktif = ref(new URLSearchParams(window.location.search).get('tab') ?? 'harian')
const loading = ref(true)
const data = ref(null)
const error = ref(null)

async function muat() {
    loading.value = true
    error.value = null
    try {
        data.value = await api.get(`/laporan/operasional?jenis=${aktif.value}`)
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

watch(aktif, muat)
onMounted(muat)
</script>

<template>
    <h2 class="mb-3 font-['Marcellus',serif] text-lg text-[#0A5C36]">Laporan Operasional</h2>

    <div class="mb-4 flex gap-2">
        <button v-for="t in tabs" :key="t.key" @click="aktif = t.key"
                class="rounded-full border px-4 py-1.5 text-sm font-semibold transition"
                :class="aktif === t.key
                    ? 'border-[#0A5C36] bg-[#0A5C36] text-white'
                    : 'border-[#ece3cf] bg-white text-[#3a4d42]'">
            {{ t.label }}
        </button>
    </div>

    <div v-if="loading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
    </div>

    <div v-else-if="error" class="rounded-2xl bg-red-50 px-4 py-5 text-sm text-red-700">
        Gagal memuat: {{ error }}
    </div>

    <template v-else-if="data">
        <!-- Harian -->
        <template v-if="aktif === 'harian'">
            <div class="mb-3 rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-5 text-white">
                <div class="text-[11px] uppercase tracking-wider text-[#e7d3a1]">Total Penerimaan · {{ formatTanggal(data.tanggal) }}</div>
                <div class="font-['Marcellus',serif] text-2xl">{{ formatRupiah(data.total_pembayaran) }}</div>
                <div class="mt-1 text-xs opacity-75">{{ data.jumlah_transaksi }} transaksi terverifikasi</div>
            </div>
            <div v-if="data.per_jenis_tagihan?.length" class="space-y-2">
                <div v-for="j in data.per_jenis_tagihan" :key="j.jenis"
                     class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                    <div>
                        <div class="text-sm font-medium uppercase">{{ j.jenis }}</div>
                        <div class="text-xs text-[#8a9690]">{{ j.jumlah_transaksi }} transaksi</div>
                    </div>
                    <span class="font-semibold text-[#0A5C36]">{{ formatRupiah(j.total) }}</span>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-6 text-center text-sm text-[#8a9690]">
                Belum ada pembayaran terverifikasi hari ini
            </div>
        </template>

        <!-- Mingguan -->
        <template v-else-if="aktif === 'mingguan'">
            <div class="mb-3 rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-5 text-white">
                <div class="text-[11px] uppercase tracking-wider text-[#e7d3a1]">
                    {{ formatTanggal(data.dari) }} – {{ formatTanggal(data.sampai) }}
                </div>
                <div class="font-['Marcellus',serif] text-2xl">{{ formatRupiah(data.total) }}</div>
                <div class="mt-1 text-xs opacity-75">{{ data.jumlah_transaksi }} transaksi dalam 7 hari</div>
            </div>
            <div v-if="data.per_hari?.length" class="space-y-2">
                <div v-for="h in data.per_hari" :key="h.tanggal"
                     class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                    <div>
                        <div class="text-sm font-medium">{{ formatTanggal(h.tanggal) }}</div>
                        <div class="text-xs text-[#8a9690]">{{ h.jumlah }} transaksi</div>
                    </div>
                    <span class="font-semibold text-emerald-600">+{{ formatRupiah(h.total) }}</span>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-6 text-center text-sm text-[#8a9690]">
                Tidak ada pembayaran pada rentang ini
            </div>
        </template>

        <!-- Bulanan -->
        <template v-else>
            <div class="mb-3 rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-5 text-white">
                <div class="text-[11px] uppercase tracking-wider text-[#e7d3a1]">Konsolidasi {{ data.bulan }}</div>
                <div class="font-['Marcellus',serif] text-2xl">{{ formatRupiah(data.total_terkumpul) }}</div>
                <div class="mt-1 text-xs opacity-75">terkumpul dari tagihan {{ formatRupiah(data.total_tagihan) }}</div>
            </div>

            <div class="mb-3 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-1.5 flex justify-between text-xs">
                    <span class="text-[#8a9690]">Kolektibilitas</span>
                    <span class="font-bold text-[#0A5C36]">{{ data.persentase_terkumpul }}%</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-[#ece3cf]">
                    <div class="h-full rounded-full bg-gradient-to-r from-[#0A5C36] to-[#C9A24A]"
                         :style="{ width: Math.min(data.persentase_terkumpul, 100) + '%' }"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-[#ece3cf] bg-white p-3.5">
                    <div class="text-[10px] uppercase tracking-wider text-[#8a9690]">Tagihan Lunas</div>
                    <div class="text-lg font-bold text-emerald-600">{{ data.jumlah_lunas }}</div>
                </div>
                <div class="rounded-xl border border-[#ece3cf] bg-white p-3.5">
                    <div class="text-[10px] uppercase tracking-wider text-[#8a9690]">Belum Lunas</div>
                    <div class="text-lg font-bold text-amber-600">{{ data.jumlah_belum_lunas }}</div>
                </div>
                <div class="col-span-2 rounded-xl border border-[#ece3cf] bg-white p-3.5">
                    <div class="text-[10px] uppercase tracking-wider text-[#8a9690]">Sisa Piutang</div>
                    <div class="text-lg font-bold text-rose-600">{{ formatRupiah(data.sisa_piutang) }}</div>
                </div>
            </div>
        </template>
    </template>
</template>
