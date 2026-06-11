<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import PortalGuruLayout from '../../../Layouts/PortalGuruLayout.vue'

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

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">
                Kepengasuhan · Disiplin
            </p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Daftar Pelanggaran</h2>
            <p class="text-sm text-slate-500">Pelanggaran santri bulan {{ bulan }}</p>
        </section>

        <!-- Filter -->
        <section class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Bulan</label>
                    <input
                        v-model="bulan"
                        type="month"
                        @change="handleFilterChange"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Status</label>
                    <select
                        v-model="statusFilter"
                        @change="handleFilterChange"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none"
                    >
                        <option
                            v-for="opt in statusOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Error -->
        <section v-if="error" class="rounded-[1.5rem] bg-red-50 border border-red-200 px-5 py-4">
            <p class="text-sm font-semibold text-red-700">{{ error }}</p>
        </section>

        <!-- Loading -->
        <section v-if="loading" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-sm text-slate-500">Memuat data...</p>
        </section>

        <!-- Stats -->
        <section v-if="!loading && daftar.length > 0" class="grid grid-cols-2 gap-3">
            <div class="rounded-[1.5rem] bg-gradient-to-br from-amber-100 to-amber-50 px-4 py-4 shadow">
                <p class="text-xs font-semibold uppercase text-amber-700">Total Poin</p>
                <p class="mt-1 text-2xl font-bold text-amber-900">{{ totalPoinSemua }}</p>
            </div>
            <div class="rounded-[1.5rem] bg-gradient-to-br from-slate-100 to-slate-50 px-4 py-4 shadow">
                <p class="text-xs font-semibold uppercase text-slate-700">Total Santri</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ daftar.length }}</p>
            </div>
        </section>

        <!-- Empty state -->
        <section v-if="!loading && daftar.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Tidak ada pelanggaran</p>
            <p class="mt-2 text-sm text-slate-500">
                Belum ada catatan pelanggaran untuk bulan {{ bulan }}.
            </p>
        </section>

        <!-- Daftar Santri -->
        <section v-if="!loading && daftar.length > 0" class="space-y-4">
            <article
                v-for="santri in daftar"
                :key="santri.santri_id"
                class="rounded-[1.5rem] bg-white shadow overflow-hidden"
            >
                <!-- Header santri -->
                <div class="px-5 py-4 border-b border-slate-100">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-900">{{ santri.nama }}</p>
                            <p class="text-xs text-slate-500">{{ santri.nis }} · {{ santri.kelas }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-amber-700">{{ santri.poin_bulan_ini }} poin</p>
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
                            'rounded-lg border-l-4 p-3',
                            kategoriColor[pelang.kategori] || 'bg-slate-50 border-slate-200',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-sm">{{ pelang.jenis_pelanggaran }}</p>
                                    <span :class="['text-xs px-2 py-1 rounded-full', kategoriBadge[pelang.kategori]]">
                                        {{ pelang.kategori }}
                                    </span>
                                </div>
                                <p class="text-xs mt-1 text-slate-600">{{ pelang.alasan }}</p>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                                    <span>📅 {{ pelang.waktu_kejadian }}</span>
                                    <span v-if="pelang.lokasi">📍 {{ pelang.lokasi }}</span>
                                </div>
                                <p v-if="pelang.status === 'dicatat'" class="text-xs text-amber-700 mt-2 font-semibold">
                                    ⏳ Menunggu verifikasi oleh {{ pelang.pencatat }}
                                </p>
                                <p v-else class="text-xs text-emerald-700 mt-2">
                                    ✓ Diverifikasi oleh {{ pelang.verifikator }} · {{ pelang.verified_at }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-lg">{{ pelang.poin }}</p>
                                <p class="text-xs text-slate-500">poin</p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <!-- Action Button -->
        <section class="flex gap-3 pb-6">
            <Link
                href="/guru"
                class="flex-1 rounded-[1rem] bg-slate-200 px-4 py-3 text-center font-semibold text-slate-700 hover:bg-slate-300 transition-colors"
            >
                Kembali
            </Link>
            <Link
                href="/guru/disiplin/catat"
                class="flex-1 rounded-[1rem] bg-amber-500 px-4 py-3 text-center font-semibold text-white hover:bg-amber-600 transition-colors"
            >
                + Catat Baru
            </Link>
        </section>
    </div>
</template>
