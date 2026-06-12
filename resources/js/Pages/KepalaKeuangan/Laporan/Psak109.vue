<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiahFull } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })

const tab = ref('perubahan_dana')
const loading = ref(true)
const data = ref(null)

const tabs = [
    { key: 'perubahan_dana', label: 'Perubahan Dana' },
    { key: 'posisi_keuangan', label: 'Posisi Keuangan' },
    { key: 'aset_kelolaan', label: 'Aset Kelolaan' },
]

async function muat() {
    loading.value = true
    try { data.value = await api.get(`/laporan/psak109?jenis=${tab.value}`) } catch { /* */ }
    loading.value = false
}

function gantiTab(t) { tab.value = t; muat() }

onMounted(muat)

function danaRows(d) {
    return [
        { label: 'Saldo Awal', nilai: d?.saldo_awal ?? 0 },
        { label: 'Penerimaan', nilai: d?.penerimaan ?? 0, hijau: true },
        { label: 'Penyaluran', nilai: d?.penyaluran ?? 0, merah: true },
        { label: 'Saldo Akhir', nilai: d?.saldo_akhir ?? 0, bold: true },
    ]
}
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Laporan PSAK 109</h1>
        <p class="mb-4 text-xs text-[#8a9690]">Akuntansi Zakat, Infak/Sedekah</p>

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

        <!-- Perubahan Dana -->
        <template v-else-if="tab === 'perubahan_dana' && data">
            <div v-for="(dana, key) in data" :key="key" class="mb-4 rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">
                    {{ key.replace(/_/g, ' ') }}
                </div>
                <div v-for="r in danaRows(dana)" :key="r.label"
                     class="flex justify-between border-b border-[#ece3cf] px-1 py-2 text-sm last:border-0"
                     :class="r.bold ? 'font-bold' : ''">
                    <span>{{ r.label }}</span>
                    <span :class="{ 'text-emerald-600': r.hijau, 'text-rose-600': r.merah }">
                        {{ formatRupiahFull(r.nilai) }}
                    </span>
                </div>
            </div>
        </template>

        <!-- Posisi Keuangan -->
        <template v-else-if="tab === 'posisi_keuangan' && data">
            <div class="space-y-4">
                <div class="rounded-xl border border-[#ece3cf] bg-white p-4">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Aset</div>
                    <div v-for="(v, k) in data.aset" :key="k" class="flex justify-between py-1.5 text-sm">
                        <span>{{ k.replace(/_/g, ' ') }}</span>
                        <span class="font-semibold">{{ formatRupiahFull(v) }}</span>
                    </div>
                </div>
                <div class="rounded-xl border border-[#ece3cf] bg-white p-4">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Saldo Dana</div>
                    <div v-for="(v, k) in data.saldo_dana" :key="k" class="flex justify-between py-1.5 text-sm"
                         :class="k === 'total' ? 'font-bold border-t border-[#ece3cf] pt-2' : ''">
                        <span>{{ k.replace(/_/g, ' ') }}</span>
                        <span :class="k === 'total' ? 'text-[#0A5C36]' : ''">{{ formatRupiahFull(v) }}</span>
                    </div>
                </div>
            </div>
        </template>

        <!-- Aset Kelolaan -->
        <template v-else-if="tab === 'aset_kelolaan' && data">
            <div class="rounded-xl border border-[#ece3cf] bg-white p-4">
                <div class="mb-2 text-xs font-bold uppercase tracking-wider text-[#8a9690]">Perubahan Aset Kelolaan</div>
                <div v-for="(v, k) in data" :key="k" class="flex justify-between py-1.5 text-sm"
                     :class="k === 'saldo_akhir' ? 'font-bold border-t border-[#ece3cf] pt-2' : ''">
                    <span>{{ k.replace(/_/g, ' ') }}</span>
                    <span>{{ formatRupiahFull(v) }}</span>
                </div>
            </div>
        </template>
    </div>
</template>
