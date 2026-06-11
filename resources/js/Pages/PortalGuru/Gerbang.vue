<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({ layout: PortalGuruLayout })

const props = defineProps({
    nis: { type: String, default: '' },
    santriCard: { type: Object, default: null },
    jengukHariIni: { type: Array, default: () => [] },
    walkInBoleh: { type: Boolean, default: true },
    logTerbaru: { type: Array, default: () => [] },
})

// Pola keyboard-wedge: scanner QR mengetik NIS + Enter ke input autofocus.
const scanInput = ref(null)
const nisInput = ref('')

onMounted(() => scanInput.value?.focus())

function scan() {
    if (!nisInput.value.trim()) return
    router.get('/guru/gerbang', { nis: nisInput.value.trim() }, { preserveState: false })
}

function catat(arah) {
    router.post('/guru/gerbang/catat', { santri_id: props.santriCard.id, arah }, {
        preserveScroll: true,
        onSuccess: () => { nisInput.value = ''; scanInput.value?.focus() },
    })
}

function tandaiHadir(id) {
    router.post(`/guru/gerbang/jenguk/${id}/hadir`, {}, { preserveScroll: true })
}

const walkInForm = useForm({ santri_id: null, nama_pengunjung: '', hubungan: 'wali', keperluan: '' })

function simpanWalkIn() {
    walkInForm.santri_id = props.santriCard?.id
    walkInForm.post('/guru/gerbang/jenguk/walk-in', {
        preserveScroll: true,
        onSuccess: () => walkInForm.reset('nama_pengunjung', 'keperluan'),
    })
}

const warnaStatus = {
    hijau: 'border-emerald-400 bg-emerald-50',
    kuning: 'border-amber-400 bg-amber-50',
    merah: 'border-rose-400 bg-rose-50',
}

// Cache offline ringan: fetch daftar izin aktif berkala (TANPA service worker baru).
const izinCache = ref([])
let timer = null
async function muatIzinAktif() {
    try {
        const res = await fetch('/guru/gerbang/izin-aktif', { headers: { Accept: 'application/json' } })
        if (res.ok) {
            const json = await res.json()
            izinCache.value = json.izin ?? []
            localStorage.setItem('gerbang_izin_aktif', JSON.stringify(json))
        }
    } catch {
        const cached = localStorage.getItem('gerbang_izin_aktif')
        if (cached) izinCache.value = JSON.parse(cached).izin ?? []
    }
}
onMounted(() => {
    muatIzinAktif()
    timer = setInterval(muatIzinAktif, 5 * 60 * 1000)
})
</script>

