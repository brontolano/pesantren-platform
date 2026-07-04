<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { PageHeader, UiField, UiInput, UiButton, AppShell } from '@/Shared'

const props = defineProps({
    santriOptions: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const form = useForm({
    santri_id: '',
    tanggal: new Date().toISOString().slice(0, 10),
    topik: '',
    deskripsi: '',
    rekomendasi: '',
})

const selectClass =
    'w-full rounded-2xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]'

function simpan() {
    form.post('/konselor/catat')
}
</script>

<template>
    <Head title="Konselor — Catat Sesi" />

    <AppShell :nav-items="navigation" brand-name="Konselor">
        <form class="space-y-4" @submit.prevent="simpan">
            <PageHeader kicker="Konseling" title="Catat Sesi Baru" subtitle="Jadwalkan sesi konseling santri" />

            <UiField label="Santri" :error="form.errors.santri_id" required>
                <template #default="{ fieldId }">
                    <select :id="fieldId" v-model="form.santri_id" :class="selectClass" required>
                        <option value="">— Pilih Santri —</option>
                        <option v-for="s in santriOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </template>
            </UiField>

            <UiField label="Tanggal" :error="form.errors.tanggal" required>
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.tanggal" type="date" />
                </template>
            </UiField>

            <UiField label="Topik" :error="form.errors.topik" required>
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.topik" placeholder="mis. Kesulitan penyesuaian asrama" />
                </template>
            </UiField>

            <UiField label="Deskripsi" :error="form.errors.deskripsi">
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.deskripsi" type="textarea" :rows="3" placeholder="Uraikan permasalahan…" />
                </template>
            </UiField>

            <UiField label="Rekomendasi" :error="form.errors.rekomendasi">
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.rekomendasi" type="textarea" :rows="2" placeholder="Tindak lanjut yang disarankan…" />
                </template>
            </UiField>

            <UiButton type="submit" variant="primary" block icon-left="check" :disabled="form.processing">
                Simpan Sesi
            </UiButton>
        </form>
    </AppShell>
</template>
