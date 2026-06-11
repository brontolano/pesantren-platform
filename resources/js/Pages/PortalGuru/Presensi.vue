<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

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

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Presensi Kelas</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ sesi.pelajaran }}</h2>
            <p class="text-sm text-slate-500">{{ sesi.kelas }} · {{ tanggal }} · {{ sesi.jam_mulai }}–{{ sesi.jam_selesai }}</p>
        </section>

        <section class="flex flex-wrap gap-2 text-xs font-semibold">
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Hadir {{ ringkasan.hadir }}</span>
            <span class="rounded-full bg-sky-50 px-3 py-1 text-sky-700">Izin {{ ringkasan.izin }}</span>
            <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-700">Sakit {{ ringkasan.sakit }}</span>
            <span class="rounded-full bg-rose-50 px-3 py-1 text-rose-700">Alpa {{ ringkasan.alpa }}</span>
        </section>

        <section v-if="santri.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada santri di rombel ini</p>
            <p class="mt-2 text-sm text-slate-500">Hubungi TU untuk memasukkan santri ke kelas.</p>
        </section>

        <section v-else class="space-y-2">
            <p class="text-xs text-slate-500">Semua santri default <strong>hadir</strong>. Ketuk tombol status untuk menandai pengecualian.</p>
            <div
                v-for="s in santri"
                :key="s.id"
                class="flex items-center justify-between gap-3 rounded-2xl bg-white px-4 py-3 shadow-sm"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ s.nama }}</p>
                    <p class="text-xs text-slate-400">{{ s.nis }}</p>
                </div>
                <button
                    type="button"
                    class="rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-wider transition"
                    :class="statusClasses[statusMap[s.id]] || statusClasses.hadir"
                    @click="cycleStatus(s.id)"
                >
                    {{ statusMap[s.id] }}
                </button>
            </div>
        </section>

        <section v-if="santri.length > 0" class="sticky bottom-20 space-y-2">
            <p v-if="errorMessage" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">
                {{ errorMessage }}
            </p>
            <p v-if="savedAt" class="rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
                Presensi tersimpan {{ savedAt }}.
            </p>
            <button
                type="button"
                class="w-full rounded-full bg-emerald-700 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-white disabled:opacity-60"
                :disabled="saving"
                @click="submit()"
            >
                {{ saving ? 'Menyimpan…' : 'Simpan Presensi' }}
            </button>
        </section>

        <!-- F2-PWA — Jurnal KBM 30-detik -->
        <section v-if="showJurnal" class="space-y-3 rounded-[1.5rem] bg-white px-5 py-5 shadow">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Jurnal KBM</p>
                <p class="mt-1 text-sm text-slate-500">30 detik saja: materi & status sesi. Hadir otomatis {{ ringkasan.hadir }} santri.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="opsi in ['terlaksana', 'kosong', 'diganti']"
                    :key="opsi"
                    type="button"
                    class="rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-wider transition"
                    :class="jurnalForm.status === opsi ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600'"
                    @click="jurnalForm.status = opsi"
                >
                    {{ opsi }}
                </button>
            </div>

            <textarea
                v-model="jurnalForm.materi"
                rows="2"
                placeholder="Materi yang diajarkan (wajib bila terlaksana)"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none"
            ></textarea>

            <input
                v-model="jurnalForm.metode"
                type="text"
                placeholder="Metode (opsional: ceramah, hafalan, praktik…)"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none"
            />

            <p v-if="jurnalError" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">{{ jurnalError }}</p>
            <p v-if="jurnalSavedAt" class="rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
                Jurnal tersimpan {{ jurnalSavedAt }}.
            </p>

            <button
                type="button"
                class="w-full rounded-full bg-amber-600 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-white disabled:opacity-60"
                :disabled="jurnalSaving"
                @click="submitJurnal()"
            >
                {{ jurnalSaving ? 'Menyimpan…' : 'Simpan Jurnal KBM' }}
            </button>
        </section>
    </div>
</template>
