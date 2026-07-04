<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, UiField, UiInput, UiButton, SegmentedControl, BottomSheet } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    santriOptions: { type: Array, default: () => [] },
})

const form = useForm({
    santri_id: '',
    keluhan: '',
    tindakan: '',
    status: 'pulang',
})

const statusOptions = [
    { value: 'pulang', label: 'Pulang', icon: 'check-circle' },
    { value: 'istirahat', label: 'Istirahat', icon: 'clock' },
    { value: 'rujuk', label: 'Rujuk', icon: 'inbox' },
]

const selectClass = 'w-full rounded-2xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]'

const sheetOpen = ref(false)

const santriLabel = computed(
    () => props.santriOptions.find((s) => s.value === form.santri_id)?.label ?? '-',
)
const statusLabel = computed(
    () => statusOptions.find((s) => s.value === form.status)?.label ?? '-',
)
const bisaSimpan = computed(() => form.santri_id && form.keluhan.trim())

function konfirmasi() {
    if (!bisaSimpan.value) return
    sheetOpen.value = true
}

function simpan() {
    form.post('/uks/kunjungan/catat', {
        preserveScroll: true,
        onSuccess: () => {
            sheetOpen.value = false
            form.reset()
        },
        onError: () => {
            sheetOpen.value = false
        },
    })
}
</script>

<template>
    <Head title="Catat Kunjungan" />

    <AppShell :nav-items="navigation" brand-name="Kesehatan UKS">
        <form class="space-y-4" @submit.prevent="konfirmasi">
            <PageHeader kicker="UKS" title="Catat Kunjungan" subtitle="Rekam keluhan & tindakan santri" />

            <UiField label="Santri" required>
                <template #default="{ fieldId }">
                    <select :id="fieldId" v-model="form.santri_id" :class="selectClass" required>
                        <option value="">— Pilih Santri —</option>
                        <option v-for="s in santriOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </template>
            </UiField>

            <UiField label="Keluhan" required>
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.keluhan" type="textarea" :rows="2" placeholder="mis. Demam sejak semalam, pusing" />
                </template>
            </UiField>

            <UiField label="Tindakan" hint="Obat diberikan / perawatan">
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.tindakan" type="textarea" :rows="2" placeholder="mis. Paracetamol 1 tablet, kompres, istirahat" />
                </template>
            </UiField>

            <UiField label="Status">
                <SegmentedControl v-model="form.status" :options="statusOptions" />
            </UiField>

            <UiButton type="submit" variant="primary" block icon-left="check" :disabled="!bisaSimpan || form.processing">
                Simpan Kunjungan
            </UiButton>

            <p
                v-if="form.recentlySuccessful"
                class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-100"
            >
                Kunjungan berhasil dicatat.
            </p>
        </form>

        <BottomSheet v-model="sheetOpen" title="Simpan kunjungan?">
            <div class="space-y-2 text-sm leading-6 text-slate-600">
                <p><span class="font-semibold text-slate-500">Santri:</span> <span class="font-bold text-slate-900">{{ santriLabel }}</span></p>
                <p><span class="font-semibold text-slate-500">Keluhan:</span> {{ form.keluhan }}</p>
                <p v-if="form.tindakan"><span class="font-semibold text-slate-500">Tindakan:</span> {{ form.tindakan }}</p>
                <p><span class="font-semibold text-slate-500">Status:</span> {{ statusLabel }}</p>
            </div>
            <template #footer>
                <div class="flex gap-3">
                    <UiButton variant="secondary" block @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" block icon-left="check" :disabled="form.processing" @click="simpan">Ya, Simpan</UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
