<script setup>
import { onMounted, ref, watch } from 'vue'
import KepalaKeuanganLayout from '@/Layouts/KepalaKeuanganLayout.vue'
import { api } from '@/keuangan-pwa/api.js'
import { formatRupiah } from '@/keuangan-pwa/format.js'

defineOptions({ layout: KepalaKeuanganLayout })

const tabs = [
    { key: 'bos-bop', label: 'BOS/BOP' },
    { key: 'kebajikan', label: 'Kebajikan' },
    { key: 'terikat', label: 'Terikat' },
    { key: 'divisi', label: 'Per Divisi' },
]

const aktif = ref(new URLSearchParams(window.location.search).get('tab') ?? 'bos-bop')
const loading = ref(true)
const data = ref(null)
const error = ref(null)

async function muat() {
    loading.value = true
    error.value = null
    try {
        data.value = await api.get(`/laporan/dana?jenis=${aktif.value}`)
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

watch(aktif, muat)
onMounted(muat)

const labelTerikat = { wakaf: 'Wakaf (PSAK 112)', zis: 'Zakat-Infak-Sedekah (PSAK 109)', lainnya_terikat: 'Dana Terikat Lainnya' }
</script>

<template>
    <h2 class="mb-3 font-['Marcellus',serif] text-lg text-[#0A5C36]">Dana & Realisasi</h2>

    <div class="mb-4 flex flex-wrap gap-2">
        <button v-for="t in tabs" :key="t.key" @click="aktif = t.key"
                class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition"
                :class="aktif === t.key
                    ? 'border-[#0A5C36] bg-[#0A5C36] text-white'
                    : 'border-[#ece3cf] bg-white text-[#3a4d42]'">
            {{ t.label }}
        </button>
    </div>

    <div v-if="loading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-xl bg-[#e8e1d0]/60"></div>
    </div>

    <div v-else-if="error" class="rounded-2xl bg-red-50 px-4 py-5 text-sm text-red-700">
        Gagal memuat: {{ error }}
    </div>

    <template v-else-if="data">
        <!-- BOS/BOP -->
        <template v-if="aktif === 'bos-bop'">
            <div v-if="data.length" class="space-y-2.5">
                <div v-for="d in data" :key="d.kode" class="rounded-xl border border-[#ece3cf] bg-white p-4">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold">{{ d.nama }}</div>
                            <div class="text-[11px] text-[#8a9690]">{{ d.kode }}<span v-if="d.rezim"> · {{ d.rezim.replaceAll('_', ' ').toUpperCase() }}</span></div>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                              :class="d.sisa >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                            sisa {{ formatRupiah(d.sisa) }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="text-xs text-[#8a9690]">Penerimaan</span><div class="font-semibold text-emerald-600">{{ formatRupiah(d.penerimaan) }}</div></div>
                        <div><span class="text-xs text-[#8a9690]">Penggunaan</span><div class="font-semibold text-rose-600">{{ formatRupiah(d.penggunaan) }}</div></div>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-6 text-center text-sm text-[#8a9690]">
                Belum ada sumber dana kategori negara
            </div>
        </template>

        <!-- Dana Kebajikan -->
        <template v-else-if="aktif === 'kebajikan'">
            <div class="mb-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
                Dana kebajikan (penerimaan syubhat) disajikan terpisah dari surplus dan hanya boleh disalurkan untuk fasilitas umum.
            </div>
            <div class="mb-3 grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-[#ece3cf] bg-white p-3.5">
                    <div class="text-[10px] uppercase tracking-wider text-[#8a9690]">Penerimaan</div>
                    <div class="font-semibold text-[#0A5C36]">{{ formatRupiah(data.penerimaan) }}</div>
                </div>
                <div class="rounded-xl border border-[#ece3cf] bg-white p-3.5">
                    <div class="text-[10px] uppercase tracking-wider text-[#8a9690]">Saldo</div>
                    <div class="font-semibold text-[#0A5C36]">{{ formatRupiah(data.saldo) }}</div>
                </div>
            </div>
            <h3 class="mb-2 text-sm font-semibold text-[#3a4d42]">Penyaluran</h3>
            <div v-if="data.penyaluran?.length" class="space-y-2">
                <div v-for="p in data.penyaluran" :key="p.kode"
                     class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                    <div>
                        <div class="text-sm font-medium">{{ p.nama }}</div>
                        <div class="text-xs text-[#8a9690]">{{ p.kode }}<span v-if="p.peruntukan"> · {{ p.peruntukan }}</span></div>
                    </div>
                    <span class="font-semibold text-rose-600">{{ formatRupiah(p.jumlah) }}</span>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-6 text-center text-sm text-[#8a9690]">
                Belum ada penyaluran
            </div>
        </template>

        <!-- Dana Terikat -->
        <template v-else-if="aktif === 'terikat'">
            <template v-for="(rows, kunci) in data" :key="kunci">
                <h3 class="mb-2 mt-4 first:mt-0 text-sm font-semibold text-[#3a4d42]">{{ labelTerikat[kunci] ?? kunci }}</h3>
                <div v-if="rows.length" class="space-y-2">
                    <div v-for="d in rows" :key="d.kode" class="rounded-xl border border-[#ece3cf] bg-white p-4">
                        <div class="mb-1.5 flex justify-between">
                            <span class="text-sm font-semibold">{{ d.nama }}</span>
                            <span class="text-sm font-bold text-[#0A5C36]">{{ formatRupiah(d.saldo) }}</span>
                        </div>
                        <div class="flex justify-between text-xs text-[#8a9690]">
                            <span>Masuk {{ formatRupiah(d.penerimaan) }}</span>
                            <span>Pakai {{ formatRupiah(d.penggunaan) }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-4 text-center text-xs text-[#8a9690]">
                    Tidak ada
                </div>
            </template>
        </template>

        <!-- Realisasi per Divisi -->
        <template v-else>
            <div v-if="data.length" class="space-y-2">
                <div v-for="d in data" :key="d.divisi"
                     class="flex items-center justify-between rounded-xl border border-[#ece3cf] bg-white px-4 py-3">
                    <span class="text-sm font-medium">{{ d.divisi }}</span>
                    <span class="font-semibold text-[#0A5C36]">{{ formatRupiah(d.realisasi) }}</span>
                </div>
            </div>
            <div v-else class="rounded-xl border border-dashed border-[#ece3cf] bg-white/60 p-6 text-center text-sm text-[#8a9690]">
                Belum ada realisasi beban
            </div>
        </template>
    </template>
</template>
