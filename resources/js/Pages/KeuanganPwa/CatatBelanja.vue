<script setup>
import { Head } from '@inertiajs/vue3'
import { reactive, ref, computed } from 'vue'
import { enqueue, flush, uuid } from '@/keuangan-pwa/offline-sync.js'
import { ambilLokasi } from '@/keuangan-pwa/camera-gps.js'
import { AppShell, PageHeader, UiField, UiInput, UiButton, SegmentedControl, UiIcon } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
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
const statusTone = ref('info')
const lokasi = ref(null)
const saving = ref(false)

const jenisOptions = [
    { value: 'barang', label: 'Barang' },
    { value: 'jasa', label: 'Jasa' },
    { value: 'sewa', label: 'Sewa' },
]

const selectClass = 'w-full rounded-2xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]'

const statusClass = computed(() => ({
    success: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
    error: 'bg-rose-50 text-rose-800 ring-rose-100',
    info: 'bg-slate-50 text-slate-700 ring-slate-200',
}[statusTone.value]))

async function tandaiLokasi() {
    try {
        lokasi.value = await ambilLokasi()
    } catch (e) {
        status.value = e.message
        statusTone.value = 'error'
    }
}

async function simpan() {
    saving.value = true
    status.value = 'Menyimpan...'
    statusTone.value = 'info'
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
            statusTone.value = 'success'
        } else if (!navigator.onLine) {
            await enqueue('/api/keuangan/belanja', payload)
            status.value = 'Offline — disimpan di antrian, akan tersinkron.'
            statusTone.value = 'info'
        } else {
            const err = await res.json().catch(() => ({}))
            status.value = 'Gagal: ' + (err.message || res.status)
            statusTone.value = 'error'
        }
    } catch (e) {
        await enqueue('/api/keuangan/belanja', payload)
        status.value = 'Tidak ada koneksi — masuk antrian luring.'
        statusTone.value = 'info'
    } finally {
        saving.value = false
    }
}

window.addEventListener('online', () => flush())
</script>

<template>
    <Head title="Catat Belanja" />

    <AppShell :nav-items="navigation" brand-name="Keuangan Lapangan">
        <form class="space-y-4" @submit.prevent="simpan">
            <PageHeader kicker="Pengeluaran" title="Catat Belanja" subtitle="Belanja barang / jasa / sewa lapangan" />

            <UiField label="Uraian belanja" required>
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.uraian" placeholder="mis. Beras 25kg" />
                </template>
            </UiField>

            <UiField label="Jenis">
                <SegmentedControl v-model="form.jenis" :options="jenisOptions" />
            </UiField>

            <UiField label="Nominal (DPP)" required>
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.dpp" type="number" inputmode="numeric" placeholder="0" />
                </template>
            </UiField>

            <UiField label="Sumber Dana" required>
                <template #default="{ fieldId }">
                    <select :id="fieldId" v-model="form.sumber_dana_id" :class="selectClass" required>
                        <option value="">— Pilih Sumber Dana —</option>
                        <option v-for="s in sumberDana" :key="s.id" :value="s.id">{{ s.kode_dana }} — {{ s.nama_sumber }}</option>
                    </select>
                </template>
            </UiField>

            <UiField label="Divisi / Unit" hint="Opsional — pembebanan per unit">
                <template #default="{ fieldId }">
                    <select :id="fieldId" v-model="form.divisi_id" :class="selectClass">
                        <option value="">— Semua / Umum —</option>
                        <option v-for="d in divisi" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
                    </select>
                </template>
            </UiField>

            <UiField label="Akun Beban">
                <template #default="{ fieldId }">
                    <select :id="fieldId" v-model="form.akun_beban_id" :class="selectClass">
                        <option value="">— Akun Beban —</option>
                        <option v-for="a in akunBeban" :key="a.id" :value="a.id">{{ a.kode }} — {{ a.nama }}</option>
                    </select>
                </template>
            </UiField>

            <UiField label="Akun Kas">
                <template #default="{ fieldId }">
                    <select :id="fieldId" v-model="form.akun_kas_id" :class="selectClass">
                        <option value="">— Akun Kas —</option>
                        <option v-for="a in akunKas" :key="a.id" :value="a.id">{{ a.kode }} — {{ a.nama }}</option>
                    </select>
                </template>
            </UiField>

            <label class="flex items-center gap-2.5 rounded-2xl bg-white px-4 py-3 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-200">
                <input v-model="form.vendor_npwp" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                Vendor ber-NPWP
            </label>

            <UiButton type="button" variant="secondary" block icon-left="camera" @click="tandaiLokasi">
                {{ lokasi ? `GPS: ${lokasi.lat.toFixed(4)}, ${lokasi.lng.toFixed(4)}` : 'Tandai Lokasi (GPS)' }}
            </UiButton>

            <UiButton type="submit" variant="primary" block :loading="saving" icon-left="check">Simpan Belanja</UiButton>

            <p v-if="status" class="rounded-2xl px-4 py-3 text-sm font-medium ring-1 ring-inset" :class="statusClass">
                {{ status }}
            </p>
        </form>
    </AppShell>
</template>