<template>
    <div class="space-y-5">
        <Head><title>Pos Gerbang</title></Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Mode Keamanan</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Pos Gerbang</h2>
        </section>

        <!-- Input scan (keyboard-wedge) -->
        <form class="flex gap-2" @submit.prevent="scan">
            <input
                ref="scanInput"
                v-model="nisInput"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                placeholder="Scan QR kartu santri / ketik NIS lalu Enter"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-lg shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
            >
            <button type="submit" class="rounded-2xl bg-emerald-600 px-5 py-3 font-semibold text-white shadow">Cari</button>
        </form>

        <!-- Kartu santri -->
        <section v-if="santriCard?.tidak_ditemukan" class="rounded-[1.5rem] border-2 border-rose-300 bg-rose-50 p-5 text-rose-700">
            NIS <strong>{{ santriCard.nis }}</strong> tidak ditemukan.
        </section>

        <section v-else-if="santriCard" :class="['rounded-[1.5rem] border-2 p-5 shadow', warnaStatus[santriCard.status]]">
            <div class="flex items-start gap-4">
                <img
                    :src="santriCard.foto || `https://ui-avatars.com/api/?background=059669&color=fff&size=256&name=${encodeURIComponent(santriCard.nama)}`"
                    :alt="santriCard.nama"
                    class="h-36 w-36 rounded-2xl object-cover shadow-md"
                >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-2xl font-bold text-slate-900">{{ santriCard.nama }}</p>
                    <p class="text-sm text-slate-600">NIS {{ santriCard.nis }} · Kelas {{ santriCard.kelas }}</p>

                    <p v-if="santriCard.status === 'hijau'" class="mt-2 inline-block rounded-full bg-emerald-600 px-3 py-1 text-sm font-semibold text-white">
                        ✓ Izin {{ santriCard.izin.jenis_izin }} aktif ({{ santriCard.izin.tanggal_mulai }} – {{ santriCard.izin.tanggal_selesai }})
                    </p>
                    <p v-else-if="santriCard.status === 'kuning'" class="mt-2 inline-block rounded-full bg-amber-500 px-3 py-1 text-sm font-semibold text-white">
                        ⚠ TERLAMBAT KEMBALI — izin habis {{ santriCard.izin.tanggal_selesai }}
                    </p>
                    <p v-else class="mt-2 inline-block rounded-full bg-rose-600 px-3 py-1 text-sm font-semibold text-white">
                        ✗ TANPA IZIN AKTIF
                    </p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <template v-if="santriCard.status === 'hijau'">
                    <button class="rounded-2xl bg-emerald-600 px-4 py-2.5 font-semibold text-white shadow" @click="catat('keluar')">Catat Keluar</button>
                    <button class="rounded-2xl bg-emerald-700 px-4 py-2.5 font-semibold text-white shadow" @click="catat('masuk')">Catat Masuk &amp; Tandai Kembali</button>
                </template>
                <template v-else-if="santriCard.status === 'kuning'">
                    <button class="rounded-2xl bg-amber-600 px-4 py-2.5 font-semibold text-white shadow" @click="catat('masuk')">Catat Masuk &amp; Tandai Kembali</button>
                </template>
                <template v-else>
                    <button class="rounded-2xl bg-rose-600 px-4 py-2.5 font-semibold text-white shadow" @click="catat('keluar')">Catat Keluar (tanpa izin)</button>
                    <button class="rounded-2xl bg-slate-700 px-4 py-2.5 font-semibold text-white shadow" @click="catat('masuk')">Catat Masuk</button>
                </template>
            </div>

            <!-- WP-30: registrasi tamu penjengukan -->
            <div class="mt-5 border-t border-slate-200 pt-4">
                <h3 class="text-sm font-semibold text-slate-700">Penjengukan Hari Ini</h3>

                <div v-for="p in jengukHariIni" :key="p.id" class="mt-2 flex items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-sm">
                    <div>
                        <p class="font-semibold text-slate-900">{{ p.pengaju ?? 'Wali' }}</p>
                        <p v-if="p.catatan" class="text-xs text-slate-500">{{ p.catatan }}</p>
                    </div>
                    <button class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white" @click="tandaiHadir(p.id)">Tandai Hadir</button>
                </div>
                <p v-if="jengukHariIni.length === 0" class="mt-1 text-xs text-slate-500">Tidak ada pengajuan jenguk terkonfirmasi hari ini.</p>

                <form v-if="walkInBoleh" class="mt-3 space-y-2" @submit.prevent="simpanWalkIn">
                    <p class="text-xs font-semibold text-slate-600">Tamu tanpa pengajuan (walk-in):</p>
                    <div class="flex flex-wrap gap-2">
                        <input v-model="walkInForm.nama_pengunjung" required placeholder="Nama pengunjung" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <select v-model="walkInForm.hubungan" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="ayah">Ayah</option>
                            <option value="ibu">Ibu</option>
                            <option value="saudara">Saudara</option>
                            <option value="wali">Wali</option>
                        </select>
                        <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Catat Walk-in</button>
                    </div>
                    <p v-if="walkInForm.errors.nama_pengunjung" class="text-xs text-rose-600">{{ walkInForm.errors.nama_pengunjung }}</p>
                </form>
                <p v-else class="mt-2 text-xs text-rose-600">Walk-in tidak diizinkan — tamu wajib mengajukan slot via Portal Wali.</p>
            </div>
        </section>

        <!-- Log hari ini -->
        <section class="space-y-2">
            <h3 class="text-sm font-semibold text-slate-700">Log Gerbang Hari Ini</h3>
            <div v-for="log in logTerbaru" :key="log.id" class="flex items-center justify-between rounded-2xl bg-white px-4 py-2.5 shadow-sm">
                <p class="font-medium text-slate-900">{{ log.nama }}</p>
                <p class="text-sm">
                    <span :class="log.arah === 'keluar' ? 'text-amber-700' : 'text-emerald-700'">{{ log.arah }}</span>
                    <span class="text-slate-400"> · {{ log.waktu }}</span>
                    <span v-if="log.is_tanpa_izin" class="ml-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">tanpa izin</span>
                </p>
            </div>
            <p v-if="logTerbaru.length === 0" class="text-xs text-slate-500">Belum ada catatan hari ini.</p>
        </section>
    </div>
</template>
