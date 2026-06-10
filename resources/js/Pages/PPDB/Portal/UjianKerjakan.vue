<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SpmbLayout from '../../../Layouts/SpmbLayout.vue';

const props = defineProps({
    attemptId: Number,
    paketId: Number,
    paketNama: String,
    deadline: String,           // ISO dari SERVER — sumber kebenaran timer
    soal: { type: Array, default: () => [] },
    jawaban: { type: Object, default: () => ({}) },
});

const jawabanLokal = reactive({ ...props.jawaban });
const statusSimpan = ref('');
const sisaDetik = ref(0);
let timer = null;

const sisaLabel = computed(() => {
    const m = Math.floor(sisaDetik.value / 60);
    const s = sisaDetik.value % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

function hitungSisa() {
    const sisa = Math.max(0, Math.floor((new Date(props.deadline) - new Date()) / 1000));
    sisaDetik.value = sisa;
    if (sisa === 0) selesai(); // waktu habis → auto-submit (server tetap pagar utama)
}

async function simpan(soalId) {
    statusSimpan.value = 'Menyimpan…';
    try {
        const res = await fetch(`/spmb/ujian/${props.paketId}/jawab`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ soal_id: soalId, jawaban: String(jawabanLokal[soalId] ?? '') }),
        });
        statusSimpan.value = res.ok ? 'Tersimpan ✓' : 'Gagal menyimpan — coba lagi';
    } catch {
        statusSimpan.value = 'Offline — jawaban belum tersimpan';
    }
}

function pilih(soalId, huruf) {
    jawabanLokal[soalId] = huruf;
    simpan(soalId);
}

function selesai() {
    if (timer) clearInterval(timer);
    router.post(`/spmb/ujian/${props.paketId}/selesai`);
}

onMounted(() => {
    hitungSisa();
    timer = setInterval(hitungSisa, 1000);
});
onBeforeUnmount(() => timer && clearInterval(timer));
</script>

<template>
    <SpmbLayout>
        <div class="mx-auto max-w-3xl space-y-5 px-4 py-6 pb-28">
            <header class="sticky top-0 z-10 -mx-4 border-b border-stone-200 bg-white/95 px-4 py-3 backdrop-blur">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold text-stone-900">{{ paketNama }}</div>
                        <div class="text-xs text-stone-500">{{ statusSimpan || 'Jawaban tersimpan otomatis' }}</div>
                    </div>
                    <div
                        class="rounded-full px-4 py-2 text-sm font-bold tabular-nums"
                        :class="sisaDetik <= 300 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                    >
                        ⏱ {{ sisaLabel }}
                    </div>
                </div>
            </header>

            <article
                v-for="s in soal"
                :key="s.id"
                class="rounded-2xl border border-stone-200 bg-white px-5 py-4 shadow-sm"
            >
                <div class="text-sm font-semibold text-stone-500">Soal {{ s.nomor }}</div>
                <p class="mt-1 whitespace-pre-line text-base text-stone-900">{{ s.pertanyaan }}</p>

                <div v-if="s.tipe === 'pg'" class="mt-3 space-y-2">
                    <label
                        v-for="(teks, huruf) in s.pilihan"
                        :key="huruf"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3"
                        :class="jawabanLokal[s.id] === huruf ? 'border-emerald-500 bg-emerald-50' : 'border-stone-200'"
                    >
                        <input
                            type="radio"
                            :name="`soal-${s.id}`"
                            :value="huruf"
                            :checked="jawabanLokal[s.id] === huruf"
                            class="mt-1"
                            @change="pilih(s.id, huruf)"
                        >
                        <span class="text-sm text-stone-800"><b>{{ huruf }}.</b> {{ teks }}</span>
                    </label>
                </div>

                <textarea
                    v-else
                    v-model="jawabanLokal[s.id]"
                    rows="5"
                    class="mt-3 w-full rounded-xl border border-stone-300 px-4 py-3 text-sm"
                    placeholder="Tulis jawaban Anda…"
                    @blur="simpan(s.id)"
                />
            </article>

            <div class="fixed inset-x-0 bottom-0 border-t border-stone-200 bg-white px-4 py-3">
                <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
                    <span class="text-xs text-stone-500">Periksa kembali sebelum mengirim — ujian tidak bisa diulang.</span>
                    <button
                        type="button"
                        class="rounded-full bg-emerald-600 px-6 py-3 text-sm font-semibold text-white"
                        @click="selesai"
                    >
                        Kirim Jawaban
                    </button>
                </div>
            </div>
        </div>
    </SpmbLayout>
</template>
