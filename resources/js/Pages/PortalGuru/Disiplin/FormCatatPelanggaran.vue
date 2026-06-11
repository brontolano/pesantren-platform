<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import PortalGuruLayout from '../../../Layouts/PortalGuruLayout.vue'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    hari: { type: String, required: true },
    tanggal: { type: String, required: true },
    santriList: { type: Array, default: () => [] },
    jenisList: { type: Array, default: () => [] },
    istilah: { type: Object, default: () => ({}) },
})

const form = ref({
    santri_id: '',
    jenis_pelanggaran_id: '',
    alasan: '',
    waktu_kejadian: new Date().toISOString().slice(0, 16),
    lokasi: '',
})

const loading = ref(false)
const error = ref('')
const success = ref(false)
const selectedSantri = ref(null)
const selectedJenis = ref(null)

const kategoriIcon = {
    'ringan': '⚠️',
    'sedang': '⚠️⚠️',
    'berat': '🛑',
    'prestasi': '⭐',
}

const kategoriColor = {
    'ringan': 'bg-emerald-50 border-emerald-200 text-emerald-700',
    'sedang': 'bg-amber-50 border-amber-200 text-amber-700',
    'berat': 'bg-red-50 border-red-200 text-red-700',
    'prestasi': 'bg-blue-50 border-blue-200 text-blue-700',
}

const isFormValid = computed(() => {
    return (
        form.value.santri_id &&
        form.value.jenis_pelanggaran_id &&
        form.value.alasan.trim().length >= 5
    )
})

const handleSantriChange = () => {
    selectedSantri.value = props.santriList.find(s => s.id == form.value.santri_id) || null
}

const handleJenisChange = () => {
    selectedJenis.value = props.jenisList.find(j => j.id == form.value.jenis_pelanggaran_id) || null
}

