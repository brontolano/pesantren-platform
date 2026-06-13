<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    MoneyText,
    StatusBadge,
    LoadingState,
    EmptyState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const data = ref([])

onMounted(async () => {
    try {
        const res = await api.get('/anggaran/realisasi')
        data.value = res.data ?? []
    } catch { /* */ }
    loading.value = false
})
</script>

<template>
    <div class="space-y-5">
        <PageHeader
            kicker="Pengawasan Belanja"
            title="Anggaran & Realisasi"
            subtitle="Pantau penyerapan pagu setiap divisi."
        />

        <LoadingState v-if="loading" variant="list" :count="4" />

        <SectionCard v-else-if="data.length" flush>
            <div class="divide-y divide-slate-100">
                <ListRow v-for="d in data" :key="d.divisi" :title="d.divisi">
                    <template #trailing>
                        <StatusBadge
                            size="sm"
                            :status="(d.realisasi / (d.pagu || 1)) > 0.9 ? 'belum_bayar' : (d.realisasi / (d.pagu || 1)) > 0.8 ? 'menunggu' : 'aktif'"
                            :label="Math.round((d.realisasi / (d.pagu || 1)) * 100) + '%'"
                        />
                    </template>

                    <div class="space-y-2">
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full transition-all"
                                 :class="(d.realisasi / (d.pagu || 1)) > 0.9 ? 'bg-rose-500' : (d.realisasi / (d.pagu || 1)) > 0.8 ? 'bg-amber-500' : 'bg-[var(--wali-primary,#059669)]'"
                                 :style="{ width: Math.min(100, (d.realisasi / (d.pagu || 1)) * 100) + '%' }"></div>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Realisasi</p>
                                <MoneyText :amount="d.realisasi" size="sm" />
                            </div>
                            <div class="min-w-0 text-right">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Pagu</p>
                                <MoneyText :amount="d.pagu" size="sm" tone="muted" />
                            </div>
                        </div>
                    </div>
                </ListRow>
            </div>
        </SectionCard>

        <EmptyState
            v-else
            icon="chart"
            title="Data anggaran belum tersedia"
            message="Realisasi belanja tetap bisa dilihat di Jurnal."
        />
    </div>
</template>
