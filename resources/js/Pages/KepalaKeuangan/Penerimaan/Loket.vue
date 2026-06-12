<script setup>
import { onMounted, ref } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah, formatWaktu } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })
const props = defineProps({ can: Object })

const loading = ref(true)
const sesiAktif = ref(null)
const riwayat = ref([])
const mode = ref('supervisi')

const formTerima = ref({ nama: '', keterangan: '', nominal: '' })
const submitting = ref(false)

async function fetchData() {
    loading.value = true
    try {
        const res = await api.get('/loket')
        sesiAktif.value = res.sesi ?? null
        riwayat.value = res.transaksi ?? []
    } catch { /* */ }
    loading.value = false
}

async function bukaSesi() {
    submitting.value = true
    try {
        await api.post('/loket/buka-sesi')
        await fetchData()
    } catch { /* */ }
    submitting.value = false
}

async function tutupSesi() {
    if (!confirm('Tutup sesi kasir? Sesi tidak bisa dibuka kembali.')) return
    submitting.value = true
    try {
        await api.post('/loket/tutup-sesi')
        await fetchData()
    } catch { /* */ }
    submitting.value = false
}

async function terimaTunai() {
    if (!formTerima.value.nominal || submitting.value) return
    submitting.value = true
    try {
        await api.post('/loket/terima-tunai', {
            nama: formTerima.value.nama,
            keterangan: formTerima.value.keterangan,
            nominal: parseFloat(formTerima.value.nominal),
        })
        formTerima.value = { nama: '', keterangan: '', nominal: '' }
        await fetchData()
    } catch { /* */ }
    submitting.value = false
}

onMounted(fetchData)
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="font-['Marcellus',serif] text-lg text-[#0A5C36]">Loket Kas</h1>
            <button v-if="props.can?.manage_keuangan"
                    class="rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wider transition"
                    :class="mode === 'kasir' ? 'border-[#0A5C36] bg-[#0A5C36] text-white' : 'border-[#ece3cf] bg-white text-[#3a4d42]'"
                    @click="mode = mode === 'supervisi' ? 'kasir' : 'supervisi'">
                {{ mode === 'kasir' ? 'Mode Kasir' : 'Mode Supervisi' }}
            </button>
        </div>

        <!-- Skeleton -->
        <div v-if="loading" class="space-y-3">
            <div class="h-20 animate-pulse rounded-2xl bg-[#e8e1d0]/60"></div>
            <div class="h-40 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
        </div>

        <template v-else>
            <!-- Sesi info -->
            <div class="mb-4 rounded-2xl p-4"
                 :class="sesiAktif ? 'bg-emerald-50 border border-emerald-200' : 'bg-white border border-[#ece3cf]'">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider"
                             :class="sesiAktif ? 'text-emerald-700' : 'text-[#8a9690]'">
                            {{ sesiAktif ? 'Sesi Aktif' : 'Tidak Ada Sesi' }}
                        </div>
                        <div v-if="sesiAktif" class="mt-1 text-sm">
                            Dibuka {{ formatWaktu(sesiAktif.dibuka_pada) }} · Petugas: {{ sesiAktif.petugas }}
                        </div>
                        <div v-if="sesiAktif" class="mt-0.5 text-lg font-bold text-[#0A5C36]">
                            {{ formatRupiah(sesiAktif.total ?? 0) }}
                        </div>
                    </div>
                    <button v-if="mode === 'kasir'"
                            :disabled="submitting"
                            class="rounded-lg px-3 py-2 text-xs font-bold transition disabled:opacity-50"
                            :class="sesiAktif ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-[#0A5C36] text-white hover:bg-[#08472a]'"
                            @click="sesiAktif ? tutupSesi() : bukaSesi()">
                        {{ sesiAktif ? 'Tutup Sesi' : 'Buka Sesi' }}
                    </button>
                </div>
            </div>

            <!-- Form terima tunai (kasir mode + sesi aktif) -->
            <div v-if="mode === 'kasir' && sesiAktif" class="mb-5 rounded-xl border border-[#ece3cf] bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold text-[#3a4d42]">Terima Pembayaran Tunai</h2>
                <div class="space-y-2.5">
                    <input v-model="formTerima.nama" placeholder="Nama santri / pembayar"
                           class="w-full rounded-lg border border-[#ece3cf] bg-[#FDFBF7] px-3 py-2.5 text-sm focus:border-[#0A5C36] focus:outline-none focus:ring-1 focus:ring-[#0A5C36]">
                    <input v-model="formTerima.keterangan" placeholder="Keterangan (SPP, Infaq, dll)"
                           class="w-full rounded-lg border border-[#ece3cf] bg-[#FDFBF7] px-3 py-2.5 text-sm focus:border-[#0A5C36] focus:outline-none focus:ring-1 focus:ring-[#0A5C36]">
                    <input v-model="formTerima.nominal" type="number" placeholder="Nominal (Rp)"
                           class="w-full rounded-lg border border-[#ece3cf] bg-[#FDFBF7] px-3 py-2.5 text-sm focus:border-[#0A5C36] focus:outline-none focus:ring-1 focus:ring-[#0A5C36]">
                    <button @click="terimaTunai" :disabled="submitting || !formTerima.nominal"
                            class="w-full rounded-lg bg-[#0A5C36] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#08472a] disabled:opacity-50">
                        Terima & Cetak Kuitansi
                    </button>
                </div>
            </div>

            <!-- Riwayat transaksi sesi -->
            <h2 class="mb-2 text-sm font-semibold text-[#3a4d42]">Riwayat Transaksi</h2>
            <div v-if="riwayat.length" class="space-y-2">
                <div v-for="t in riwayat" :key="t.id"
                     class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                    <div>
                        <div class="text-sm font-medium">{{ t.nama ?? t.keterangan }}</div>
                        <div class="text-xs text-[#8a9690]">{{ formatWaktu(t.waktu) }} · {{ t.keterangan }}</div>
                    </div>
                    <span class="text-sm font-semibold text-emerald-600">+{{ formatRupiah(t.nominal) }}</span>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-6 text-center text-sm text-[#8a9690]">
                Belum ada transaksi di sesi ini
            </div>
        </template>
    </div>
</template>
