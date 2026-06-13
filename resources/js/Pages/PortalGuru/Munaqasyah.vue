<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, UiCard, UiField, UiButton, SegmentedControl, EmptyState } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    santri: { type: Array, default: () => [] },
    periode: { type: Array, default: () => [] },
    istilah: { type: Object, default: () => ({}) },
})

function freshForm() {
    return {
        santri_id: '',
        juz_diuji: '',
        hasil: 'lulus',
        nilai: null,
        tanggal: new Date().toISOString().slice(0, 10),
        periode_akademik_id: null,
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

    router.post('/guru/munaqasyah', { ...form.value }, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'Hasil munaqasyah tercatat.'
            form.value = freshForm()
        },
        onError: () => {
            errorMessage.value = 'Periksa kembali isian munaqasyah.'
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
            <title>Munaqasyah</title>
        </Head>

        <PageHeader kicker="Tahfidz Lanjutan" title="Catat Munaqasyah" />

        <EmptyState
            v-if="santri.length === 0"
            icon="user"
            :title="`Belum ada ${istilah.santri || 'santri'} binaan`"
            :message="`Tambahkan anggota ${istilah.halaqah || 'halaqah'} dari panel admin.`"
        />

        <SectionCard v-else>
            <form class="space-y-4" @submit.prevent="submit()">
                <UiField label="Santri">
                    <template #default="{ fieldId }">
                        <select :id="fieldId" v-model="form.santri_id" required class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]">
                            <option value="" disabled>Pilih santri…</option>
                            <option v-for="s in santri" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                        </select>
                    </template>
                </UiField>

                <UiField label="Juz diuji">
                    <template #default="{ fieldId }">
                        <input :id="fieldId" v-model="form.juz_diuji" type="text" required maxlength="255" placeholder="cth. Juz 1-10" class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]">
                    </template>
                </UiField>

                <UiField label="Hasil">
                    <template #default>
                        <SegmentedControl
                            v-model="form.hasil"
                            :options="[
                                { value: 'lulus', label: 'Lulus', icon: 'check-circle' },
                                { value: 'mengulang', label: 'Mengulang', icon: 'clock' },
                            ]"
                        />
                    </template>
                </UiField>

                <div class="grid grid-cols-2 gap-3">
                    <UiField label="Nilai (0–100)">
                        <template #default="{ fieldId }">
                            <input :id="fieldId" v-model.number="form.nilai" type="number" min="0" max="100" step="0.01" class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]">
                        </template>
                    </UiField>
                    <UiField label="Tanggal">
                        <template #default="{ fieldId }">
                            <input :id="fieldId" v-model="form.tanggal" type="date" required class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium tabular-nums text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]">
                        </template>
                    </UiField>
                </div>

                <UiField label="Periode akademik">
                    <template #default="{ fieldId }">
                        <select :id="fieldId" v-model="form.periode_akademik_id" class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]">
                            <option :value="null">— Tanpa periode —</option>
                            <option v-for="p in periode" :key="p.id" :value="p.id">{{ p.nama }}</option>
                        </select>
                    </template>
                </UiField>

                <p v-if="errorMessage" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">{{ errorMessage }}</p>
                <p v-if="successMessage" class="rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">{{ successMessage }}</p>

                <UiButton type="submit" variant="primary" size="lg" block :loading="saving" :disabled="saving">
                    {{ saving ? 'Menyimpan…' : 'Catat Munaqasyah' }}
                </UiButton>
            </form>
        </SectionCard>
    </div>
</template>
