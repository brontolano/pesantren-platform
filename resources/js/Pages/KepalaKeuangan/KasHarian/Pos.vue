<script setup>
import { onMounted, ref, computed } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah } from '@/keuangan-pwa/format.js'

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
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="font-['Marcellus',serif] text-lg text-[#0A5C36]">POS Kantin</h1>
            <div v-if="props.can?.operate_pos" class="flex rounded-lg border border-[#ece3cf] bg-white p-0.5">
                <button v-for="m in ['supervisi','kasir']" :key="m"
                        class="rounded-md px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider transition"
                        :class="mode === m ? 'bg-[#0A5C36] text-white' : 'text-[#3a4d42]'"
                        @click="switchMode(m)">
                    {{ m }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="space-y-3">
            <div class="h-24 animate-pulse rounded-2xl bg-[#e8e1d0]/60"></div>
            <div class="h-40 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
        </div>

        <!-- ═══ Mode Supervisi ═══ -->
        <template v-else-if="mode === 'supervisi'">
            <div v-if="ringkasan" class="space-y-3">
                <div class="rounded-2xl bg-gradient-to-br from-[#0A5C36] to-[#06342b] p-5 text-white"
                     style="border: 1px solid rgba(201,162,74,0.3);">
                    <div class="text-[11px] uppercase tracking-wider text-[#e7d3a1]">Omzet Hari Ini</div>
                    <div class="font-['Marcellus',serif] text-2xl">{{ formatRupiah(ringkasan.omzetHariIni) }}</div>
                    <div class="mt-1 text-xs opacity-75">{{ ringkasan.totalTransaksi ?? 0 }} transaksi</div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-[#ece3cf] bg-white p-3.5">
                        <div class="text-[10px] uppercase tracking-wider text-[#8a9690]">Sesi Aktif</div>
                        <div class="text-lg font-bold text-[#0A5C36]">{{ ringkasan.sesiAktif ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-[#ece3cf] bg-white p-3.5">
                        <div class="text-[10px] uppercase tracking-wider text-[#8a9690]">Stok Rendah</div>
                        <div class="text-lg font-bold" :class="(ringkasan.stokRendah ?? 0) > 0 ? 'text-amber-600' : 'text-[#0A5C36]'">
                            {{ ringkasan.stokRendah ?? 0 }}
                        </div>
                    </div>
                </div>

                <div v-if="ringkasan.topProduk?.length">
                    <h2 class="mb-2 text-sm font-semibold text-[#3a4d42]">Produk Terlaris</h2>
                    <div class="space-y-2">
                        <div v-for="(p, idx) in ringkasan.topProduk" :key="idx"
                             class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#C9A24A]/10 text-[10px] font-bold text-[#C9A24A]">{{ idx + 1 }}</span>
                                <span class="text-sm">{{ p.nama }}</span>
                            </div>
                            <span class="text-xs font-semibold text-[#8a9690]">{{ p.terjual }} pcs</span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-8 text-center text-sm text-[#8a9690]">
                Data POS belum tersedia
            </div>
        </template>

        <!-- ═══ Mode Kasir ═══ -->
        <template v-else>
            <!-- Search -->
            <input v-model="searchQuery" placeholder="Cari produk..."
                   class="mb-3 w-full rounded-lg border border-[#ece3cf] bg-white px-3 py-2.5 text-sm focus:border-[#0A5C36] focus:outline-none focus:ring-1 focus:ring-[#0A5C36]">

            <!-- Product grid -->
            <div class="mb-4 grid grid-cols-3 gap-2">
                <button v-for="p in produkFiltered" :key="p.id"
                        class="flex flex-col items-center rounded-xl border border-[#ece3cf] bg-white p-2.5 text-center transition hover:border-[#C9A24A]/50 hover:shadow-sm"
                        @click="tambahKeKeranjang(p)">
                    <div class="mb-1 text-xs font-medium leading-tight">{{ p.nama }}</div>
                    <div class="text-[10px] font-bold text-[#0A5C36]">{{ formatRupiah(p.harga) }}</div>
                    <div v-if="p.stok != null" class="mt-0.5 text-[9px] text-[#8a9690]">stok: {{ p.stok }}</div>
                </button>
            </div>

            <!-- Keranjang -->
            <div v-if="keranjang.length" class="rounded-xl border border-[#C9A24A]/40 bg-white p-4">
                <h2 class="mb-2 text-sm font-semibold text-[#3a4d42]">Keranjang</h2>
                <div class="space-y-2">
                    <div v-for="k in keranjang" :key="k.id"
                         class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <button @click="hapusDariKeranjang(k.id)" class="text-rose-400 hover:text-rose-600">×</button>
                            <span>{{ k.nama }} ×{{ k.qty }}</span>
                        </div>
                        <span class="font-semibold">{{ formatRupiah(k.harga * k.qty) }}</span>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-[#ece3cf] pt-3">
                    <span class="font-bold">Total</span>
                    <span class="text-lg font-bold text-[#0A5C36]">{{ formatRupiah(totalKeranjang) }}</span>
                </div>
                <button @click="checkout" :disabled="submitting"
                        class="mt-3 w-full rounded-lg bg-[#0A5C36] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#08472a] disabled:opacity-50">
                    {{ submitting ? 'Memproses...' : 'Bayar' }}
                </button>
            </div>
        </template>
    </div>
</template>
