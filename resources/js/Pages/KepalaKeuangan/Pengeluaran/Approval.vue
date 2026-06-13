<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatTanggal } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    ListRow,
    IconCircle,
    UiIcon,
    MoneyText,
    UiButton,
    StatusBadge,
    SegmentedControl,
    EmptyState,
    LoadingState,
} from '@/Shared'

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
    <div class="space-y-5">
        <PageHeader
            kicker="Pengeluaran"
            title="Kotak Persetujuan"
            subtitle="Tinjau dan putuskan pengajuan pengeluaran"
        />

        <!-- Filter status -->
        <SegmentedControl
            :model-value="filter"
            :options="[
                { value: 'menunggu', label: 'Menunggu', icon: 'clock' },
                { value: 'disetujui', label: 'Disetujui', icon: 'check-circle' },
                { value: 'ditolak', label: 'Ditolak', icon: 'x-circle' },
            ]"
            @update:model-value="filter = $event; fetchData()"
        />

        <LoadingState v-if="loading" variant="list" :count="4" />

        <div v-else-if="items.length" class="space-y-4">
            <SectionCard v-for="item in items" :key="item.id" flush>
                <ListRow>
                    <template #leading>
                        <IconCircle icon="receipt" tone="emerald" />
                    </template>
                    <template #title>{{ item.keterangan }}</template>
                    <template #subtitle>{{ item.pemohon }} · {{ item.divisi }}</template>
                    <template #trailing>
                        <MoneyText :amount="item.nominal" size="md" tone="emerald" />
                        <div class="mt-0.5 text-[11px] text-slate-400 tabular-nums">
                            {{ formatTanggal(item.tanggal) }}
                        </div>
                    </template>

                    <!-- Peringatan anggaran -->
                    <div
                        v-if="item.pagu_persen != null && item.pagu_persen > 80"
                        class="mb-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-semibold"
                        :class="item.pagu_persen > 90 ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-100' : 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-100'"
                    >
                        <UiIcon name="info" class="h-3.5 w-3.5 shrink-0" />
                        Anggaran divisi terpakai {{ item.pagu_persen }}%
                    </div>

                    <!-- Aksi (status menunggu) -->
                    <div v-if="filter === 'menunggu'" class="flex gap-2">
                        <UiButton
                            variant="primary"
                            size="sm"
                            block
                            iconLeft="check"
                            :loading="processing === item.id"
                            :disabled="processing === item.id"
                            @click="setujui(item.id)"
                        >
                            Setujui
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

                    <!-- Badge status (selain menunggu) -->
                    <div v-else>
                        <StatusBadge
                            :status="item.status === 'disetujui' ? 'terverifikasi' : item.status"
                            :label="item.status === 'disetujui' ? 'Disetujui' : 'Ditolak'"
                            size="sm"
                        />
                    </div>
                </ListRow>
            </SectionCard>
        </div>

        <EmptyState
            v-else
            :icon="filter === 'menunggu' ? 'check-circle' : 'inbox'"
            :tone="filter === 'menunggu' ? 'emerald' : 'slate'"
            :title="filter === 'menunggu' ? 'Tidak ada pengajuan menunggu persetujuan' : 'Tidak ada data'"
            :message="filter === 'menunggu' ? 'Semua pengajuan pengeluaran sudah diputuskan.' : null"
        />
    </div>
</template>
