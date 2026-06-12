<script setup>
import { onMounted, ref, computed } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull, formatTanggal } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })

const tab = ref('spt-masa')
const loading = ref(true)
const data = ref(null)
const masaPajak = ref(new Date().toISOString().slice(0, 7))
const tahun = ref(String(new Date().getFullYear()))

async function muat() {
    loading.value = true
    data.value = null
    try {
        if (tab.value === 'spt-masa') {
            data.value = await api.get(`/pajak/spt-masa?masa=${masaPajak.value}`)
        } else if (tab.value === 'ebupot') {
            data.value = await api.get(`/pajak/ebupot?masa=${masaPajak.value}`)
        } else if (tab.value === 'spt-badan') {
            data.value = await api.get(`/pajak/spt-badan?tahun=${tahun.value}`)
        } else if (tab.value === 'sisa-lebih') {
            data.value = await api.get('/pajak/sisa-lebih')
        }
    } catch { /* */ }
    loading.value = false
}

function gantiTab(t) {
    tab.value = t
    muat()
}

onMounted(muat)

const tabs = [
    { key: 'spt-masa', label: 'SPT Masa PPh 21' },
    { key: 'ebupot', label: 'e-Bupot 21' },
    { key: 'spt-badan', label: 'SPT Badan' },
    { key: 'sisa-lebih', label: 'Sisa Lebih' },
]
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Pusat Pajak</h1>

        <!-- Tabs -->
        <div class="mb-4 flex gap-1 overflow-x-auto rounded-xl bg-[#e8e1d0]/60 p-1">
            <button v-for="t in tabs" :key="t.key"
                    @click="gantiTab(t.key)"
                    class="whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="tab === t.key ? 'bg-white text-[#0A5C36] shadow-sm' : 'text-[#8a9690]'">
                {{ t.label }}
            </button>
        </div>

        <!-- Filter -->
        <div class="mb-4 flex gap-2">
            <input v-if="tab !== 'sisa-lebih' && tab !== 'spt-badan'"
                   v-model="masaPajak" type="month" @change="muat"
                   class="rounded-lg border border-[#ece3cf] px-3 py-1.5 text-sm">
            <input v-if="tab === 'spt-badan'"
                   v-model="tahun" type="number" min="2020" max="2030" @change="muat"
                   class="w-24 rounded-lg border border-[#ece3cf] px-3 py-1.5 text-sm">
        </div>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="h-10 animate-pulse rounded-lg bg-[#e8e1d0]/60"></div>
        </div>

        <!-- SPT Masa -->
        <template v-else-if="tab === 'spt-masa' && data">
            <div class="rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Ringkasan SPT Masa {{ data.masa_pajak }}</div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Pegawai Tetap</span>
                        <span class="font-semibold">{{ data.bagian_a?.pegawai_tetap?.jumlah_penerima ?? 0 }} orang</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Bruto Pegawai Tetap</span>
                        <span class="font-semibold">{{ formatRupiahFull(data.bagian_a?.pegawai_tetap?.bruto ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>PPh 21 Pegawai Tetap</span>
                        <span class="font-bold text-[#0A5C36]">{{ formatRupiahFull(data.bagian_a?.pegawai_tetap?.pph21 ?? 0) }}</span>
                    </div>
                    <hr class="border-[#ece3cf]">
                    <div class="flex justify-between font-bold">
                        <span>Total PPh 21 Disetor</span>
                        <span class="text-[#0A5C36]">{{ formatRupiahFull(data.bagian_b?.pph21_disetor ?? 0) }}</span>
                    </div>
                    <div class="text-xs text-[#8a9690]">MAP {{ data.bagian_b?.kode_map }} / KJS {{ data.bagian_b?.kode_kjs }}</div>
                </div>
            </div>

            <div v-if="data.lampiran?.length" class="mt-4 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Lampiran</div>
                <div v-for="(r, i) in data.lampiran" :key="i"
                     class="flex justify-between border-b border-[#ece3cf] py-2 text-sm last:border-0">
                    <div>
                        <div class="font-medium">{{ r.nama }}</div>
                        <div class="text-xs text-[#8a9690]">{{ r.kode_objek }} · {{ r.npwp || 'Tanpa NPWP' }}</div>
                    </div>
                    <div class="text-right">
                        <div>{{ formatRupiahFull(r.bruto) }}</div>
                        <div class="text-xs font-semibold text-[#0A5C36]">PPh {{ formatRupiahFull(r.pph21) }}</div>
                    </div>
                </div>
            </div>
        </template>

        <!-- e-Bupot -->
        <template v-else-if="tab === 'ebupot' && data">
            <div class="rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-xs font-bold uppercase tracking-wider text-[#8a9690]">Bukti Potong PPh 21</div>
                    <a :href="`/keuangan/api/pajak/ebupot/csv?masa=${masaPajak}`"
                       class="rounded-lg bg-[#0A5C36] px-3 py-1 text-xs font-bold text-white">
                        Download CSV
                    </a>
                </div>
                <div class="mb-3 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span>Jumlah Bukti Potong</span>
                        <span class="font-semibold">{{ data.summary?.jumlah_bukti_potong ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Total Bruto</span>
                        <span class="font-semibold">{{ formatRupiahFull(data.summary?.total_bruto ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between font-bold">
                        <span>Total PPh 21</span>
                        <span class="text-[#0A5C36]">{{ formatRupiahFull(data.summary?.total_pph21 ?? 0) }}</span>
                    </div>
                </div>

                <div v-for="(r, i) in data.rows" :key="i"
                     class="flex justify-between border-t border-[#ece3cf] py-2 text-sm">
                    <div>
                        <div class="font-medium">{{ r.nama }}</div>
                        <div class="text-xs text-[#8a9690]">NIK {{ r.nik || '—' }} · NPWP {{ r.npwp || '—' }}</div>
                    </div>
                    <div class="text-right text-xs">
                        <div>Bruto {{ formatRupiahFull(r.bruto) }}</div>
                        <div class="font-semibold text-[#0A5C36]">PPh {{ formatRupiahFull(r.pph21_dipotong) }}</div>
                    </div>
                </div>
            </div>
        </template>

        <!-- SPT Badan -->
        <template v-else-if="tab === 'spt-badan' && data">
            <div class="space-y-4">
                <div class="rounded-xl border border-[#ece3cf] bg-white p-4">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Pemisahan Pendapatan {{ data.tahun }}</div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>Pendapatan Pendidikan</span>
                            <div class="text-right">
                                <div class="font-semibold">{{ formatRupiahFull(data.pendapatan?.pendidikan?.nominal ?? 0) }}</div>
                                <div class="text-xs text-emerald-600">{{ data.pendapatan?.pendidikan?.status_pajak }}</div>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <span>Pendapatan Komersial</span>
                            <div class="text-right">
                                <div class="font-semibold">{{ formatRupiahFull(data.pendapatan?.komersial?.nominal ?? 0) }}</div>
                                <div class="text-xs text-rose-600">{{ data.pendapatan?.komersial?.status_pajak }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-[#ece3cf] bg-white p-4">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Kalkulasi PPh Badan</div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>Laba Fiskal Komersial</span>
                            <span class="font-semibold">{{ formatRupiahFull(data.laba_fiskal?.komersial ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>PKP</span>
                            <span class="font-semibold">{{ formatRupiahFull(data.penghasilan_kena_pajak ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between font-bold">
                            <span>PPh Badan ({{ data.tarif_pph_badan }}%)</span>
                            <span class="text-[#0A5C36]">{{ formatRupiahFull(data.pph_badan_terutang ?? 0) }}</span>
                        </div>
                    </div>
                </div>

                <p class="text-[11px] text-[#8a9690]">
                    {{ data.koreksi_fiskal?.catatan }}
                </p>
            </div>
        </template>

        <!-- Sisa Lebih -->
        <template v-else-if="tab === 'sisa-lebih' && data">
            <div v-if="!data.length" class="rounded-xl bg-[#e8e1d0]/40 p-6 text-center text-sm text-[#8a9690]">
                Belum ada data sisa lebih. Data terisi otomatis setelah tutup buku tahunan.
            </div>
            <div v-for="item in data" :key="item.tahun_fiskal"
                 class="mb-3 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-2 flex items-center justify-between">
                    <span class="font-['Marcellus',serif] text-sm font-bold text-[#0A5C36]">Tahun {{ item.tahun_fiskal }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                          :class="{
                              'bg-emerald-100 text-emerald-700': item.status === 'terpenuhi',
                              'bg-amber-100 text-amber-700': item.status === 'terbuka',
                              'bg-rose-100 text-rose-700': item.status === 'kena_pajak',
                          }">
                        {{ item.status === 'terpenuhi' ? 'Terpenuhi' : item.status === 'kena_pajak' ? 'Kena Pajak' : 'Terbuka' }}
                    </span>
                </div>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-[#8a9690]">Surplus Pendidikan</span>
                        <span class="font-semibold">{{ formatRupiahFull(item.surplus) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#8a9690]">Teralokasi</span>
                        <span class="font-semibold text-emerald-600">{{ formatRupiahFull(item.teralokasi) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#8a9690]">Sisa Belum Reinvestasi</span>
                        <span class="font-bold" :class="item.sisa > 0 ? 'text-amber-600' : 'text-emerald-600'">
                            {{ formatRupiahFull(item.sisa) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#8a9690]">Batas Waktu</span>
                        <span :class="item.hari_tersisa < 365 ? 'text-rose-600 font-bold' : ''">
                            {{ item.batas }} ({{ item.hari_tersisa }} hari)
                        </span>
                    </div>
                </div>

                <div v-if="item.alokasi?.length" class="mt-3 border-t border-[#ece3cf] pt-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9690]">Riwayat Reinvestasi</div>
                    <div v-for="(a, i) in item.alokasi" :key="i" class="flex justify-between py-1 text-xs">
                        <span>{{ a.tanggal }} — {{ a.keterangan }}</span>
                        <span class="font-semibold">{{ formatRupiahFull(a.nominal) }}</span>
                    </div>
                </div>
            </div>
        </template>

        <div v-else-if="!loading" class="rounded-xl bg-[#e8e1d0]/40 p-6 text-center text-sm text-[#8a9690]">
            Tidak ada data untuk ditampilkan.
        </div>
    </div>
</template>
