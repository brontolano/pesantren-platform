<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SpmbLayout from '@/Layouts/SpmbLayout.vue';
import MdCard from '@/spmb/components/MdCard.vue';
import MdButton from '@/spmb/components/MdButton.vue';

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

// Navigasi fokus satu-soal-per-layar (murni presentasi, tidak menyentuh
// logika penyimpanan jawaban / endpoint). Index 0-based di dalam array soal.
const aktif = ref(0);
const soalAktif = computed(() => props.soal[aktif.value] ?? null);

// Apakah sebuah soal sudah dijawab (untuk indikator grid nomor).
function sudahDijawab(s) {
    const v = jawabanLokal[s.id];
    return v !== undefined && v !== null && String(v).trim() !== '';
}

function keNomor(i) {
    aktif.value = Math.min(Math.max(i, 0), props.soal.length - 1);
}
function sebelumnya() { keNomor(aktif.value - 1); }
function berikutnya() { keNomor(aktif.value + 1); }

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
    <SpmbLayout :bottom-nav="false" show-back :back-href="'/spmb/ujian'" :title="paketNama">
        <!-- Timer di app bar (sumber kebenaran dari server) -->
        <template #app-bar-actions>
            <div
                class="flex items-center gap-1 rounded-full px-3 py-1.5 text-[13px] font-bold tabular-nums"
                :class="sisaDetik <= 300 ? 'bg-red-100 text-red-700' : 'bg-[var(--spmb-primary)]/12 text-[var(--spmb-primary)]'"
            >
                <span aria-hidden="true">⏱</span>
                <span>{{ sisaLabel }}</span>
            </div>
        </template>

        <div class="space-y-4 pb-2">
            <!-- Status simpan + progres -->
            <div class="flex items-center justify-between px-1">
                <span class="text-[12px] text-stone-500">{{ statusSimpan || 'Jawaban tersimpan otomatis' }}</span>
                <span class="text-[12px] font-semibold text-stone-700">
                    Soal {{ aktif + 1 }} / {{ soal.length }}
                </span>
            </div>

            <!-- Grid nomor soal: chip tappable, hijau bila sudah dijawab -->
            <MdCard :padded="false">
                <div class="grid grid-cols-6 gap-2 p-3">
                    <button
                        v-for="(s, i) in soal"
                        :key="s.id"
                        type="button"
                        class="flex h-10 items-center justify-center rounded-xl border text-[13px] font-bold transition active:scale-95"
                        :class="i === aktif
                            ? 'border-[var(--spmb-primary)] bg-[var(--spmb-primary)] text-white'
                            : sudahDijawab(s)
                                ? 'border-[var(--spmb-primary)]/30 bg-[var(--spmb-primary)]/8 text-[var(--spmb-primary)]'
                                : 'border-stone-200 bg-white text-stone-600'"
                        @click="keNomor(i)"
                    >
                        {{ s.nomor }}
                    </button>
                </div>
            </MdCard>

            <!-- Soal aktif -->
            <MdCard v-if="soalAktif" :key="soalAktif.id">
                <div class="text-[12px] font-bold uppercase tracking-wide text-[var(--spmb-primary)]">
                    Soal {{ soalAktif.nomor }}
                </div>
                <p class="mt-1.5 whitespace-pre-line text-[15px] leading-relaxed text-stone-900">
                    {{ soalAktif.pertanyaan }}
                </p>

                <!-- Pilihan ganda: baris besar tappable -->
                <div v-if="soalAktif.tipe === 'pg'" class="mt-4 space-y-2.5">
                    <label
                        v-for="(teks, huruf) in soalAktif.pilihan"
                        :key="huruf"
                        class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition active:scale-[0.99]"
                        :class="jawabanLokal[soalAktif.id] === huruf
                            ? 'border-[var(--spmb-primary)] bg-[var(--spmb-primary)]/8'
                            : 'border-stone-200 active:bg-stone-50'"
                    >
                        <input
                            type="radio"
                            :name="`soal-${soalAktif.id}`"
                            :value="huruf"
                            :checked="jawabanLokal[soalAktif.id] === huruf"
                            class="sr-only"
                            @change="pilih(soalAktif.id, huruf)"
                        >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-[13px] font-bold"
                            :class="jawabanLokal[soalAktif.id] === huruf
                                ? 'border-[var(--spmb-primary)] bg-[var(--spmb-primary)] text-white'
                                : 'border-stone-300 text-stone-500'"
                        >
                            {{ huruf }}
                        </span>
                        <span class="text-[14px] leading-snug text-stone-800">{{ teks }}</span>
                    </label>
                </div>

                <!-- Esai -->
                <textarea
                    v-else
                    v-model="jawabanLokal[soalAktif.id]"
                    rows="6"
                    class="mt-4 w-full rounded-xl border border-stone-300 px-4 py-3 text-[14px] focus:border-[var(--spmb-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--spmb-primary)]"
                    placeholder="Tulis jawaban Anda…"
                    @blur="simpan(soalAktif.id)"
                />

                <!-- Navigasi sebelumnya / berikutnya -->
                <div class="mt-5 flex items-center gap-3">
                    <MdButton
                        variant="ghost"
                        :block="false"
                        :disabled="aktif === 0"
                        class="flex-1"
                        @click="sebelumnya"
                    >
                        ← Sebelumnya
                    </MdButton>
                    <MdButton
                        variant="tonal"
                        :block="false"
                        :disabled="aktif === soal.length - 1"
                        class="flex-1"
                        @click="berikutnya"
                    >
                        Berikutnya →
                    </MdButton>
                </div>
            </MdCard>

            <!-- Kirim jawaban -->
            <MdCard>
                <p class="text-[12px] leading-relaxed text-stone-500">
                    Periksa kembali sebelum mengirim — ujian tidak bisa diulang.
                </p>
                <MdButton variant="primary" class="mt-3" @click="selesai">
                    Kirim Jawaban
                </MdButton>
            </MdCard>
        </div>
    </SpmbLayout>
</template>