const handleSubmit = async () => {
    if (!isFormValid.value) return

    loading.value = true
    error.value = ''
    success.value = false

    try {
        const clientRef = `${form.value.santri_id}-${form.value.jenis_pelanggaran_id}-${Date.now()}`

        const res = await fetch('/api/pwa/guru/disiplin/catatPelanggaran', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('sanctum_token')}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                ...form.value,
                client_ref: clientRef,
            }),
        })

        const json = await res.json()

        if (json.status === 'success') {
            success.value = true
            form.value = {
                santri_id: '',
                jenis_pelanggaran_id: '',
                alasan: '',
                waktu_kejadian: new Date().toISOString().slice(0, 16),
                lokasi: '',
            }
            selectedSantri.value = null
            selectedJenis.value = null

            // Redirect after 1.5s
            setTimeout(() => {
                window.location.href = '/guru/disiplin/daftar'
            }, 1500)
        } else {
            error.value = json.message || 'Gagal mencatat pelanggaran'
        }
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Catat Pelanggaran</title>
        </Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">
                Kepengasuhan · Disiplin
            </p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Catat Pelanggaran</h2>
            <p class="text-sm text-slate-500">Tambah catatan pelanggaran santri baru</p>
        </section>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <!-- Success Alert -->
            <section v-if="success" class="rounded-[1.5rem] bg-emerald-50 border border-emerald-200 px-5 py-4">
                <p class="text-sm font-semibold text-emerald-700">✓ Pelanggaran berhasil dicatat! Mengalihkan...</p>
            </section>

            <!-- Error Alert -->
            <section v-if="error && !success" class="rounded-[1.5rem] bg-red-50 border border-red-200 px-5 py-4">
                <p class="text-sm font-semibold text-red-700">{{ error }}</p>
            </section>

            <!-- Pilih Santri -->
            <section class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
                <label class="block text-sm font-semibold text-slate-700">Pilih Santri</label>
                <select
                    v-model="form.santri_id"
                    @change="handleSantriChange"
                    required
                    class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none"
                >
                    <option value="">-- Pilih Santri --</option>
                    <option
                        v-for="santri in santriList"
                        :key="santri.id"
                        :value="santri.id"
                    >
                        {{ santri.nama }} ({{ santri.nis }}) · {{ santri.kelas }}
                    </option>
                </select>

                <!-- Info santri terpilih -->
                <div v-if="selectedSantri" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
                    <p class="font-semibold text-slate-900">{{ selectedSantri.nama }}</p>
                    <p class="text-xs text-slate-600">{{ selectedSantri.nis }} · {{ selectedSantri.kelas }}</p>
                </div>
            </section>

            <!-- Pilih Jenis Pelanggaran -->
            <section class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
                <label class="block text-sm font-semibold text-slate-700">Jenis Pelanggaran</label>
                <select
                    v-model="form.jenis_pelanggaran_id"
                    @change="handleJenisChange"
                    required
                    class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none"
                >
                    <option value="">-- Pilih Jenis --</option>
                    <option
                        v-for="jenis in jenisList"
                        :key="jenis.id"
                        :value="jenis.id"
                        :disabled="!jenis.aktif"
                    >
                        {{ jenis.nama }} ({{ jenis.poin }} poin) — {{ jenis.kategori }}
                    </option>
                </select>

                <!-- Info jenis terpilih -->
                <div v-if="selectedJenis" :class="['mt-3 rounded-lg p-3 text-sm', kategoriColor[selectedJenis.kategori]]">
                    <p class="font-semibold">
                        {{ kategoriIcon[selectedJenis.kategori] }} {{ selectedJenis.nama }}
                    </p>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="font-semibold">Poin</p>
                            <p class="text-lg font-bold">{{ selectedJenis.poin }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Sanksi Default</p>
                            <p>{{ selectedJenis.sanksi_default }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Waktu Kejadian -->
            <section class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
                <label class="block text-sm font-semibold text-slate-700">Waktu Kejadian</label>
                <input
                    v-model="form.waktu_kejadian"
                    type="datetime-local"
                    class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none"
                />
                <p class="mt-1 text-xs text-slate-500">Default: sekarang</p>
            </section>

            <!-- Lokasi -->
            <section class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
                <label class="block text-sm font-semibold text-slate-700">Lokasi (Opsional)</label>
                <input
                    v-model="form.lokasi"
                    type="text"
                    placeholder="mis. Asrama putra, Musholla, Kantor"
                    class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none"
                />
            </section>

            <!-- Alasan / Kronologi -->
            <section class="rounded-[1.5rem] bg-white px-5 py-4 shadow">
                <label class="block text-sm font-semibold text-slate-700">
                    Alasan / Kronologi <span class="text-red-500">*</span>
                </label>
                <textarea
                    v-model="form.alasan"
                    required
                    minlength="5"
                    placeholder="Jelaskan detail kejadian pelanggaran secara singkat (min. 5 karakter)"
                    class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-amber-500 focus:outline-none"
                    rows="5"
                ></textarea>
                <p class="mt-1 text-xs text-slate-500">
                    Karakter: {{ form.alasan.length }}/500
                </p>
            </section>

            <!-- Summary -->
            <section v-if="selectedSantri && selectedJenis" class="rounded-[1.5rem] bg-gradient-to-r from-amber-50 to-amber-100 border border-amber-200 px-5 py-4">
                <p class="text-sm font-semibold text-amber-900">📋 Ringkasan Catatan</p>
                <div class="mt-3 space-y-1 text-sm text-amber-800">
                    <p>
                        <span class="font-semibold">Santri:</span> {{ selectedSantri.nama }} ({{ selectedSantri.nis }})
                    </p>
                    <p>
                        <span class="font-semibold">Jenis:</span> {{ selectedJenis.nama }} · {{ selectedJenis.kategori }}
                    </p>
                    <p>
                        <span class="font-semibold">Poin:</span> <span class="font-bold">{{ selectedJenis.poin }}</span>
                    </p>
                </div>
            </section>

            <!-- Actions -->
            <section class="flex gap-3 pb-6">
                <Link
                    href="/guru"
                    class="flex-1 rounded-[1rem] bg-slate-200 px-4 py-3 text-center font-semibold text-slate-700 hover:bg-slate-300 transition-colors"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="!isFormValid || loading"
                    :class="[
                        'flex-1 rounded-[1rem] px-4 py-3 text-center font-semibold transition-colors',
                        isFormValid && !loading
                            ? 'bg-amber-500 text-white hover:bg-amber-600'
                            : 'bg-slate-200 text-slate-500 cursor-not-allowed',
                    ]"
                >
                    {{ loading ? 'Menyimpan...' : '✓ Simpan' }}
                </button>
            </section>
        </form>
    </div>
</template>
