<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })

const tab = ref('perubahan_aset_neto')
const loading = ref(true)
const data = ref(null)

const tabs = [
    { key: 'perubahan_aset_neto', label: 'Perubahan Aset Neto' },
    { key: 'penghasilan_komprehensif', label: 'Penghasilan Komprehensif' },
    { key: 'posisi_keuangan', label: 'Posisi Keuangan' },
]

async function muat() {
    loading.value = true
    try { data.value = await api.get(`/laporan/isak35?jenis=${tab.value}`) } catch { /* */ }
    loading.value = false
}

function gantiTab(t) { tab.value = t; muat() }
onMounted(muat)
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Laporan ISAK 35</h1>
        <p class="mb-4 text-xs text-[#8a9690]">Entitas Berorientasi Nonlaba</p>

        <div class="mb-4 flex gap-1 overflow-x-auto rounded-xl bg-[#e8e1d0]/60 p-1">
            <button v-for="t in tabs" :key="t.key" @click="gantiTab(t.key)"
                    class="whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="tab === t.key ? 'bg-white text-[#0A5C36] shadow-sm' : 'text-[#8a9690]'">
                {{ t.label }}
            </button>
        </div>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="h-10 animate-pulse rounded-lg bg-[#e8e1d0]/60"></div>
        </div>

        <!-- Perubahan Aset Neto -->
        <template v-else-if="tab === 'perubahan_aset_neto' && data">
            <div v-for="(section, key) in data" :key="key" class="mb-4 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">
                    {{ key.replace(/_/g, ' ') }}
                </div>
                <template v-if="typeof section === 'object' && section !== null">
                    <div v-for="(v, k) in section" :key="k"
                         class="flex justify-between py-1.5 text-sm"
                         :class="k === 'saldo_akhir' || k === 'perubahan' ? 'font-bold' : ''">
                        <span>{{ k.replace(/_/g, ' ') }}</span>
                        <span :class="{ 'text-emerald-600': v > 0 && (k === 'surplus' || k === 'perubahan'), 'text-rose-600': v < 0 }">
                            {{ formatRupiahFull(v) }}
                        </span>
                    </div>
                </template>
            </div>
        </template>

        <!-- Penghasilan Komprehensif -->
        <template v-else-if="tab === 'penghasilan_komprehensif' && data">
            <div v-for="key in ['tanpa_pembatasan', 'dengan_pembatasan']" :key="key"
                 class="mb-4 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">
                    {{ key.replace(/_/g, ' ') }}
                </div>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span>Total Pendapatan</span>
                        <span class="font-semibold">{{ formatRupiahFull(data[key]?.total_pendapatan ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Total Beban</span>
                        <span class="font-semibold">{{ formatRupiahFull(data[key]?.total_beban ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-[#ece3cf] pt-2 font-bold">
                        <span>Surplus / Defisit</span>
                        <span :class="(data[key]?.surplus ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                            {{ formatRupiahFull(data[key]?.surplus ?? 0) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-4 text-center text-white"
                 style="border: 1px solid rgba(201,162,74,0.3);">
                <div class="text-[11px] uppercase tracking-wider text-[#e7d3a1]">Total Surplus</div>
                <div class="font-['Marcellus',serif] text-xl">{{ formatRupiahFull(data.surplus_total ?? 0) }}</div>
            </div>
        </template>

        <!-- Posisi Keuangan -->
        <template v-else-if="tab === 'posisi_keuangan' && data">
            <div class="space-y-4">
                <div class="rounded-xl border border-[#ece3cf] bg-white p-4">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Aset</div>
                    <div v-for="item in (data.aset ?? [])" :key="item.kode"
                         class="flex justify-between py-1.5 text-sm">
                        <span>{{ item.nama ?? item.kode }}</span>
                        <span class="font-semibold">{{ formatRupiahFull(item.saldo ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-[#ece3cf] pt-2 font-bold text-sm">
                        <span>Total Aset</span>
                        <span>{{ formatRupiahFull(data.total_aset ?? 0) }}</span>
                    </div>
                </div>

                <div class="rounded-xl border border-[#ece3cf] bg-white p-4">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Aset Neto</div>
                    <div class="flex justify-between py-1.5 text-sm">
                        <span>Tanpa Pembatasan</span>
                        <span class="font-semibold">{{ formatRupiahFull(data.aset_neto?.tanpa_pembatasan ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 text-sm">
                        <span>Dengan Pembatasan</span>
                        <span class="font-semibold">{{ formatRupiahFull(data.aset_neto?.dengan_pembatasan ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-[#ece3cf] pt-2 font-bold text-sm">
                        <span>Total Aset Neto</span>
                        <span class="text-[#0A5C36]">{{ formatRupiahFull(data.aset_neto?.total ?? 0) }}</span>
                    </div>
                </div>

                <div class="rounded-lg px-3 py-2 text-center text-sm font-semibold"
                     :class="data.seimbang ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                    {{ data.seimbang ? '✓ Neraca Seimbang' : '✗ Neraca Tidak Seimbang' }}
                </div>
            </div>
        </template>
    </div>
</template>
