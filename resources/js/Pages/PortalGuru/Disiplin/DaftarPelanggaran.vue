<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import PortalGuruLayout from '../../../Layouts/PortalGuruLayout.vue'
import {
    PageHeader,
    SectionCard,
    UiCard,
    ListRow,
    StatCard,
    StatusBadge,
    UiButton,
    UiField,
    UiIcon,
    IconCircle,
    EmptyState,
    LoadingState,
} from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    hari: { type: String, required: true },
    tanggal: { type: String, required: true },
    istilah: { type: Object, default: () => ({}) },
})

const bulan = ref(new Date().toISOString().slice(0, 7))
const statusFilter = ref('diverifikasi')
const daftar = ref([])
const loading = ref(false)
const error = ref('')

const statusOptions = [
    { value: 'diverifikasi', label: 'Terverifikasi' },
    { value: 'dicatat', label: 'Pending Verifikasi' },
    { value: 'all', label: 'Semua Status' },
]

const kategoriColor = {
    'ringan': 'bg-emerald-50 border-emerald-200 text-emerald-700',
    'sedang': 'bg-amber-50 border-amber-200 text-amber-700',
    'berat': 'bg-red-50 border-red-200 text-red-700',
    'prestasi': 'bg-blue-50 border-blue-200 text-blue-700',
}

const kategoriBadge = {
    'ringan': 'bg-emerald-100 text-emerald-800',
    'sedang': 'bg-amber-100 text-amber-800',
    'berat': 'bg-red-100 text-red-800',
    'prestasi': 'bg-blue-100 text-blue-800',
}

const fetchDaftar = async () => {
    loading.value = true
    error.value = ''
    try {
        const params = new URLSearchParams({
            bulan: bulan.value,
            status: statusFilter.value,
        })
        const res = await fetch(`/api/pwa/guru/disiplin/daftar?${params}`, {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('sanctum_token')}`,
                'Accept': 'application/json',
            },
        })
        const json = await res.json()
        if (json.status === 'success') {
            daftar.value = json.data || []
        } else {
            error.value = json.message || 'Gagal memuat daftar'
        }
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

const handleFilterChange = () => {
    fetchDaftar()
}

const totalPoinSemua = computed(() => {
    return daftar.value.reduce((sum, santri) => sum + santri.poin_bulan_ini, 0)
})

const totalPelangaranSemua = computed(() => {
    return daftar.value.reduce((sum, santri) => sum + santri.total_pelanggaran, 0)
})

// Load on mount
import { onMounted } from 'vue'
onMounted(() => {
    fetchDaftar()
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Daftar Pelanggaran</title>
        </Head>

        <PageHeader
            kicker="Kepengasuhan · Disiplin"
            title="Daftar Pelanggaran"
            :subtitle="`Pelanggaran santri bulan ${bulan}`"
        />

        <!-- Filter -->
        <SectionCard>
            <div class="space-y-3">
                <UiField label="Bulan">
                    <template #default="{ fieldId }">
                        <input
                            :id="fieldId"
                            v-model="bulan"
                            type="month"
                            @change="handleFilterChange"
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        />
                    </template>
                </UiField>
                <UiField label="Status">
                    <template #default="{ fieldId }">
                        <select
                            :id="fieldId"
                            v-model="statusFilter"
                            @change="handleFilterChange"
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        >
                            <option
                                v-for="opt in statusOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </template>
                </UiField>
            </div>
        </SectionCard>

        <!-- Error -->
        <UiCard v-if="error" padding="md">
            <div class="flex items-start gap-3">
                <IconCircle icon="x-circle" tone="rose" size="sm" />
                <p class="text-sm font-semibold text-rose-700">{{ error }}</p>
            </div>
        </UiCard>

        <!-- Loading -->
        <LoadingState v-if="loading" variant="card" :count="3" />

        <!-- Stats -->
        <section v-if="!loading && daftar.length > 0" class="grid grid-cols-2 gap-3">
            <StatCard
                icon="chart"
                tone="amber"
                label="Total Poin"
                :value="totalPoinSemua"
            />
            <StatCard
                icon="user"
                tone="slate"
                label="Total Santri"
                :value="daftar.length"
            />
        </section>

        <!-- Empty state -->
        <EmptyState
            v-if="!loading && daftar.length === 0"
            icon="check-circle"
            tone="emerald"
            title="Tidak ada pelanggaran"
            :message="`Belum ada catatan pelanggaran untuk bulan ${bulan}.`"
        />

        <!-- Daftar Santri -->
        <section v-if="!loading && daftar.length > 0" class="space-y-4">
            <UiCard
                v-for="santri in daftar"
                :key="santri.santri_id"
                padding="none"
            >
                <!-- Header santri -->
                <div class="border-b border-slate-100 px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-bold tracking-tight text-slate-900">{{ santri.nama }}</p>
                            <p class="truncate text-xs text-slate-500">{{ santri.nis }} · {{ santri.kelas }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-bold tabular-nums text-amber-700">{{ santri.poin_bulan_ini }} poin</p>
                            <p class="text-xs text-slate-500">{{ santri.total_pelanggaran }} pelanggaran</p>
                        </div>
                    </div>
                </div>

                <!-- Daftar pelanggaran -->
                <div class="space-y-2 px-5 py-4">
                    <div
                        v-for="pelang in santri.pelanggaran"
                        :key="pelang.id"
                        :class="[
                            'rounded-2xl border-l-4 p-3',
                            kategoriColor[pelang.kategori] || 'bg-slate-50 border-slate-200',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold">{{ pelang.jenis_pelanggaran }}</p>
                                    <span :class="['rounded-full px-2 py-1 text-xs font-semibold', kategoriBadge[pelang.kategori]]">
                                        {{ pelang.kategori }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-600">{{ pelang.alasan }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span class="inline-flex items-center gap-1">
                                        <UiIcon name="calendar" class="h-3.5 w-3.5 shrink-0" />
                                        {{ pelang.waktu_kejadian }}
                                    </span>
                                    <span v-if="pelang.lokasi" class="inline-flex items-center gap-1">
                                        <UiIcon name="home" class="h-3.5 w-3.5 shrink-0" />
                                        {{ pelang.lokasi }}
                                    </span>
                                </div>
                                <div v-if="pelang.status === 'dicatat'" class="mt-2">
                                    <StatusBadge status="menunggu" :label="`Menunggu verifikasi oleh ${pelang.pencatat}`" size="sm" />
                                </div>
                                <div v-else class="mt-2">
                                    <StatusBadge status="terverifikasi" :label="`Diverifikasi oleh ${pelang.verifikator} · ${pelang.verified_at}`" size="sm" />
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-lg font-bold tabular-nums">{{ pelang.poin }}</p>
                                <p class="text-xs text-slate-500">poin</p>
                            </div>
                        </div>
                    </div>
                </div>
            </UiCard>
        </section>

        <!-- Action Button -->
        <section class="flex gap-3 pb-6">
            <UiButton
                href="/guru"
                variant="secondary"
                block
                iconLeft="arrow-left"
            >
                Kembali
            </UiButton>
            <UiButton
                href="/guru/disiplin/catat"
                variant="primary"
                block
                iconLeft="plus"
            >
                Catat Baru
            </UiButton>
        </section>
    </div>
</template>
