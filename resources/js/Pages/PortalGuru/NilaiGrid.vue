<script setup>
import { reactive, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    kelas_id: { type: Number, required: true },
    kelas: { type: String, default: '' },
    pelajaran_id: { type: Number, required: true },
    pelajaran: { type: String, default: '' },
    periode: { type: Object, required: true },
    komponen: { type: Array, default: () => [] },
    baris: { type: Array, default: () => [] },
})

// State per baris: nilai komponen (atau harian/uts/uas bila tenant tanpa
// komponen), status autosave per baris.
const rows = reactive(
    props.baris.map((b) => ({
        ...b,
        nilai_komponen: { ...(b.nilai_komponen || {}) },
        _state: 'idle', // idle|pending|saving|saved|error
        _error: null,
    })),
)

const pakaiKomponen = props.komponen.length > 0
const timers = ref({})

// Autosave per-baris: debounce 800ms setelah ketikan terakhir, POST satu
// baris. Server meng-upsert by (santri, pelajaran, periode) — kirim ulang
// baris sama (putus-sambung) TIDAK menggandakan baris.
function jadwalkanSimpan(idx) {
    rows[idx]._state = 'pending'
    rows[idx]._error = null

    if (timers.value[idx]) {
        window.clearTimeout(timers.value[idx])
    }

    timers.value[idx] = window.setTimeout(() => simpanBaris(idx), 800)
}

async function simpanBaris(idx, attempt = 0) {
    const row = rows[idx]
    row._state = 'saving'

    const nilaiKomponen = {}
    Object.entries(row.nilai_komponen || {}).forEach(([id, v]) => {
        if (v !== null && v !== '') {
            nilaiKomponen[id] = v
        }
    })

    const payload = {
        santri_id: row.santri_id,
        nilai_komponen: pakaiKomponen ? nilaiKomponen : null,
        nilai_harian: pakaiKomponen ? null : (row.nilai_harian === '' ? null : row.nilai_harian),
        nilai_uts: pakaiKomponen ? null : (row.nilai_uts === '' ? null : row.nilai_uts),
        nilai_uas: pakaiKomponen ? null : (row.nilai_uas === '' ? null : row.nilai_uas),
    }

    try {
        const response = await fetch(`/guru/nilai/${props.kelas_id}/${props.pelajaran_id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    (document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '',
                ),
            },
            body: JSON.stringify(payload),
        })

        if (response.status === 422) {
            const body = await response.json()
            row._state = 'error'
            row._error = Object.values(body.errors || {}).flat()[0] || 'Nilai tidak valid (0-100).'
            return
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`)
        }

        const body = await response.json()
        row.nilai_akhir = body.nilai_akhir
        row._state = 'saved'
    } catch (e) {
        // Putus-sambung: retry otomatis 1x — idempoten di server (upsert).
        if (attempt < 1) {
            window.setTimeout(() => simpanBaris(idx, attempt + 1), 1500)
            return
        }

        row._state = 'error'
        row._error = 'Gagal terkirim (jaringan). Akan tersimpan saat diketik ulang.'
    }
}

const stateBadge = {
    pending: { text: '…', cls: 'text-slate-400' },
    saving: { text: 'menyimpan', cls: 'text-amber-600' },
    saved: { text: '✓ tersimpan', cls: 'text-emerald-700' },
    error: { text: 'gagal', cls: 'text-rose-600' },
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Grid Nilai</title>
        </Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Grid Nilai</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ pelajaran }}</h2>
            <p class="text-sm text-slate-500">{{ kelas }} · {{ periode.nama }} · tersimpan otomatis per baris</p>
        </section>

        <section v-if="rows.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Belum ada santri aktif di kelas ini</p>
        </section>

        <section v-else class="overflow-x-auto rounded-[1.5rem] bg-white shadow">
            <table class="w-full min-w-[480px] table-auto text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                        <th class="px-4 py-3">Santri</th>
                        <template v-if="komponen.length > 0">
                            <th v-for="k in komponen" :key="k.id" class="px-2 py-3">
                                {{ k.nama }}
                                <span class="block font-normal normal-case">{{ k.bobot }}%</span>
                            </th>
                        </template>
                        <template v-else>
                            <th class="px-2 py-3">Harian</th>
                            <th class="px-2 py-3">UTS</th>
                            <th class="px-2 py-3">UAS</th>
                        </template>
                        <th class="px-2 py-3">Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in rows" :key="row.santri_id" class="border-b border-slate-50">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-900">{{ row.nama }}</p>
                            <p class="text-xs" :class="stateBadge[row._state]?.cls || 'text-slate-400'">
                                {{ row._error || stateBadge[row._state]?.text || row.nis }}
                            </p>
                        </td>
                        <template v-if="komponen.length > 0">
                            <td v-for="k in komponen" :key="k.id" class="px-2 py-2">
                                <input
                                    v-model="row.nilai_komponen[k.id]"
                                    type="number"
                                    min="0"
                                    max="100"
                                    inputmode="numeric"
                                    class="w-16 rounded-xl border border-slate-200 px-2 py-2 text-center focus:border-emerald-600 focus:outline-none"
                                    @input="jadwalkanSimpan(idx)"
                                />
                            </td>
                        </template>
                        <template v-else>
                            <td v-for="kolom in ['nilai_harian', 'nilai_uts', 'nilai_uas']" :key="kolom" class="px-2 py-2">
                                <input
                                    v-model="row[kolom]"
                                    type="number"
                                    min="0"
                                    max="100"
                                    inputmode="numeric"
                                    class="w-16 rounded-xl border border-slate-200 px-2 py-2 text-center focus:border-emerald-600 focus:outline-none"
                                    @input="jadwalkanSimpan(idx)"
                                />
                            </td>
                        </template>
                        <td class="px-2 py-2 text-center font-semibold text-slate-900">
                            {{ row.nilai_akhir ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
