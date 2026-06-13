<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import PortalGuruLayout from '../../../Layouts/PortalGuruLayout.vue'
import {
    PageHeader,
    SectionCard,
    UiCard,
    StatCard,
    UiButton,
    UiIcon,
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

        <PageHeader
            kicker="Kepengasuhan · Disiplin"
            title="Poin Terbaru"
            :subtitle="`Status akumulasi poin santri (${masaBerlakuText()})`"
        />

        <!-- Ambang Eskalasi -->
        <SectionCard v-if="ambang.length > 0" title="Ambang Eskalasi" flush>
            <div class="divide-y divide-slate-100">
                <div
                    v-for="(item, idx) in ambang"
                    :key="idx"
                    class="flex items-center justify-between px-5 py-3"
                >
                    <p class="text-sm font-semibold text-slate-700">{{ item.label }}</p>
                    <p class="text-sm font-bold tabular-nums text-amber-700">≥ {{ item.poin }} poin</p>
                </div>
            </div>
        </SectionCard>

        <!-- Error -->
        <UiCard v-if="error" padding="md" class="border border-rose-200 bg-rose-50">
            <div class="flex items-center gap-3">
                <UiIcon name="x-circle" class="h-5 w-5 text-rose-600" />
                <p class="text-sm font-semibold text-rose-700">{{ error }}</p>
            </div>
        </UiCard>

        <!-- Loading -->
        <LoadingState v-if="loading" variant="list" :count="3" />

        <!-- Stats -->
        <section v-if="!loading && daftar.length > 0" class="grid grid-cols-2 gap-3">
            <StatCard
                tone="amber"
                icon="info"
                label="Santri Berpoin"
                :value="totalSantriCincang"
            />
            <StatCard
                tone="slate"
                icon="user"
                label="Total Santri"
                :value="daftar.length"
            />
        </section>

        <!-- Empty state -->
        <EmptyState
            v-if="!loading && daftar.length === 0"
            icon="check-circle"
            tone="emerald"
            title="Belum ada data poin"
            :message="`Belum ada catatan pelanggaran santri untuk periode ${masaBerlakuText()}.`"
        />

        <!-- Daftar Poin -->
        <section v-if="!loading && daftar.length > 0" class="space-y-3">
            <article
                v-for="santri in daftar"
                :key="santri.santri_id"
                :class="[
                    'rounded-3xl border-l-4 px-5 py-4 shadow-soft',
                    poinColor(santri.total_poin),
                ]"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-900">{{ santri.nama }}</p>
                        <p class="text-xs text-slate-600">{{ santri.nis }}</p>
                    </div>
                    <div class="text-right">
                        <p :class="['text-2xl font-bold tabular-nums', poinTextColor(santri.total_poin)]">
                            {{ santri.total_poin }}
                        </p>
                        <p class="text-xs text-slate-500">poin</p>
                    </div>
                </div>

                <!-- Status eskalasi -->
                <div v-if="statusPoin(santri.total_poin)" class="mt-3 flex items-center gap-2 rounded-xl bg-white/60 px-3 py-2">
                    <UiIcon name="info" :class="['h-4 w-4 shrink-0', poinTextColor(santri.total_poin)]" />
                    <p :class="['text-xs font-semibold', poinTextColor(santri.total_poin)]">
                        {{ statusPoin(santri.total_poin).label }} — Tindakan eskalasi diperlukan
                    </p>
                </div>

                <!-- Progress bar -->
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
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
            <UiButton href="/guru" variant="secondary" size="lg" block>
                Kembali
            </UiButton>
            <UiButton href="/guru/disiplin/daftar" variant="primary" size="lg" block>
                Lihat Daftar
            </UiButton>
        </section>
    </div>
</template>
