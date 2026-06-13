<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, UiField, UiButton, EmptyState, UiIcon } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    santri: { type: Array, default: () => [] },
    istilah: { type: Object, default: () => ({}) },
})

function freshForm() {
    return {
        santri_id: '',
        rentang: '',
        nilai_kelancaran: null,
        nilai_tajwid: null,
        catatan: '',
        tanggal: new Date().toISOString().slice(0, 10),
        // Idempoten: satu UUID per entri — retry mengirim ref sama, server dedup.
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

    router.post('/guru/simaan', { ...form.value }, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = "Sima'an tercatat."
            form.value = freshForm()
        },
        onError: () => {
            errorMessage.value = "Periksa kembali isian sima'an."
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
            <title>Sima'an</title>
        </Head>

        <PageHeader kicker="Tahfidz Lanjutan" title="Catat Sima'an" />

        <EmptyState
            v-if="santri.length === 0"
            icon="book"
            :title="`Belum ada ${istilah.santri || 'santri'} binaan`"
            :message="`Tambahkan anggota ${istilah.halaqah || 'halaqah'} dari panel admin.`"
        />

        <SectionCard v-else>
            <form class="space-y-4" @submit.prevent="submit()">
                <UiField label="Santri" required>
                    <template #default="{ fieldId, invalid }">
                        <select
                            :id="fieldId"
                            v-model="form.santri_id"
                            required
                            :aria-invalid="invalid || undefined"
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        >
                            <option value="" disabled>Pilih santri…</option>
                            <option v-for="s in santri" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                        </select>
                    </template>
                </UiField>

                <UiField label="Rentang" required>
                    <template #default="{ fieldId, invalid }">
                        <input
                            :id="fieldId"
                            v-model="form.rentang"
                            type="text"
                            required
                            maxlength="255"
                            placeholder="cth. Juz 1-5 / Al-Baqarah 1-141"
                            :aria-invalid="invalid || undefined"
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        >
                    </template>
                </UiField>

                <div class="grid grid-cols-2 gap-3">
                    <UiField label="Kelancaran (0–100)">
                        <template #default="{ fieldId, invalid }">
                            <input
                                :id="fieldId"
                                v-model.number="form.nilai_kelancaran"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                max="100"
                                :aria-invalid="invalid || undefined"
                                class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                            >
                        </template>
                    </UiField>
                    <UiField label="Tajwid (0–100)">
                        <template #default="{ fieldId, invalid }">
                            <input
                                :id="fieldId"
                                v-model.number="form.nilai_tajwid"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                max="100"
                                :aria-invalid="invalid || undefined"
                                class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                            >
                        </template>
                    </UiField>
                </div>

                <UiField label="Tanggal" required>
                    <template #default="{ fieldId, invalid }">
                        <input
                            :id="fieldId"
                            v-model="form.tanggal"
                            type="date"
                            required
                            :aria-invalid="invalid || undefined"
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        >
                    </template>
                </UiField>

                <UiField label="Catatan">
                    <template #default="{ fieldId, invalid }">
                        <textarea
                            :id="fieldId"
                            v-model="form.catatan"
                            rows="2"
                            maxlength="500"
                            :aria-invalid="invalid || undefined"
                            class="w-full rounded-2xl bg-white px-4 py-3 text-sm font-medium leading-6 text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        />
                    </template>
                </UiField>

                <p v-if="errorMessage" class="flex items-start gap-2 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                    <UiIcon name="x-circle" size="18" class="mt-0.5 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </p>
                <p v-if="successMessage" class="flex items-start gap-2 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    <UiIcon name="check-circle" size="18" class="mt-0.5 shrink-0" />
                    <span>{{ successMessage }}</span>
                </p>

                <UiButton type="submit" variant="primary" size="lg" block :loading="saving" :disabled="saving">
                    {{ saving ? 'Menyimpan…' : "Catat Sima'an" }}
                </UiButton>
            </form>
        </SectionCard>
    </div>
</template>
