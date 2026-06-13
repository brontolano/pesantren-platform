<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, ListRow, UiField, UiButton, SegmentedControl, UiIcon } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    kajian: { type: Object, required: true },
    peserta: { type: Array, default: () => [] },
    riwayatKelompok: { type: Array, default: () => [] },
    riwayatPerSantri: { type: Object, default: () => ({}) },
})

const isSorogan = computed(() => props.kajian.metode === 'sorogan')

const form = ref({
    santri_id: null,
    posisi: '',
    status: 'lanjut',
    catatan: '',
})
const saving = ref(false)
const savedAt = ref(null)
const errorMessage = ref(null)

const riwayatTerpilih = computed(() => {
    if (!isSorogan.value) {
        return props.riwayatKelompok
    }

    return form.value.santri_id ? (props.riwayatPerSantri[form.value.santri_id] || []) : []
})

function submit() {
    saving.value = true
    errorMessage.value = null

    router.post(`/guru/kitab/${props.kajian.id}`, {
        santri_id: isSorogan.value ? form.value.santri_id : null,
        posisi: form.value.posisi,
        status: form.value.status,
        catatan: form.value.catatan || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            savedAt.value = new Date().toLocaleTimeString()
            form.value.posisi = ''
            form.value.catatan = ''
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors)[0] || 'Data tidak valid.'
        },
        onFinish: () => {
            saving.value = false
        },
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Capaian Kitab</title>
        </Head>

        <PageHeader
            :kicker="`Capaian Kitab · ${kajian.metode}`"
            :title="kajian.kitab"
            :subtitle="kajian.kelompok || '—'"
        />

        <SectionCard>
            <div class="space-y-4">
                <UiField v-if="isSorogan" label="Santri">
                    <template #default="{ fieldId }">
                        <select
                            :id="fieldId"
                            v-model="form.santri_id"
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        >
                            <option :value="null" disabled>— pilih santri —</option>
                            <option v-for="s in peserta" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis }})</option>
                        </select>
                    </template>
                </UiField>
                <p v-else class="flex items-start gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 ring-1 ring-inset ring-amber-100">
                    <UiIcon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>Satu update untuk SEMUA peserta ({{ peserta.length }} santri) — posisi kelompok.</span>
                </p>

                <UiField label="Posisi (halaman/bab)">
                    <template #default="{ fieldId }">
                        <input
                            :id="fieldId"
                            v-model="form.posisi"
                            type="text"
                            placeholder='Misal: "Bab Thaharah hal. 12"'
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        >
                    </template>
                </UiField>

                <UiField label="Status">
                    <template #default>
                        <SegmentedControl
                            v-model="form.status"
                            :options="[
                                { value: 'lanjut', label: 'Lanjut', icon: 'arrow-up-right' },
                                { value: 'ulang', label: 'Ulang', icon: 'clock' },
                                { value: 'khatam', label: 'Khatam', icon: 'check-circle' },
                            ]"
                        />
                    </template>
                </UiField>

                <UiField label="Catatan (opsional)">
                    <template #default="{ fieldId }">
                        <textarea
                            :id="fieldId"
                            v-model="form.catatan"
                            rows="2"
                            placeholder="Catatan (opsional)"
                            class="w-full rounded-2xl bg-white px-4 py-3 text-sm font-medium leading-6 text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]"
                        ></textarea>
                    </template>
                </UiField>

                <p v-if="errorMessage" class="rounded-2xl bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700">{{ errorMessage }}</p>
                <p v-if="savedAt" class="rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
                    Capaian tersimpan {{ savedAt }}.
                </p>

                <UiButton
                    type="button"
                    variant="primary"
                    size="lg"
                    block
                    :loading="saving"
                    :disabled="saving || (isSorogan && !form.santri_id)"
                    @click="submit()"
                >
                    {{ saving ? 'Menyimpan…' : 'Simpan Capaian' }}
                </UiButton>
            </div>
        </SectionCard>

        <SectionCard v-if="riwayatTerpilih.length > 0" kicker="Riwayat" title="Riwayat Terakhir" flush>
            <div class="divide-y divide-slate-100">
                <ListRow
                    v-for="(r, i) in riwayatTerpilih"
                    :key="i"
                    :title="r.posisi"
                >
                    <template #subtitle>
                        {{ r.tanggal }}<span v-if="r.catatan"> · {{ r.catatan }}</span>
                    </template>
                    <template #trailing>
                        <span
                            class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider ring-1 ring-inset"
                            :class="{
                                'bg-emerald-50 text-emerald-700 ring-emerald-100': r.status === 'lanjut',
                                'bg-amber-50 text-amber-700 ring-amber-100': r.status === 'ulang',
                                'bg-sky-50 text-sky-700 ring-sky-100': r.status === 'khatam',
                            }"
                        >
                            {{ r.status }}
                        </span>
                    </template>
                </ListRow>
            </div>
        </SectionCard>
    </div>
</template>
