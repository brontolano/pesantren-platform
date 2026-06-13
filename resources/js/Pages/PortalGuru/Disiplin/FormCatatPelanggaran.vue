<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import PortalGuruLayout from '../../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, UiField, UiInput, UiButton, IconCircle, UiIcon, StatCard } from '@/Shared'

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

        <PageHeader
            kicker="Kepengasuhan · Disiplin"
            title="Catat Pelanggaran"
            subtitle="Tambah catatan pelanggaran santri baru"
        />

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <!-- Success Alert -->
            <div v-if="success" class="flex items-center gap-3 rounded-3xl bg-emerald-50 px-5 py-4 ring-1 ring-emerald-100">
                <IconCircle icon="check-circle" tone="emerald" size="sm" />
                <p class="text-sm font-semibold text-emerald-700">Pelanggaran berhasil dicatat! Mengalihkan...</p>
            </div>

            <!-- Error Alert -->
            <div v-if="error && !success" class="flex items-center gap-3 rounded-3xl bg-rose-50 px-5 py-4 ring-1 ring-rose-100">
                <IconCircle icon="x-circle" tone="rose" size="sm" />
                <p class="text-sm font-semibold text-rose-700">{{ error }}</p>
            </div>

            <!-- Pilih Santri -->
            <SectionCard title="Pilih Santri">
                <UiField label="Santri" required>
                    <template #default="{ fieldId, describedby }">
                        <select
                            :id="fieldId"
                            :aria-describedby="describedby"
                            v-model="form.santri_id"
                            @change="handleSantriChange"
                            required
                            class="w-full min-h-[48px] rounded-2xl bg-white px-4 py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
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
                    </template>
                </UiField>

                <!-- Info santri terpilih -->
                <div v-if="selectedSantri" class="mt-3 flex items-center gap-3 rounded-2xl bg-slate-50 p-3">
                    <IconCircle icon="user" tone="slate" size="sm" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">{{ selectedSantri.nama }}</p>
                        <p class="text-xs text-slate-600">{{ selectedSantri.nis }} · {{ selectedSantri.kelas }}</p>
                    </div>
                </div>
            </SectionCard>

            <!-- Pilih Jenis Pelanggaran -->
            <SectionCard title="Jenis Pelanggaran">
                <UiField label="Jenis Pelanggaran" required>
                    <template #default="{ fieldId, describedby }">
                        <select
                            :id="fieldId"
                            :aria-describedby="describedby"
                            v-model="form.jenis_pelanggaran_id"
                            @change="handleJenisChange"
                            required
                            class="w-full min-h-[48px] rounded-2xl bg-white px-4 py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
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
                    </template>
                </UiField>

                <!-- Info jenis terpilih -->
                <div v-if="selectedJenis" :class="['mt-3 rounded-2xl border p-3 text-sm', kategoriColor[selectedJenis.kategori]]">
                    <p class="flex items-center gap-2 font-semibold">
                        <UiIcon
                            :name="selectedJenis.kategori === 'berat' ? 'ban' : (selectedJenis.kategori === 'prestasi' ? 'heart' : 'info')"
                            class="h-4 w-4 shrink-0"
                        />
                        {{ selectedJenis.nama }}
                    </p>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="font-semibold">Poin</p>
                            <p class="text-lg font-bold tabular-nums">{{ selectedJenis.poin }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Sanksi Default</p>
                            <p>{{ selectedJenis.sanksi_default }}</p>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- Waktu Kejadian -->
            <SectionCard title="Waktu Kejadian">
                <UiField label="Waktu Kejadian" hint="Default: sekarang">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            :aria-describedby="describedby"
                            v-model="form.waktu_kejadian"
                            type="datetime-local"
                        />
                    </template>
                </UiField>
            </SectionCard>

            <!-- Lokasi -->
            <SectionCard title="Lokasi">
                <UiField label="Lokasi (Opsional)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            :aria-describedby="describedby"
                            v-model="form.lokasi"
                            type="text"
                            placeholder="mis. Asrama putra, Musholla, Kantor"
                        />
                    </template>
                </UiField>
            </SectionCard>

            <!-- Alasan / Kronologi -->
            <SectionCard title="Alasan / Kronologi">
                <UiField
                    label="Alasan / Kronologi"
                    required
                    :hint="`Karakter: ${form.alasan.length}/500`"
                >
                    <template #default="{ fieldId, describedby }">
                        <textarea
                            :id="fieldId"
                            :aria-describedby="describedby"
                            v-model="form.alasan"
                            required
                            minlength="5"
                            placeholder="Jelaskan detail kejadian pelanggaran secara singkat (min. 5 karakter)"
                            rows="5"
                            class="w-full rounded-2xl bg-white px-4 py-3 text-sm font-medium leading-6 text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        ></textarea>
                    </template>
                </UiField>
            </SectionCard>

            <!-- Summary -->
            <SectionCard v-if="selectedSantri && selectedJenis" title="Ringkasan Catatan">
                <div class="grid grid-cols-2 gap-3">
                    <StatCard
                        icon="user"
                        tone="slate"
                        label="Santri"
                        :value="selectedSantri.nama"
                        :hint="selectedSantri.nis"
                    />
                    <StatCard
                        icon="chart"
                        tone="amber"
                        label="Poin"
                        :value="selectedJenis.poin"
                        :hint="`${selectedJenis.nama} · ${selectedJenis.kategori}`"
                    />
                </div>
            </SectionCard>

            <!-- Actions -->
            <div class="flex gap-3 pb-6">
                <UiButton
                    href="/guru"
                    variant="secondary"
                    size="lg"
                    block
                >
                    Batal
                </UiButton>
                <UiButton
                    type="submit"
                    variant="primary"
                    size="lg"
                    block
                    :disabled="!isFormValid"
                    :loading="loading"
                    icon-left="check"
                >
                    {{ loading ? 'Menyimpan...' : 'Simpan' }}
                </UiButton>
            </div>
        </form>
    </div>
</template>
