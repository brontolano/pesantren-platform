<script setup>
import { computed, onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })

const loading = ref(true)
const rows = ref([])
const error = ref(null)

const totalDebit = computed(() => rows.value.reduce((s, r) => s + (r.debit ?? 0), 0))
const totalKredit = computed(() => rows.value.reduce((s, r) => s + (r.kredit ?? 0), 0))
const seimbang = computed(() => Math.abs(totalDebit.value - totalKredit.value) < 0.01)

onMounted(async () => {
    try {
        rows.value = await api.get('/neraca-saldo')
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="mb-3 flex items-center justify-between">
        <h2 class="font-['Marcellus',serif] text-lg text-[#0A5C36]">Neraca Saldo</h2>
        <span v-if="!loading && !error" class="rounded-full px-2.5 py-1 text-[11px] font-bold"
              :class="seimbang ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
            {{ seimbang ? '✓ Seimbang' : '✗ Tidak Seimbang' }}
        </span>
    </div>

    <div v-if="loading" class="space-y-2">
        <div v-for="i in 6" :key="i" class="h-12 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
    </div>

    <div v-else-if="error" class="rounded-2xl bg-red-50 px-4 py-5 text-sm text-red-700">
        Gagal memuat: {{ error }}
    </div>

    <template v-else>
        <div class="overflow-hidden rounded-xl border border-[#ece3cf] bg-white">
            <div class="grid grid-cols-[1fr_auto_auto] gap-2 border-b border-[#ece3cf] bg-[#FDFBF7] px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider text-[#8a9690]">
                <span>Akun</span><span class="text-right w-24">Debit</span><span class="text-right w-24">Kredit</span>
            </div>
            <div v-for="r in rows" :key="r.akun_id"
                 class="grid grid-cols-[1fr_auto_auto] items-center gap-2 border-b border-[#f3eee0] px-4 py-2.5 last:border-0">
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium">{{ r.nama }}</div>
                    <div class="text-[11px] text-[#8a9690]">{{ r.kode }} · {{ r.tipe }}</div>
                </div>
                <span class="w-24 text-right text-xs font-semibold text-[#0A5C36]">{{ r.debit ? formatRupiah(r.debit) : '–' }}</span>
                <span class="w-24 text-right text-xs font-semibold text-[#0A5C36]">{{ r.kredit ? formatRupiah(r.kredit) : '–' }}</span>
            </div>
            <div class="grid grid-cols-[1fr_auto_auto] gap-2 bg-[#0A5C36] px-4 py-3 text-white">
                <span class="text-sm font-bold">TOTAL</span>
                <span class="w-24 text-right text-xs font-bold">{{ formatRupiah(totalDebit) }}</span>
                <span class="w-24 text-right text-xs font-bold">{{ formatRupiah(totalKredit) }}</span>
            </div>
        </div>
    </template>
</template>
