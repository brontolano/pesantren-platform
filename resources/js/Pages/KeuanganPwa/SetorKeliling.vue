<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, onMounted, reactive, ref } from 'vue'
import { enqueue, flush, gagalItems, pendingCount, uuid } from '@/keuangan-pwa/offline-sync.js'

// WP-32: Setor Keliling — petugas keliling kamar mencatat setoran tabungan
// sebagai DRAF (saldo tersentuh hanya setelah bendahara menyetujui).
// Offline-first: gagal kirim → antrian IndexedDB (pola offline-sync.js),
// idempoten via client_ref per entri.

const blok = ref([])
const kamarTerpilih = ref(null)
const santri = ref([])
const nominal = reactive({}) // santri_id → nominal
const catatan = reactive({})
const status = ref('')
const belumSinkron = ref(0)
const gagal = ref([])
const sesi = ref(null)
const memuat = ref(false)

const sesiDraf = computed(() => !sesi.value || sesi.value.status === 'draf')

async function muatBlok() {
    try {
        const res = await fetch('/api/keuangan/setor-keliling/kamar', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        if (res.ok) blok.value = (await res.json()).blok
    } catch (e) {
        status.value = 'Offline — daftar kamar butuh koneksi pertama kali.'
    }
}

async function muatSesi() {
    try {
        const res = await fetch('/api/keuangan/setor-keliling/sesi', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        if (res.ok) sesi.value = (await res.json()).sesi
    } catch (e) { /* offline: biarkan */ }
}

async function pilihKamar(k) {
    kamarTerpilih.value = k
    santri.value = []
    memuat.value = true
    try {
        const res = await fetch(`/api/keuangan/setor-keliling/kamar/${k.id}/santri`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        if (res.ok) santri.value = (await res.json()).santri
    } catch (e) {
        status.value = 'Offline — daftar santri butuh koneksi.'
    } finally {
        memuat.value = false
    }
}

async function refreshBadge() {
    belumSinkron.value = await pendingCount()
    gagal.value = await gagalItems()
}

async function setor(s) {
    const n = parseFloat(nominal[s.id])
    if (!n || n <= 0) {
        status.value = 'Isi nominal lebih dari 0 untuk ' + s.nama + '.'
        return
    }
    const payload = {
        client_ref: uuid(),
        santri_id: s.id,
        nominal: n,
        catatan: catatan[s.id] || null,
        lokasi: kamarTerpilih.value ? `Kamar ${kamarTerpilih.value.nomor_kamar}` : null,
    }
    try {
        const res = await fetch('/api/keuangan/setor-keliling/setoran', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        if (res.ok) {
            status.value = `Tercatat (draf): ${s.nama} — Rp ${n.toLocaleString('id-ID')}.`
            nominal[s.id] = ''
            catatan[s.id] = ''
            await muatSesi()
        } else {
            const err = await res.json().catch(() => ({}))
            status.value = 'Gagal: ' + (err.message || res.status)
        }
    } catch (e) {
        await enqueue('/api/keuangan/setor-keliling/setoran', payload)
        nominal[s.id] = ''
        catatan[s.id] = ''
        status.value = 'Tidak ada koneksi — setoran masuk antrian luring.'
    }
    await refreshBadge()
}

async function ajukanSesi() {
    if (belumSinkron.value > 0) {
        status.value = 'Masih ada setoran belum tersinkron — sambungkan internet dulu.'
        return
    }
    try {
        const res = await fetch('/api/keuangan/setor-keliling/ajukan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: '{}',
        })
        const data = await res.json().catch(() => ({}))
        status.value = res.ok
            ? `Sesi diajukan ke bendahara — total Rp ${Number(data.total).toLocaleString('id-ID')}.`
            : 'Gagal: ' + (data.message || res.status)
        await muatSesi()
    } catch (e) {
        status.value = 'Tidak ada koneksi — coba ajukan lagi saat online.'
    }
}

async function sinkronUlang() {
    await flush(document.querySelector('meta[name=csrf-token]')?.content)
    await refreshBadge()
    await muatSesi()
}

window.addEventListener('online', sinkronUlang)

onMounted(async () => {
    await Promise.all([muatBlok(), muatSesi(), refreshBadge()])
})
</script>

<template>
    <Head title="Setor Keliling" />
    <div class="min-h-screen bg-[#FDFBF7] p-4 pb-24">
        <header class="mb-3 flex items-center justify-between">
            <div>
                <a href="/admin/keuangan/pwa" class="text-xs text-gray-400">&larr; Beranda</a>
                <h1 class="text-lg font-semibold text-[#0A5C36]">Setor Keliling</h1>
            </div>
            <button v-if="belumSinkron > 0" @click="sinkronUlang"
                class="rounded-full bg-amber-100 text-amber-800 text-xs px-3 py-1 font-medium">
                {{ belumSinkron }} belum tersinkron
            </button>
        </header>

        <div v-if="sesi" class="mb-3 rounded-lg border bg-white p-3 text-sm flex items-center justify-between">
            <div>
                Sesi hari ini: <span class="font-medium">{{ sesi.jumlah_item }} setoran</span>
                — Rp {{ Number(sesi.total).toLocaleString('id-ID') }}
                <span class="ml-1 text-xs rounded px-1.5 py-0.5"
                    :class="sesi.status === 'draf' ? 'bg-gray-100 text-gray-600' : 'bg-blue-100 text-blue-700'">{{ sesi.status }}</span>
            </div>
            <button v-if="sesi.status === 'draf' && sesi.jumlah_item > 0" @click="ajukanSesi"
                class="rounded-lg bg-[#0A5C36] text-white text-xs px-3 py-2">Ajukan</button>
        </div>

        <div v-if="gagal.length" class="mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800">
            <p class="font-semibold mb-1">{{ gagal.length }} setoran luring DITOLAK server (tidak akan diulang otomatis):</p>
            <p v-for="g in gagal" :key="g.client_ref">Rp {{ Number(g.payload?.nominal || 0).toLocaleString('id-ID') }} — {{ g.error }}</p>
            <p class="mt-1">Catat ulang setoran tersebut secara manual.</p>
        </div>

        <p v-if="!sesiDraf" class="mb-3 text-sm text-blue-700">Sesi sudah diajukan — setoran baru akan masuk sesi berikutnya setelah bendahara memproses.</p>

        <!-- Pilih kamar -->
        <div v-if="!kamarTerpilih">
            <p v-if="!blok.length" class="text-sm text-gray-500">Belum ada data blok/kamar asrama.</p>
            <div v-for="b in blok" :key="b.id" class="mb-4">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">{{ b.nama_blok }}</h2>
                <div class="grid grid-cols-3 gap-2">
                    <button v-for="k in b.kamar" :key="k.id" @click="pilihKamar(k)"
                        class="rounded-xl bg-white border p-3 text-center shadow-sm">
                        <div class="font-medium">{{ k.nomor_kamar }}</div>
                        <div class="text-xs text-gray-500">{{ k.jumlah_santri }} santri</div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Daftar santri per kamar -->
        <div v-else>
            <button @click="kamarTerpilih = null; santri = []" class="mb-2 text-sm text-[#0A5C36]">&larr; Pilih kamar lain</button>
            <h2 class="text-sm font-semibold text-gray-700 mb-2">Kamar {{ kamarTerpilih.nomor_kamar }}</h2>
            <p v-if="memuat" class="text-sm text-gray-500">Memuat santri…</p>
            <p v-else-if="!santri.length" class="text-sm text-gray-500">Tidak ada penghuni aktif di kamar ini.</p>
            <div v-for="s in santri" :key="s.id" class="mb-3 rounded-xl bg-white border p-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium">{{ s.nama }}</div>
                        <div class="text-xs text-gray-500">NIS {{ s.nis }}</div>
                    </div>
                </div>
                <div class="mt-2 flex gap-2">
                    <input v-model="nominal[s.id]" type="number" min="1" inputmode="numeric" placeholder="Nominal"
                        class="w-full rounded-lg border p-2 text-sm" />
                    <button @click="setor(s)" class="rounded-lg bg-[#0A5C36] text-white px-4 text-sm font-medium">Setor</button>
                </div>
                <input v-model="catatan[s.id]" placeholder="Catatan (opsional)" class="mt-2 w-full rounded-lg border p-2 text-xs" />
            </div>
        </div>

        <p v-if="status" class="fixed bottom-3 left-4 right-4 rounded-lg bg-gray-900/90 text-white text-sm p-3">{{ status }}</p>
    </div>
</template>
