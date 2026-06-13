<script setup>
import { Head, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, UiCard, UiField, UiInput, UiButton, EmptyState, UiIcon } from '@/Shared'

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

        <PageHeader
            kicker="Koreksi Essay"
            title="Antrean Jawaban"
            subtitle="Beri skor 0..bobot per soal. Nilai final dihitung server (tertimbang bobot)."
        />

        <div v-if="pesan" class="flex items-start gap-2 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <UiIcon name="info" size="sm" class="mt-0.5 shrink-0" />
            <span>{{ pesan }}</span>
        </div>

        <EmptyState
            v-if="antrean.length === 0"
            icon="check-circle"
            tone="emerald"
            title="Tidak ada essay menunggu nilai"
            message="Semua jawaban essay sudah dikoreksi."
        />

        <div v-else class="space-y-4">
            <SectionCard
                v-for="a in antrean"
                :key="a.attempt_id"
                :title="a.santri"
                :subtitle="`NIS ${a.nis} · ${a.pelajaran} · ${a.sesi} · Nilai PG: ${a.nilai_pg ?? '—'}`"
            >
                <div class="space-y-3">
                    <UiCard
                        v-for="e in a.essay"
                        :key="e.soal_id"
                        padding="md"
                    >
                        <p class="text-xs font-semibold text-slate-700">{{ e.pertanyaan }}</p>
                        <p class="mt-2 whitespace-pre-wrap rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-800">{{ e.jawaban || '(tidak dijawab)' }}</p>
                        <div class="mt-3 max-w-[12rem]">
                            <UiField :label="`Skor (0–${e.bobot})`">
                                <UiInput
                                    v-model.number="skor[a.attempt_id][e.soal_id]"
                                    type="number"
                                    inputmode="decimal"
                                    :min="0"
                                    :max="e.bobot"
                                />
                            </UiField>
                        </div>
                    </UiCard>

                    <UiButton
                        type="button"
                        variant="primary"
                        block
                        :loading="sibuk === a.attempt_id"
                        :disabled="sibuk === a.attempt_id || a.essay.some((e) => skor[a.attempt_id][e.soal_id] === null || skor[a.attempt_id][e.soal_id] === '')"
                        @click="simpan(a)"
                    >{{ sibuk === a.attempt_id ? 'Menyimpan…' : 'Simpan Nilai' }}</UiButton>
                </div>
            </SectionCard>
        </div>
    </div>
</template>
