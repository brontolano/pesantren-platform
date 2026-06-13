<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { UiCard, UiField, UiButton, SegmentedControl, UiIcon } from '@/Shared'

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
    <form @submit.prevent="submit()">
        <UiCard padding="md">
            <div class="space-y-4">
                <UiField label="Santri">
                    <template #default="{ fieldId }">
                        <select
                            :id="fieldId"
                            v-model="form.santri_id"
                            required
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        >
                            <option value="" disabled>Pilih santri…</option>
                            <option v-for="s in santri" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                        </select>
                    </template>
                </UiField>

                <UiField label="Surah">
                    <template #default="{ fieldId }">
                        <input
                            :id="fieldId"
                            v-model="form.surah"
                            type="text"
                            required
                            maxlength="100"
                            placeholder="cth. Al-Mulk"
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        >
                    </template>
                </UiField>

                <div class="grid grid-cols-2 gap-3">
                    <UiField label="Ayat awal">
                        <template #default="{ fieldId }">
                            <input
                                :id="fieldId"
                                v-model.number="form.ayat_awal"
                                type="number"
                                min="1"
                                max="286"
                                inputmode="numeric"
                                class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                            >
                        </template>
                    </UiField>
                    <UiField label="Ayat akhir">
                        <template #default="{ fieldId }">
                            <input
                                :id="fieldId"
                                v-model.number="form.ayat_akhir"
                                type="number"
                                min="1"
                                max="286"
                                inputmode="numeric"
                                class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                            >
                        </template>
                    </UiField>
                </div>

                <UiField label="Jenis setoran">
                    <SegmentedControl
                        v-model="form.jenis"
                        :options="[
                            { value: 'ziyadah', label: 'Ziyadah' },
                            { value: 'murajaah', label: 'Muraja\'ah' },
                        ]"
                    />
                </UiField>

                <UiField label="Nilai (0–100)">
                    <template #default="{ fieldId }">
                        <input
                            :id="fieldId"
                            v-model.number="form.nilai"
                            type="number"
                            min="0"
                            max="100"
                            step="0.5"
                            inputmode="decimal"
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        >
                    </template>
                </UiField>

                <UiField label="Catatan">
                    <template #default="{ fieldId }">
                        <textarea
                            :id="fieldId"
                            v-model="form.catatan"
                            rows="2"
                            maxlength="500"
                            class="w-full rounded-2xl bg-white px-4 py-3 text-sm font-medium leading-6 text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        />
                    </template>
                </UiField>

                <p
                    v-if="errorMessage"
                    class="flex items-start gap-2 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
                    role="alert"
                >
                    <UiIcon name="x-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </p>
                <p
                    v-if="successMessage"
                    class="flex items-start gap-2 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                >
                    <UiIcon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ successMessage }}</span>
                </p>

                <UiButton
                    type="submit"
                    variant="primary"
                    size="lg"
                    block
                    :loading="saving"
                    :disabled="saving"
                    icon-left="check"
                >
                    {{ saving ? 'Menyimpan…' : 'Catat Setoran' }}
                </UiButton>
            </div>
        </UiCard>
    </form>
</template>
