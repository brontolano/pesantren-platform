<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const tagihan = ref([])
const filter = ref('semua')

async function fetchData() {
    loading.value = true
    try {
        const res = await api.get(`/tagihan?status=${filter.value}`)
        tagihan.value = res.data ?? []
    } catch { /* handled by skeleton */ }
    loading.value = false
}

onMounted(fetchData)
</script>

<template>
    <div>
        <h1 class="mb-4 font-['Marcellus',serif] text-lg text-[#0A5C36]">Tagihan & Skema Biaya</h1>

        <!-- Filter tabs -->
        <div class="mb-4 flex gap-2">
            <button v-for="s in ['semua','aktif','lunas','tertunggak']" :key="s"
                    class="rounded-full border px-3 py-1.5 text-xs font-semibold capitalize transition"
                    :class="filter === s ? 'border-[#0A5C36] bg-[#0A5C36] text-white' : 'border-[#ece3cf] bg-white text-[#3a4d42]'"
                    @click="filter = s; fetchData()">
                {{ s }}
            </button>
        </div>

        <!-- Skeleton -->
        <div v-if="loading" class="space-y-3">
            <div v-for="i in 5" :key="i" class="h-16 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
        </div>

        <!-- List -->
        <div v-else-if="tagihan.length" class="space-y-2">
            <div v-for="t in tagihan" :key="t.id"
                 class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium">{{ t.nama_santri ?? t.keterangan }}</div>
                    <div class="text-xs text-[#8a9690]">{{ t.jenis ?? '-' }} · {{ formatTanggal(t.jatuh_tempo) }}</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-semibold text-[#0A5C36]">{{ formatRupiah(t.nominal) }}</div>
                    <span class="text-[10px] font-bold uppercase"
                          :class="t.status === 'lunas' ? 'text-emerald-600' : t.status === 'tertunggak' ? 'text-rose-600' : 'text-amber-600'">
                        {{ t.status }}
                    </span>
                </div>
            </div>
        </div>

        <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-8 text-center text-sm text-[#8a9690]">
            Tidak ada tagihan ditemukan
        </div>
    </div>
</template>
