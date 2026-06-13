<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    StatusBadge,
    Chip,
    MoneyText,
    EmptyState,
    LoadingState,
} from '@/Shared'

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
    <div class="space-y-5">
        <PageHeader kicker="Penerimaan" title="Tagihan & Skema Biaya" />

        <!-- Filter pills -->
        <div class="flex flex-wrap gap-2">
            <Chip
                v-for="s in ['semua','aktif','lunas','tertunggak']"
                :key="s"
                :active="filter === s"
                size="sm"
                class="capitalize"
                @click="filter = s; fetchData()"
            >
                {{ s }}
            </Chip>
        </div>

        <!-- Skeleton -->
        <LoadingState v-if="loading" variant="list" :count="5" />

        <!-- List -->
        <SectionCard v-else-if="tagihan.length" flush>
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="t in tagihan"
                    :key="t.id"
                    :title="t.nama_santri ?? t.keterangan"
                    :subtitle="`${t.jenis ?? '-'} · ${formatTanggal(t.jatuh_tempo)}`"
                >
                    <template #trailing>
                        <MoneyText :amount="t.nominal" size="sm" tone="emerald" />
                        <div class="mt-1 flex justify-end">
                            <StatusBadge :status="t.status" size="sm" />
                        </div>
                    </template>
                </ListRow>
            </div>
        </SectionCard>

        <EmptyState
            v-else
            icon="receipt"
            title="Tidak ada tagihan ditemukan"
            message="Belum ada tagihan untuk filter ini."
        />
    </div>
</template>
