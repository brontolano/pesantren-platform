<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, reactive, computed } from 'vue'
import { AppShell, PageHeader, MoneyText, UiButton, BottomSheet, EmptyState, UiIcon, StatusBadge } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    produk: { type: Array, default: () => [] },
})

// Keranjang = state lokal reaktif: { [produkId]: qty }
const keranjang = reactive({})
const sheetOpen = ref(false)
const sukses = ref(false)
const toast = ref('')

const produkById = computed(() => Object.fromEntries(props.produk.map((p) => [p.id, p])))

const itemKeranjang = computed(() =>
    Object.entries(keranjang)
        .filter(([, qty]) => qty > 0)
        .map(([id, qty]) => {
            const p = produkById.value[id]
            return { ...p, qty, subtotal: p.harga * qty }
        }),
)

const totalQty = computed(() => itemKeranjang.value.reduce((s, i) => s + i.qty, 0))
const totalHarga = computed(() => itemKeranjang.value.reduce((s, i) => s + i.subtotal, 0))

function tambah(p) {
    keranjang[p.id] = (keranjang[p.id] || 0) + 1
}
function kurang(p) {
    if (!keranjang[p.id]) return
    keranjang[p.id] -= 1
    if (keranjang[p.id] <= 0) delete keranjang[p.id]
}

function bukaBayar() {
    if (totalQty.value === 0) return
    sukses.value = false
    sheetOpen.value = true
}

// Optimistic: TIDAK POST ke server / TIDAK ubah data — hanya tampilkan sukses,
// kosongkan keranjang, lalu tutup sheet (peragaan).
function konfirmasiBayar() {
    sukses.value = true
    toast.value = `Pembayaran ${formatRupiah(totalHarga.value)} berhasil dicatat.`
    setTimeout(() => {
        Object.keys(keranjang).forEach((k) => delete keranjang[k])
        sheetOpen.value = false
        setTimeout(() => (toast.value = ''), 2200)
    }, 750)
}

function formatRupiah(n) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(n)
}
</script>

<template>
    <Head title="POS Kantin — Kasir" />

    <AppShell :nav-items="navigation" brand-name="POS Kantin">
        <div class="space-y-4">
            <PageHeader kicker="Transaksi" title="Kasir" subtitle="Ketuk produk untuk menambah ke keranjang" />

            <div class="grid grid-cols-2 gap-3">
                <button
                    v-for="p in produk"
                    :key="p.id"
                    type="button"
                    class="relative flex flex-col rounded-3xl bg-white p-4 text-left ring-1 ring-slate-100 shadow-soft transition-all duration-200 active:scale-[.97]"
                    :class="keranjang[p.id] ? 'ring-2 ring-emerald-500' : ''"
                    @click="tambah(p)"
                >
                    <span
                        v-if="keranjang[p.id]"
                        class="absolute right-3 top-3 flex h-6 min-w-6 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-xs font-bold text-white"
                    >
                        {{ keranjang[p.id] }}
                    </span>
                    <p class="pr-7 text-[14px] font-bold leading-tight text-slate-800">{{ p.nama }}</p>
                    <p class="mt-0.5 text-[11px] font-medium text-slate-400">{{ p.kategori }}</p>
                    <div class="mt-2">
                        <MoneyText :amount="p.harga" size="sm" tone="emerald" />
                    </div>
                </button>
            </div>
        </div>

        <!-- Bilah keranjang mengambang di atas bottom-nav -->
        <div
            v-if="totalQty > 0"
            class="fixed inset-x-0 bottom-[96px] z-40 mx-auto w-full max-w-md px-5"
        >
            <UiButton variant="primary" block size="lg" icon-left="cash" @click="bukaBayar">
                Bayar {{ totalQty }} item · {{ formatRupiah(totalHarga) }}
            </UiButton>
        </div>

        <div
            v-if="toast"
            class="fixed inset-x-0 top-4 z-50 mx-auto w-full max-w-md px-5"
            role="status"
        >
            <div class="flex items-center gap-2.5 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-soft">
                <UiIcon name="check-circle" class="h-5 w-5 shrink-0" />
                <span>{{ toast }}</span>
            </div>
        </div>

        <BottomSheet v-model="sheetOpen" title="Konfirmasi Pembayaran">
            <div v-if="!sukses" class="space-y-3">
                <div class="divide-y divide-slate-100 rounded-2xl bg-slate-50/70 px-4">
                    <div
                        v-for="i in itemKeranjang"
                        :key="i.id"
                        class="flex items-center justify-between gap-3 py-3"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-slate-800">{{ i.nama }}</p>
                            <p class="text-xs text-slate-400">{{ formatRupiah(i.harga) }} × {{ i.qty }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-full bg-white text-slate-500 ring-1 ring-slate-200 active:scale-90"
                                aria-label="Kurangi"
                                @click="kurang(i)"
                            >
                                <UiIcon name="minus" class="h-3.5 w-3.5" />
                            </button>
                            <span class="w-5 text-center text-sm font-bold tabular-nums text-slate-800">{{ i.qty }}</span>
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-full bg-white text-emerald-600 ring-1 ring-slate-200 active:scale-90"
                                aria-label="Tambah"
                                @click="tambah(i)"
                            >
                                <UiIcon name="plus" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <EmptyState
                    v-if="itemKeranjang.length === 0"
                    icon="cash"
                    title="Keranjang kosong"
                    message="Tambahkan produk dari layar kasir."
                />

                <div v-else class="flex items-center justify-between rounded-2xl bg-emerald-50 px-4 py-3">
                    <span class="text-sm font-bold text-emerald-800">Total</span>
                    <MoneyText :amount="totalHarga" size="lg" tone="emerald" />
                </div>
            </div>

            <div v-else class="flex flex-col items-center gap-3 py-6 text-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <UiIcon name="check-circle" class="h-9 w-9" />
                </span>
                <p class="text-base font-bold text-slate-800">Pembayaran Berhasil</p>
                <StatusBadge status="lunas" label="Lunas" />
            </div>

            <template v-if="!sukses && itemKeranjang.length > 0" #footer>
                <UiButton variant="primary" block size="lg" icon-left="check" @click="konfirmasiBayar">
                    Konfirmasi · {{ formatRupiah(totalHarga) }}
                </UiButton>
            </template>
        </BottomSheet>
    </AppShell>
</template>
