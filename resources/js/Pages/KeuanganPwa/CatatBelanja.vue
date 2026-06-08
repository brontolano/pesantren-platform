<script setup>
import { Head } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import { enqueue, flush, uuid } from '@/keuangan-pwa/offline-sync.js'
import { ambilLokasi } from '@/keuangan-pwa/camera-gps.js'

const props = defineProps({
    sumberDana: { type: Array, default: () => [] },
    divisi: { type: Array, default: () => [] },
    akunBeban: { type: Array, default: () => [] },
    akunKas: { type: Array, default: () => [] },
})

const form = reactive({
    uraian: '', jenis: 'barang', dpp: '', sumber_dana_id: '',
    divisi_id: '', akun_beban_id: '', akun_kas_id: '', vendor_npwp: false,
})
const status = ref('')
const lokasi = ref(null)

async function tandaiLokasi() {
    try { lokasi.value = await ambilLokasi() } catch (e) { status.value = e.message }
}

async function simpan() {
    status.value = 'Menyimpan...'
    const payload = { ...form, client_ref: uuid() }
    try {
        const res = await fetch('/api/keuangan/belanja', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        if (res.ok) {
            const data = await res.json()
            status.value = `Tersimpan. Pajak: PPN ${data.ppn}, PPh ${data.pph}.`
        } else if (!navigator.onLine) {
            await enqueue('/api/keuangan/belanja', payload)
            status.value = 'Offline — disimpan di antrian, akan tersinkron.'
        } else {
            const err = await res.json().catch(() => ({}))
            status.value = 'Gagal: ' + (err.message || res.status)
        }
    } catch (e) {
        await enqueue('/api/keuangan/belanja', payload)
        status.value = 'Tidak ada koneksi — masuk antrian luring.'
    }
}

window.addEventListener('online', () => flush())
</script>

<template>
    <Head title="Catat Belanja" />
    <div class="min-h-screen bg-[#FDFBF7] p-4">
        <h1 class="text-lg font-semibold text-[#0A5C36] mb-3">Catat Belanja</h1>
        <form @submit.prevent="simpan" class="space-y-3">
            <input v-model="form.uraian" placeholder="Uraian belanja" class="w-full rounded-lg border p-2" required />
            <select v-model="form.jenis" class="w-full rounded-lg border p-2">
                <option value="barang">Barang</option>
                <option value="jasa">Jasa</option>
                <option value="sewa">Sewa</option>
            </select>
            <input v-model="form.dpp" type="number" placeholder="Nominal (DPP)" class="w-full rounded-lg border p-2" required />
            <select v-model="form.sumber_dana_id" class="w-full rounded-lg border p-2" required>
                <option value="">— Sumber Dana —</option>
                <option v-for="s in sumberDana" :key="s.id" :value="s.id">{{ s.kode_dana }} — {{ s.nama_sumber }}</option>
            </select>
            <select v-model="form.akun_beban_id" class="w-full rounded-lg border p-2">
                <option value="">— Akun Beban —</option>
                <option v-for="a in akunBeban" :key="a.id" :value="a.id">{{ a.kode }} — {{ a.nama }}</option>
            </select>
            <select v-model="form.akun_kas_id" class="w-full rounded-lg border p-2">
                <option value="">— Akun Kas —</option>
                <option v-for="a in akunKas" :key="a.id" :value="a.id">{{ a.kode }} — {{ a.nama }}</option>
            </select>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.vendor_npwp" /> Vendor ber-NPWP</label>

            <button type="button" @click="tandaiLokasi" class="w-full rounded-lg border p-2 text-sm">
                📍 {{ lokasi ? `GPS: ${lokasi.lat.toFixed(4)}, ${lokasi.lng.toFixed(4)}` : 'Tandai Lokasi (GPS)' }}
            </button>
            <button type="submit" class="w-full rounded-lg bg-[#0A5C36] text-white p-3 font-medium">Simpan</button>
        </form>
        <p v-if="status" class="mt-3 text-sm text-gray-600">{{ status }}</p>
    </div>
</template>
