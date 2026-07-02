<script setup>
import { router } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'

const props = defineProps({
    sesi: { type: Object, required: true },
    attempt: { type: Object, required: true },
    soal: { type: Array, default: () => [] },
})

const jawaban = reactive({ ...props.attempt.jawaban })
const sisaDetik = ref(props.attempt.sisa_detik)
const statusSimpan = reactive({}) // soal_id -> 'menyimpan'|'tersimpan'|'gagal'
let timer = null

onMounted(() => {
    timer = setInterval(() => {
        if (sisaDetik.value > 0) sisaDetik.value -= 1
        else kumpulkan() // waktu habis → auto-submit (server tetap otoritatif)
    }, 1000)
})
onUnmounted(() => clearInterval(timer))

const jam = computed(() => {
    const m = Math.floor(sisaDetik.value / 60)
    const s = sisaDetik.value % 60
    return `${m}:${String(s).padStart(2, '0')}`
})

const xsrf = () => decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '')

async function simpanJawaban(soalId) {
    const nilai = jawaban[soalId]
    if (nilai === undefined || nilai === null || nilai === '') return
    statusSimpan[soalId] = 'menyimpan'
    try {
        const res = await fetch(`/app/ujian/${props.sesi.id}/jawab`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            body: JSON.stringify({ soal_id: soalId, jawaban: String(nilai) }),
        })
        statusSimpan[soalId] = res.ok ? 'tersimpan' : 'gagal'
    } catch {
        statusSimpan[soalId] = 'gagal'
    }
}

let sudahKumpul = false
function kumpulkan() {
    if (sudahKumpul) return
    sudahKumpul = true
    clearInterval(timer)
    router.post(`/app/ujian/${props.sesi.id}/selesai`)
}
</script>

<template>
    <div class="min-h-screen bg-[#fffaf3] pb-24">
        <header class="sticky top-0 z-10 bg-emerald-700 px-5 py-3 text-white shadow">
            <div class="mx-auto flex max-w-md items-center justify-between">
                <p class="truncate text-sm font-bold">{{ sesi.nama }}</p>
                <p class="rounded-lg bg-white/15 px-3 py-1 font-mono text-sm font-bold" :class="{ 'animate-pulse text-amber-300': sisaDetik < 300 }">
                    {{ jam }}
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-md space-y-4 px-5 pt-4">
            <section v-for="(s, i) in soal" :key="s.id" class="rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-bold text-stone-400">Soal {{ i + 1 }} <span v-if="s.tipe !== 'pg'" class="text-amber-600">· Essay</span></p>
                <p class="mt-1 whitespace-pre-wrap text-sm text-stone-800">{{ s.pertanyaan }}</p>

                <div v-if="s.tipe === 'pg'" class="mt-3 space-y-2">
                    <label
                        v-for="(teks, huruf) in s.pilihan"
                        :key="huruf"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-sm"
                        :class="jawaban[s.id] === huruf ? 'border-emerald-600 bg-emerald-50 font-semibold text-emerald-800' : 'border-stone-200'"
                    >
                        <input
                            v-model="jawaban[s.id]"
                            type="radio" :name="`soal-${s.id}`" :value="huruf" class="text-emerald-600"
                            @change="simpanJawaban(s.id)"
                        >
                        <span><b>{{ huruf }}.</b> {{ teks }}</span>
                    </label>
                </div>

                <textarea
                    v-else
                    v-model="jawaban[s.id]"
                    rows="4"
                    class="mt-3 w-full rounded-xl border-stone-300 text-sm"
                    placeholder="Tulis jawabanmu…"
                    @blur="simpanJawaban(s.id)"
                />

                <p class="mt-2 text-right text-[10px]" :class="{
                    'text-stone-400': statusSimpan[s.id] === 'menyimpan',
                    'text-emerald-600': statusSimpan[s.id] === 'tersimpan',
                    'text-rose-600': statusSimpan[s.id] === 'gagal',
                }">
                    {{ statusSimpan[s.id] === 'menyimpan' ? 'Menyimpan…' : statusSimpan[s.id] === 'tersimpan' ? 'Tersimpan ✓' : statusSimpan[s.id] === 'gagal' ? 'Gagal menyimpan — ubah lagi utk ulang' : '' }}
                </p>
            </section>
        </main>

        <footer class="fixed inset-x-0 bottom-0 bg-white/95 px-5 py-3 shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
            <div class="mx-auto max-w-md">
                <button type="button" class="w-full rounded-xl bg-emerald-700 py-3 text-sm font-bold text-white" @click="kumpulkan">
                    Kumpulkan Ujian
                </button>
            </div>
        </footer>
    </div>
</template>
