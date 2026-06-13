<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import {
    PageHeader,
    SectionCard,
    ListRow,
    StatCard,
    EmptyState,
    UiButton,
    UiField,
    UiInput,
    SegmentedControl,
} from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    sesi: { type: Object, required: true },
    tanggal: { type: String, required: true },
    santri: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => ['hadir', 'izin', 'sakit', 'alpa'] },
    jurnal: { type: Object, default: null },
    jurnal_url: { type: String, default: null },
})

const statusMap = ref(Object.fromEntries(props.santri.map((s) => [s.id, s.status || 'hadir'])))
const saving = ref(false)
const savedAt = ref(null)
const errorMessage = ref(null)

const statusClasses = {
    hadir: 'bg-emerald-600 text-white',
    izin: 'bg-sky-600 text-white',
    sakit: 'bg-amber-500 text-white',
    alpa: 'bg-rose-600 text-white',
}

const ringkasan = computed(() => {
    const counts = { hadir: 0, izin: 0, sakit: 0, alpa: 0 }
    Object.values(statusMap.value).forEach((status) => {
        counts[status] = (counts[status] || 0) + 1
    })
    return counts
})

// Default semua HADIR — sekali ketuk memutar status pengecualian.
function cycleStatus(santriId) {
    const order = props.statusOptions
    const current = statusMap.value[santriId] || 'hadir'
    const next = order[(order.indexOf(current) + 1) % order.length]
    statusMap.value = { ...statusMap.value, [santriId]: next }
}

function buildPayload() {
    return {
        tanggal: props.tanggal,
        presensi: props.santri.map((s) => ({
            santri_id: s.id,
            status: statusMap.value[s.id] || 'hadir',
        })),
    }
}

// Retry fetch sederhana: gagal jaringan → coba ulang otomatis 1x (idempoten
// di server per santri+tanggal, retry aman tidak menggandakan baris).
function submit(attempt = 0) {
    saving.value = true
    errorMessage.value = null

    router.post(`/guru/presensi/${props.sesi.id}`, buildPayload(), {
        preserveScroll: true,
        onSuccess: () => {
            savedAt.value = new Date().toLocaleTimeString()
        },
        onError: () => {
            errorMessage.value = 'Sebagian data tidak valid. Periksa kembali.'
        },
        onFinish: (visit) => {
            saving.value = false

            if (!visit.completed && attempt < 1) {
                window.setTimeout(() => submit(attempt + 1), 1500)
            } else if (!visit.completed && attempt >= 1) {
                errorMessage.value = 'Gagal terkirim (jaringan). Coba lagi saat sinyal membaik.'
            }
        },
    })
}

// ---------------------------------------------------------------
// F2-PWA — Jurnal KBM 30-detik: form mini muncul SETELAH presensi
// tersimpan (atau bila jurnal hari ini sudah ada). Upsert server per
// (guru, jadwal, tanggal) — retry aman. jumlah_hadir dihitung server
// dari presensi tersimpan, bukan dikirim klien.
// ---------------------------------------------------------------
const jurnalForm = ref({
    materi: props.jurnal?.materi || '',
    metode: props.jurnal?.metode || '',
    status: props.jurnal?.status || 'terlaksana',
})
const jurnalSaving = ref(false)
const jurnalSavedAt = ref(null)
const jurnalError = ref(null)
const showJurnal = computed(() => Boolean(savedAt.value || props.jurnal))

