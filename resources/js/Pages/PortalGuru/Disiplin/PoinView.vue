<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import PortalGuruLayout from '../../../Layouts/PortalGuruLayout.vue'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    hari: { type: String, required: true },
    tanggal: { type: String, required: true },
    istilah: { type: Object, default: () => ({}) },
})

const daftar = ref([])
const loading = ref(false)
const error = ref('')
const masaBerlaku = ref('semester')
const ambang = ref([])

const fetchPoin = async () => {
    loading.value = true
    error.value = ''
    try {
        const res = await fetch('/api/pwa/guru/disiplin/poinTerbaru', {
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('sanctum_token')}`,
                'Accept': 'application/json',
            },
        })
        const json = await res.json()
        if (json.status === 'success') {
            daftar.value = json.data || []
            masaBerlaku.value = json.meta?.masa_berlaku || 'semester'
            ambang.value = json.meta?.ambang || []
        } else {
            error.value = json.message || 'Gagal memuat data poin'
        }
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

const statusPoin = (poin) => {
    const level = ambang.value.findIndex(a => poin >= a.poin)
    return level >= 0 ? ambang.value[level] : null
}

const poinColor = (poin) => {
    if (!statusPoin(poin)) return 'bg-emerald-50 border-emerald-200'
    const status = statusPoin(poin)
    if (poin >= 100) return 'bg-red-50 border-red-200'
    if (poin >= 50) return 'bg-amber-50 border-amber-200'
    return 'bg-slate-50 border-slate-200'
}

const poinTextColor = (poin) => {
    if (!statusPoin(poin)) return 'text-emerald-700'
    const status = statusPoin(poin)
    if (poin >= 100) return 'text-red-700'
    if (poin >= 50) return 'text-amber-700'
    return 'text-slate-700'
}

const totalSantriCincang = computed(() => {
    return daftar.value.filter(s => s.total_poin > 0).length
})

const masaBerlakuText = () => {
    const map = {
        'semester': 'Semester',
        'tahunan': 'Tahunan',
        'akumulasi': 'Akumulasi',
    }
    return map[masaBerlaku.value] || masaBerlaku.value
}

onMounted(() => {
    fetchPoin()
})
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Poin Terbaru</title>
        </Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">
                Kepengasuhan · Disiplin
            </p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Poin Terbaru</h2>
            <p class="text-sm text-slate-500">Status akumulasi poin santri ({{ masaBerlakuText() }})</p>
        </section>

        <!-- Ambang Eskalasi -->
        <section v-if="ambang.length > 0" class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
            <p class="text-sm font-semibold text-slate-700 mb-3">Ambang Eskalasi</p>
            <div class="space-y-2">
                <div
                    v-for="(item, idx) in ambang"
                    :key="idx"
                    class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                >
                    <p class="text-sm font-semibold text-slate-700">{{ item.label }}</p>
                    <p class="text-sm font-bold text-amber-700">≥ {{ item.poin }} poin</p>
                </div>
            </div>
        </section>

        <!-- Error -->
        <section v-if="error" class="rounded-[1.5rem] bg-red-50 border border-red-200 px-5 py-4">
            <p class="text-sm font-semibold text-red-700">{{ error }}</p>
        </section>

        <!-- Loading -->
        <section v-if="loading" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-sm text-slate-500">Memuat data poin...</p>
        </section>

        <!-- Stats -->
        <section v-if="!loading && daftar.length > 0" class="grid grid-cols-2 gap-3">
            <div class="rounded-[1.5rem] bg-gradient-to-br from-amber-100 to-amber-50 px-4 py-4 shadow">
                <p class="text-xs font-semibold uppercase text-amber-700">Santri Cincang</p>
                <p class="mt-1 text-2xl font-bold text-amber-900">{{ totalSantriCincang }}</p>
            </div>
            <div class="rounded-[1.5rem] bg-gradient-to-br from-slate-100 to-slate-50 px-4 py-4 shadow">
                <p class="text-xs font-semibold uppercase text-slate-700">Total Santri</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ daftar.length }}</p>
            </div>
        </section>

        <!-- Empty state -->
        <section v-if="!loading && daftar.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada data poin</p>
            <p class="mt-2 text-sm text-slate-500">
                Belum ada catatan pelanggaran santri untuk periode {{ masaBerlakuText() }}.
            </p>
        </section>

        <!-- Daftar Poin -->
        <section v-if="!loading && daftar.length > 0" class="space-y-3">
            <article
                v-for="santri in daftar"
                :key="santri.santri_id"
                :class="[
                    'rounded-[1.5rem] border-l-4 shadow px-5 py-4',
                    poinColor(santri.total_poin),
                ]"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-900">{{ santri.nama }}</p>
                        <p class="text-xs text-slate-600">{{ santri.nis }}</p>
                    </div>
                    <div class="text-right">
                        <p :class="['text-2xl font-bold', poinTextColor(santri.total_poin)]">
                            {{ santri.total_poin }}
                        </p>
                        <p class="text-xs text-slate-500">poin</p>
                    </div>
                </div>

                <!-- Status eskalasi -->
                <div v-if="statusPoin(santri.total_poin)" class="mt-3 rounded-lg bg-white/50 px-3 py-2">
                    <p :class="['text-xs font-semibold', poinTextColor(santri.total_poin)]">
                        🚨 {{ statusPoin(santri.total_poin).label }} — Tindakan eskalasi diperlukan
                    </p>
                </div>

                <!-- Progress bar -->
                <div class="mt-3 h-2 rounded-full bg-slate-200 overflow-hidden">
                    <div
                        :style="{ width: Math.min((santri.total_poin / 100) * 100, 100) + '%' }"
                        :class="[
                            'h-full transition-all',
                            santri.total_poin >= 100 ? 'bg-red-500' :
                            santri.total_poin >= 50 ? 'bg-amber-500' :
                            'bg-emerald-500'
                        ]"
                    ></div>
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
                href="/guru/disiplin/daftar"
                class="flex-1 rounded-[1rem] bg-amber-500 px-4 py-3 text-center font-semibold text-white hover:bg-amber-600 transition-colors"
            >
                Lihat Daftar
            </Link>
        </section>
    </div>
</template>
