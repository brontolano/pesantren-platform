<script setup>
import { onMounted, ref, computed } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah } from '@/keuangan-pwa/format.js'
import {
    PageHeader,
    SectionCard,
    UiCard,
    ListRow,
    StatCard,
    UiButton,
    SegmentedControl,
    UiField,
    UiInput,
    MoneyText,
    EmptyState,
    LoadingState,
} from '@/Shared'

defineOptions({ layout: KepalaKeuanganLayout })
const props = defineProps({ can: Object })

const mode = ref('supervisi')
const loading = ref(true)

// Supervisi data
const ringkasan = ref(null)

// Kasir data
const produk = ref([])
const keranjang = ref([])
const searchQuery = ref('')
const submitting = ref(false)

const totalKeranjang = computed(() =>
    keranjang.value.reduce((sum, item) => sum + item.harga * item.qty, 0)
)

const produkFiltered = computed(() => {
    if (!searchQuery.value) return produk.value
    const q = searchQuery.value.toLowerCase()
    return produk.value.filter(p => p.nama.toLowerCase().includes(q))
})

async function fetchData() {
    loading.value = true
    try {
        if (mode.value === 'supervisi') {
            const res = await api.get('/pos/ringkasan')
            ringkasan.value = res
        } else {
            const res = await api.get('/pos/produk')
            produk.value = res.data ?? []
        }
    } catch { /* */ }
    loading.value = false
}

function tambahKeKeranjang(p) {
    const existing = keranjang.value.find(k => k.id === p.id)
    if (existing) {
        existing.qty++
    } else {
        keranjang.value.push({ ...p, qty: 1 })
    }
}

function hapusDariKeranjang(id) {
    keranjang.value = keranjang.value.filter(k => k.id !== id)
}

async function checkout() {
    if (!keranjang.value.length || submitting.value) return
    submitting.value = true
    try {
        await api.post('/pos/checkout', {
            items: keranjang.value.map(k => ({ produk_id: k.id, qty: k.qty })),
        })
        keranjang.value = []
        await fetchData()
    } catch { /* */ }
    submitting.value = false
}

function switchMode(m) {
    mode.value = m
    fetchData()
}

onMounted(fetchData)
</script>

<template>
    <div class="space-y-5">
        <PageHeader kicker="Kas Harian" title="POS Kantin">
            <template v-if="props.can?.operate_pos" #right>
                <div class="w-44">
                    <SegmentedControl
                        :model-value="mode"
                        :options="[
                            { value: 'supervisi', label: 'Supervisi', icon: 'chart' },
                            { value: 'kasir', label: 'Kasir', icon: 'cash' },
                        ]"
                        @update:model-value="switchMode"
                    />
                </div>
            </template>
        </PageHeader>

        <LoadingState v-if="loading" variant="card" :count="2" />

        <!-- ═══ Mode Supervisi ═══ -->
        <template v-else-if="mode === 'supervisi'">
            <div v-if="ringkasan" class="space-y-5">
                <UiCard variant="hero-dark" padding="md">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">Omzet Hari Ini</p>
                    <MoneyText :amount="ringkasan.omzetHariIni" size="xl" tone="inherit" class="mt-1 block text-white" />
                    <p class="mt-1 text-xs text-white/75 tabular-nums">{{ ringkasan.totalTransaksi ?? 0 }} transaksi</p>
                </UiCard>

                <div class="grid grid-cols-2 gap-3">
                    <StatCard icon="clock" tone="emerald" label="Sesi Aktif">
                        <template #value>
                            <span class="text-2xl font-extrabold tabular-nums tracking-tight text-violet-700">{{ ringkasan.sesiAktif ?? 0 }}</span>
                        </template>
                    </StatCard>
                    <StatCard
                        icon="info"
                        :tone="(ringkasan.stokRendah ?? 0) > 0 ? 'amber' : 'emerald'"
                        label="Stok Rendah"
                    >
                        <template #value>
                            <span
                                class="text-2xl font-extrabold tabular-nums tracking-tight"
                                :class="(ringkasan.stokRendah ?? 0) > 0 ? 'text-amber-600' : 'text-emerald-700'"
                            >{{ ringkasan.stokRendah ?? 0 }}</span>
                        </template>
                    </StatCard>
                </div>

                <SectionCard v-if="ringkasan.topProduk?.length" title="Produk Terlaris" flush>
                    <div class="divide-y divide-slate-100">
                        <ListRow v-for="(p, idx) in ringkasan.topProduk" :key="idx" :title="p.nama">
                            <template #leading>
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-50 text-xs font-bold tabular-nums text-amber-600 ring-1 ring-inset ring-amber-100">{{ idx + 1 }}</span>
                            </template>
                            <template #trailing>
                                <span class="text-sm font-semibold tabular-nums text-slate-500">{{ p.terjual }} pcs</span>
                            </template>
                        </ListRow>
                    </div>
                </SectionCard>
            </div>
            <EmptyState
                v-else
                icon="cash"
                title="Data POS belum tersedia"
                message="Belum ada transaksi atau sesi kasir untuk ditampilkan."
            />
        </template>

        <!-- ═══ Mode Kasir ═══ -->
        <template v-else>
            <!-- Search -->
            <UiField label="Cari Produk">
                <UiInput v-model="searchQuery" placeholder="Cari produk..." />
            </UiField>

            <!-- Product grid -->
            <div class="grid grid-cols-3 gap-2">
                <button v-for="p in produkFiltered" :key="p.id"
                        type="button"
                        class="flex flex-col items-center rounded-2xl bg-white p-2.5 text-center shadow-soft ring-1 ring-slate-100 transition-all duration-200 hover:ring-violet-200 active:scale-[.97]"
                        @click="tambahKeKeranjang(p)">
                    <div class="mb-1 text-xs font-semibold leading-tight tracking-tight text-slate-900">{{ p.nama }}</div>
                    <MoneyText :amount="p.harga" size="sm" tone="emerald" />
                    <div v-if="p.stok != null" class="mt-0.5 text-[10px] tabular-nums text-slate-400">stok: {{ p.stok }}</div>
                </button>
            </div>

            <!-- Keranjang -->
            <SectionCard v-if="keranjang.length" title="Keranjang" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="k in keranjang" :key="k.id">
                        <template #leading>
                            <button type="button" @click="hapusDariKeranjang(k.id)" class="flex h-7 w-7 items-center justify-center rounded-full text-rose-400 transition hover:bg-rose-50 hover:text-rose-600">
                                <span class="text-lg leading-none">×</span>
                            </button>
                        </template>
                        <template #title>{{ k.nama }} ×{{ k.qty }}</template>
                        <template #trailing>
                            <MoneyText :amount="k.harga * k.qty" size="sm" />
                        </template>
                    </ListRow>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                    <span class="font-bold tracking-tight text-slate-900">Total</span>
                    <MoneyText :amount="totalKeranjang" size="lg" tone="emerald" />
                </div>
                <div class="px-4 pb-4">
                    <UiButton variant="primary" block :loading="submitting" :disabled="submitting" iconLeft="cash" @click="checkout">
                        {{ submitting ? 'Memproses...' : 'Bayar' }}
                    </UiButton>
                </div>
            </SectionCard>
        </template>
    </div>
</template>