function submitJurnal() {
    jurnalSaving.value = true
    jurnalError.value = null

    router.post(props.jurnal_url || `/guru/jurnal/${props.sesi.id}`, {
        tanggal: props.tanggal,
        materi: jurnalForm.value.materi || null,
        metode: jurnalForm.value.metode || null,
        status: jurnalForm.value.status,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            jurnalSavedAt.value = new Date().toLocaleTimeString()
        },
        onError: (errors) => {
            jurnalError.value = Object.values(errors)[0] || 'Jurnal tidak valid.'
        },
        onFinish: () => {
            jurnalSaving.value = false
        },
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Presensi Kelas</title>
        </Head>

        <PageHeader
            kicker="Presensi Kelas"
            :title="sesi.pelajaran"
            :subtitle="`${sesi.kelas} · ${tanggal} · ${sesi.jam_mulai}–${sesi.jam_selesai}`"
        />

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatCard tone="emerald" label="Hadir" :value="ringkasan.hadir" compact />
            <StatCard tone="blue" label="Izin" :value="ringkasan.izin" compact />
            <StatCard tone="amber" label="Sakit" :value="ringkasan.sakit" compact />
            <StatCard tone="rose" label="Alpa" :value="ringkasan.alpa" compact />
        </div>

        <EmptyState
            v-if="santri.length === 0"
            icon="user"
            title="Belum ada santri di rombel ini"
            message="Hubungi TU untuk memasukkan santri ke kelas."
        />

        <SectionCard
            v-else
            title="Daftar Santri"
            subtitle="Semua santri default hadir. Ketuk tombol status untuk menandai pengecualian."
            flush
        >
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="s in santri"
                    :key="s.id"
                    :title="s.nama"
                    :subtitle="s.nis"
                >
                    <template #trailing>
                        <button
                            type="button"
                            class="rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-wider transition"
                            :class="statusClasses[statusMap[s.id]] || statusClasses.hadir"
                            @click="cycleStatus(s.id)"
                        >
                            {{ statusMap[s.id] }}
                        </button>
                    </template>
                </ListRow>
            </div>
        </SectionCard>

        <section v-if="santri.length > 0" class="sticky bottom-20 space-y-2">
            <p v-if="errorMessage" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">
                {{ errorMessage }}
            </p>
            <p v-if="savedAt" class="rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
                Presensi tersimpan {{ savedAt }}.
            </p>
            <UiButton
                type="button"
                variant="primary"
                size="lg"
                block
                :loading="saving"
                :disabled="saving"
                @click="submit()"
            >
                {{ saving ? 'Menyimpan…' : 'Simpan Presensi' }}
            </UiButton>
        </section>

        <!-- F2-PWA — Jurnal KBM 30-detik -->
        <SectionCard
            v-if="showJurnal"
            kicker="Jurnal KBM"
            title="Jurnal KBM"
            :subtitle="`30 detik saja: materi & status sesi. Hadir otomatis ${ringkasan.hadir} santri.`"
        >
            <div class="space-y-3">
                <SegmentedControl
                    v-model="jurnalForm.status"
                    :options="[
                        { value: 'terlaksana', label: 'Terlaksana' },
                        { value: 'kosong', label: 'Kosong' },
                        { value: 'diganti', label: 'Diganti' },
                    ]"
                />

                <UiField label="Materi">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            :aria-describedby="describedby"
                            v-model="jurnalForm.materi"
                            type="textarea"
                            :rows="2"
                            placeholder="Materi yang diajarkan (wajib bila terlaksana)"
                        />
                    </template>
                </UiField>

                <UiField label="Metode">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            :aria-describedby="describedby"
                            v-model="jurnalForm.metode"
                            type="text"
                            placeholder="Metode (opsional: ceramah, hafalan, praktik…)"
                        />
                    </template>
                </UiField>

                <p v-if="jurnalError" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">{{ jurnalError }}</p>
                <p v-if="jurnalSavedAt" class="rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
                    Jurnal tersimpan {{ jurnalSavedAt }}.
                </p>

                <UiButton
                    type="button"
                    variant="primary"
                    size="lg"
                    block
                    :loading="jurnalSaving"
                    :disabled="jurnalSaving"
                    @click="submitJurnal()"
                >
                    {{ jurnalSaving ? 'Menyimpan…' : 'Simpan Jurnal KBM' }}
                </UiButton>
            </div>
        </SectionCard>
    </div>
</template>
