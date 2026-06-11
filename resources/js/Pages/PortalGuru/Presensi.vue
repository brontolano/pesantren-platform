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
    </div>
</template>
