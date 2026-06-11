<script setup>
import { Head, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'

defineOptions({ layout: PortalGuruLayout })

const props = defineProps({
    antrean: { type: Array, default: () => [] },
})

const skor = reactive({})      // attempt_id -> { soal_id: nilai }
const sibuk = ref(null)
const pesan = ref('')

props.antrean.forEach((a) => {
    skor[a.attempt_id] = {}
    a.essay.forEach((e) => { skor[a.attempt_id][e.soal_id] = null })
})

async function simpan(a) {
    sibuk.value = a.attempt_id
    pesan.value = ''
    try {
        const res = await fetch(`/guru/ujian/koreksi/${a.attempt_id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || ''),
            },
            body: JSON.stringify({ skor: skor[a.attempt_id] }),
        })
        const json = await res.json()
        if (res.ok && json.ok) {
            router.reload({ only: ['antrean'] })
        } else {
            pesan.value = json.pesan || 'Gagal menyimpan nilai.'
        }
    } catch {
        pesan.value = 'Jaringan bermasalah — coba lagi.'
    } finally {
        sibuk.value = null
    }
}
</script>

<template>
    <div class="space-y-5">
        <Head><title>Koreksi Essay</title></Head>

        <section>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Koreksi Essay</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Antrean Jawaban</h2>
            <p class="text-sm text-slate-500">Beri skor 0..bobot per soal. Nilai final dihitung server (tertimbang bobot).</p>
        </section>

        <p v-if="pesan" class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ pesan }}</p>

        <section v-if="antrean.length === 0" class="rounded-[1.5rem] bg-white px-5 py-8 text-center shadow">
            <p class="text-base font-semibold text-slate-800">Tidak ada essay menunggu nilai</p>
        </section>

        <section v-else class="space-y-4">
            <div v-for="a in antrean" :key="a.attempt_id" class="rounded-2xl bg-white px-4 py-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ a.santri }} <span class="font-normal text-slate-400">· NIS {{ a.nis }}</span></p>
                        <p class="text-xs text-slate-400">{{ a.pelajaran }} · {{ a.sesi }} · Nilai PG: {{ a.nilai_pg ?? '—' }}</p>
                    </div>
                </div>

                <div v-for="e in a.essay" :key="e.soal_id" class="mt-3 rounded-xl bg-slate-50 px-3 py-3">
                    <p class="text-xs font-semibold text-slate-700">{{ e.pertanyaan }}</p>
                    <p class="mt-2 whitespace-pre-wrap rounded-lg bg-white px-3 py-2 text-sm text-slate-800">{{ e.jawaban || '(tidak dijawab)' }}</p>
                    <label class="mt-2 flex items-center gap-2 text-xs text-slate-600">
                        Skor (0–{{ e.bobot }})
                        <input
                            v-model.number="skor[a.attempt_id][e.soal_id]"
                            type="number" min="0" :max="e.bobot" step="0.5"
                            class="w-24 rounded-lg border-slate-300 py-1 text-sm"
                        >
                    </label>
                </div>

                <button
                    type="button"
                    class="mt-3 w-full rounded-xl bg-emerald-600 py-2.5 text-sm font-bold text-white disabled:opacity-50"
                    :disabled="sibuk === a.attempt_id || a.essay.some((e) => skor[a.attempt_id][e.soal_id] === null || skor[a.attempt_id][e.soal_id] === '')"
                    @click="simpan(a)"
                >{{ sibuk === a.attempt_id ? 'Menyimpan…' : 'Simpan Nilai' }}</button>
            </div>
        </section>
    </div>
</template>
