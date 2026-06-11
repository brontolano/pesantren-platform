<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    santri: { type: Array, default: () => [] },
    halaqahId: { type: [Number, String], default: null },
})

function freshForm() {
    return {
        santri_id: '',
        surah: '',
        ayat_awal: null,
        ayat_akhir: null,
        jenis: 'ziyadah',
        nilai: null,
        catatan: '',
        // Idempoten: satu UUID per entri form — retry mengirim ref yang sama,
        // server dedup; sukses → UUID baru untuk entri berikutnya.
        client_ref: crypto.randomUUID(),
    }
}

const form = ref(freshForm())
const saving = ref(false)
const successMessage = ref(null)
const errorMessage = ref(null)

function submit(attempt = 0) {
    saving.value = true
    errorMessage.value = null

    router.post('/guru/setoran', { ...form.value, halaqah_id: props.halaqahId }, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'Setoran tercatat.'
            form.value = freshForm()
        },
        onError: () => {
            errorMessage.value = 'Periksa kembali isian setoran.'
        },
        onFinish: (visit) => {
            saving.value = false

            // Retry fetch sederhana: gagal jaringan → ulang 1x dengan
            // client_ref yang SAMA (server tidak akan mencatat dobel).
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
    <form class="space-y-4 rounded-[1.5rem] bg-white px-5 py-5 shadow" @submit.prevent="submit()">
        <div>
            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="setoran-santri">Santri</label>
            <select
                id="setoran-santri"
                v-model="form.santri_id"
                required
                class="mt-1 w-full rounded-xl border-slate-200 text-sm"
            >
                <option value="" disabled>Pilih santri…</option>
                <option v-for="s in santri" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
            </select>
        </div>

        <div>
            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="setoran-surah">Surah</label>
            <input
                id="setoran-surah"
                v-model="form.surah"
                type="text"
                required
                maxlength="100"
                placeholder="cth. Al-Mulk"
                class="mt-1 w-full rounded-xl border-slate-200 text-sm"
            >
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="setoran-ayat-awal">Ayat awal</label>
                <input id="setoran-ayat-awal" v-model.number="form.ayat_awal" type="number" min="1" max="286" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="setoran-ayat-akhir">Ayat akhir</label>
                <input id="setoran-ayat-akhir" v-model.number="form.ayat_akhir" type="number" min="1" max="286" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
            </div>
        </div>

        <div>
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis setoran</span>
            <div class="mt-1 grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-sm font-semibold"
                    :class="form.jenis === 'ziyadah' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600'"
                    @click="form.jenis = 'ziyadah'"
                >
                    Ziyadah
                </button>
                <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-sm font-semibold"
                    :class="form.jenis === 'murajaah' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600'"
                    @click="form.jenis = 'murajaah'"
                >
                    Muraja'ah
                </button>
            </div>
        </div>

        <div>
            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="setoran-nilai">Nilai (0–100)</label>
            <input id="setoran-nilai" v-model.number="form.nilai" type="number" min="0" max="100" step="0.5" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
        </div>

        <div>
            <label class="text-xs font-semibold uppercase tracking-wider text-slate-500" for="setoran-catatan">Catatan</label>
            <textarea id="setoran-catatan" v-model="form.catatan" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
        </div>

        <p v-if="errorMessage" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">{{ errorMessage }}</p>
        <p v-if="successMessage" class="rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">{{ successMessage }}</p>

        <button
            type="submit"
            class="w-full rounded-full bg-emerald-700 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-white disabled:opacity-60"
            :disabled="saving"
        >
            {{ saving ? 'Menyimpan…' : 'Catat Setoran' }}
        </button>
    </form>
</template>
