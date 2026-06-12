<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const items = ref([])
const processing = ref(null)
const filter = ref('menunggu')

async function fetchData() {
    loading.value = true
    try {
        const res = await api.get(`/approval?status=${filter.value}`)
        items.value = res.data ?? []
    } catch { /* */ }
    loading.value = false
}

async function setujui(id) {
    if (processing.value) return
    processing.value = id
    try {
        await api.post(`/approval/${id}/setujui`)
        items.value = items.value.filter(i => i.id !== id)
    } catch { /* */ }
    processing.value = null
}

async function tolak(id) {
    if (processing.value) return
    const alasan = prompt('Alasan penolakan:')
    if (!alasan) return
    processing.value = id
    try {
        await api.post(`/approval/${id}/tolak`, { alasan })
        items.value = items.value.filter(i => i.id !== id)
    } catch { /* */ }
    processing.value = null
}

onMounted(fetchData)
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Kotak Persetujuan</h1>

        <!-- Filter -->
        <div class="mb-4 flex gap-2">
            <button v-for="s in ['menunggu','disetujui','ditolak']" :key="s"
                    class="rounded-full border px-3 py-1.5 text-xs font-semibold capitalize transition"
                    :class="filter === s ? 'border-[#0A5C36] bg-[#0A5C36] text-white' : 'border-[#ece3cf] bg-white text-[#3a4d42]'"
                    @click="filter = s; fetchData()">
                {{ s }}
            </button>
        </div>

        <div v-if="loading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
        </div>

        <div v-else-if="items.length" class="space-y-3">
            <div v-for="item in items" :key="item.id"
                 class="rounded-xl border border-[#ece3cf] bg-white p-4">
                <!-- Header -->
                <div class="mb-2 flex items-start justify-between">
                    <div>
                        <div class="text-sm font-medium">{{ item.keterangan }}</div>
                        <div class="text-xs text-[#8a9690]">{{ item.pemohon }} · {{ item.divisi }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold text-[#0A5C36]">{{ formatRupiah(item.nominal) }}</div>
                        <div class="text-[10px] text-[#8a9690]">{{ formatTanggal(item.tanggal) }}</div>
                    </div>
                </div>

                <!-- Budget warning -->
                <div v-if="item.pagu_persen != null && item.pagu_persen > 80"
                     class="mb-2 rounded-lg px-3 py-1.5 text-[11px] font-semibold"
                     :class="item.pagu_persen > 90 ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700'">
                    Anggaran divisi terpakai {{ item.pagu_persen }}%
                </div>

                <!-- Actions -->
                <div v-if="filter === 'menunggu'" class="flex gap-2">
                    <button @click="setujui(item.id)"
                            :disabled="processing === item.id"
                            class="flex-1 rounded-lg bg-[#0A5C36] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#08472a] disabled:opacity-50">
                        Setujui
                    </button>
                    <button @click="tolak(item.id)"
                            :disabled="processing === item.id"
                            class="flex-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100 disabled:opacity-50">
                        Tolak
                    </button>
                </div>

                <!-- Status badge for non-pending -->
                <div v-else class="mt-1">
                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase"
                          :class="item.status === 'disetujui' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                        {{ item.status }}
                    </span>
                </div>
            </div>
        </div>

        <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-8 text-center text-sm text-[#8a9690]">
            {{ filter === 'menunggu' ? 'Tidak ada pengajuan menunggu persetujuan' : 'Tidak ada data' }}
        </div>
    </div>
</template>
