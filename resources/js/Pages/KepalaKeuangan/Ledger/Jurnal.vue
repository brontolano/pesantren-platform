<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal } from '@/keuangan-pwa/format.js'
import { PageHeader, SectionCard, ListRow, MoneyText, LoadingState, EmptyState } from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const jurnals = ref([])

onMounted(async () => {
    try {
        const res = await api.get('/jurnal')
        jurnals.value = res.data ?? []
    } catch { /* */ }
    loading.value = false
})
</script>

<template>
    <div class="space-y-5">
        <PageHeader title="Jurnal & Buku Besar" />

        <LoadingState v-if="loading" variant="list" :count="5" />

        <SectionCard v-else-if="jurnals.length" flush>
            <div class="divide-y divide-slate-100">
                <ListRow v-for="j in jurnals" :key="j.id" :title="j.nomor">
                    <template v-if="j.deskripsi" #subtitle>{{ j.deskripsi }}</template>
                    <template #trailing>
                        <MoneyText :amount="j.total_debit" size="sm" tone="emerald" />
                    </template>
                    <p class="text-xs text-slate-400 tabular-nums">{{ formatTanggal(j.tanggal) }}</p>
                </ListRow>
            </div>
        </SectionCard>

        <EmptyState
            v-else
            icon="book"
            title="Belum ada jurnal"
            message="Belum ada jurnal di periode ini"
        />
    </div>
</template>
