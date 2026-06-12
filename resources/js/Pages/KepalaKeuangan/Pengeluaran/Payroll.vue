<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
const props = defineProps({ can: Object })

const loading = ref(true)
const periodes = ref([])
const selectedPeriode = ref(null)
const slips = ref([])
const ringkasan = ref(null)
const processing = ref(false)

async function fetchPeriodes() {
    loading.value = true
    try {
        const res = await api.get('/payroll/periode')
        periodes.value = res.data ?? []
        if (periodes.value.length) {
            selectedPeriode.value = periodes.value[0]
            await fetchSlips(periodes.value[0].id)
        }
    } catch { /* */ }
    loading.value = false
}

async function fetchSlips(periodeId) {
    try {
        const res = await api.get(`/payroll/slip?periode_id=${periodeId}`)
        slips.value = res.data ?? []
        ringkasan.value = res.ringkasan ?? null
    } catch { /* */ }
}

async function prosesGaji() {
    if (!selectedPeriode.value || processing.value) return
    if (!confirm(`Proses gaji periode ${selectedPeriode.value.label}? Aksi ini tidak dapat dibatalkan.`)) return
    if (!confirm('Anda yakin? Slip gaji akan diterbitkan dan jurnal akan diposting.')) return
    processing.value = true
    try {
        await api.post('/payroll/proses', { periode_id: selectedPeriode.value.id })
        await fetchSlips(selectedPeriode.value.id)
    } catch { /* */ }
    processing.value = false
}

onMounted(fetchPeriodes)
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Payroll & PPh21</h1>

        <div v-if="loading" class="space-y-3">
            <div class="h-20 animate-pulse rounded-2xl bg-[#e8e1d0]/60"></div>
            <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
        </div>

        <template v-else>
            <!-- Periode selector -->
            <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
                <button v-for="p in periodes" :key="p.id"
                        class="flex-shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                        :class="selectedPeriode?.id === p.id ? 'border-[#0A5C36] bg-[#0A5C36] text-white' : 'border-[#ece3cf] bg-white text-[#3a4d42]'"
                        @click="selectedPeriode = p; fetchSlips(p.id)">
                    {{ p.label }}
                </button>
            </div>

            <!-- Ringkasan -->
            <div v-if="ringkasan" class="mb-4 rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-5 text-white"
                 style="border: 1px solid rgba(201,162,74,0.3);">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-[10px] uppercase tracking-wider text-[#e7d3a1]">Total Gaji Kotor</div>
                        <div class="font-['Marcellus',serif] text-lg">{{ formatRupiah(ringkasan.totalKotor) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-wider text-[#e7d3a1]">Total Bersih</div>
                        <div class="font-['Marcellus',serif] text-lg">{{ formatRupiah(ringkasan.totalBersih) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-wider text-[#e7d3a1]">PPh21</div>
                        <div class="text-sm font-semibold">{{ formatRupiah(ringkasan.totalPph) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-wider text-[#e7d3a1]">Pegawai</div>
                        <div class="text-sm font-semibold">{{ ringkasan.jumlahPegawai }} orang</div>
                    </div>
                </div>
            </div>

            <!-- Proses button -->
            <div v-if="props.can?.run_payroll && selectedPeriode && !selectedPeriode.diproses" class="mb-4">
                <button @click="prosesGaji" :disabled="processing"
                        class="w-full rounded-xl bg-[#C9A24A] px-4 py-3 text-sm font-bold text-white shadow transition hover:bg-[#b8913f] disabled:opacity-50">
                    {{ processing ? 'Memproses...' : 'Proses Gaji Periode Ini' }}
                </button>
            </div>

            <!-- Slip list -->
            <h2 class="mb-2 text-sm font-semibold text-[#3a4d42]">Daftar Slip</h2>
            <div v-if="slips.length" class="space-y-2">
                <div v-for="s in slips" :key="s.id"
                     class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                    <div>
                        <div class="text-sm font-medium">{{ s.nama_pegawai }}</div>
                        <div class="text-xs text-[#8a9690]">{{ s.jabatan ?? '-' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-semibold text-[#0A5C36]">{{ formatRupiah(s.gaji_bersih) }}</div>
                        <div v-if="s.pph" class="text-[10px] text-[#8a9690]">PPh: {{ formatRupiah(s.pph) }}</div>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-6 text-center text-sm text-[#8a9690]">
                Belum ada slip gaji di periode ini
            </div>
        </template>
    </div>
</template>
