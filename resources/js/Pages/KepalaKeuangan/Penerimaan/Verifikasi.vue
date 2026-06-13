<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal, formatWaktu } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    IconCircle,
    MoneyText,
    UiButton,
    EmptyState,
    LoadingState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })
defineProps({ can: Object })

const loading = ref(true)
const items = ref([])
const processing = ref(null)

async function fetchData() {
    loading.value = true
    try {
        const res = await api.get('/pembayaran/pending')
        items.value = res.data ?? []
    } catch { /* */ }
    loading.value = false
}

async function verifikasi(id) {
    if (processing.value) return
    processing.value = id
    try {
        await api.post(`/pembayaran/${id}/verifikasi`)
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
        await api.post(`/pembayaran/${id}/tolak`, { alasan })
        items.value = items.value.filter(i => i.id !== id)
    } catch { /* */ }
    processing.value = null
}

onMounted(fetchData)
</script>

<template>
    <div class="space-y-5">
        <PageHeader
            kicker="Penerimaan"
            title="Verifikasi Pembayaran"
            subtitle="Tinjau dan setujui pembayaran yang masuk"
        />

        <LoadingState v-if="loading" variant="list" :count="4" />

        <div v-else-if="items.length" class="space-y-4">
            <SectionCard v-for="item in items" :key="item.id" flush>
                <ListRow>
                    <template #leading>
                        <IconCircle icon="cash" tone="emerald" />
                    </template>
                    <template #title>{{ item.nama_santri }}</template>
                    <template #subtitle>
                        {{ item.metode ?? 'Transfer' }} · {{ formatTanggal(item.tanggal) }} {{ formatWaktu(item.tanggal) }}
                    </template>
                    <template #trailing>
                        <MoneyText :amount="item.nominal" size="md" tone="emerald" />
                    </template>

                    <div class="flex gap-2">
                        <UiButton
                            variant="primary"
                            size="sm"
                            block
                            iconLeft="check"
                            :loading="processing === item.id"
                            :disabled="processing === item.id"
                            @click="verifikasi(item.id)"
                        >
                            Verifikasi
                        </UiButton>
                        <UiButton
                            variant="danger"
                            size="sm"
                            block
                            iconLeft="x-circle"
                            :disabled="processing === item.id"
                            @click="tolak(item.id)"
                        >
                            Tolak
                        </UiButton>
                    </div>
                </ListRow>
            </SectionCard>
        </div>

        <EmptyState
            v-else
            icon="check-circle"
            tone="emerald"
            title="Semua pembayaran sudah diverifikasi"
            message="Tidak ada pembayaran yang menunggu verifikasi saat ini."
        />
    </div>
</template>
