<script setup>
import { Head, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import { AppShell, PageHeader, UiField, UiInput, UiButton, SegmentedControl, BottomSheet } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: '' },
    santriOptions: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
})

const form = reactive({
    santri_id: '',
    jenis: props.jenisOptions[0]?.value ?? 'kedisiplinan',
    kronologi: '',
})

const selectClass =
    'w-full rounded-2xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]'

const konfirmasi = ref(false)
const tersimpan = ref(false)
const menyimpan = ref(false)
const errorPesan = ref('')

const namaSantri = () =>
    props.santriOptions.find((s) => String(s.id) === String(form.santri_id))?.nama ?? '—'
const labelJenis = () =>
    props.jenisOptions.find((j) => j.value === form.jenis)?.label ?? form.jenis

function tinjau() {
    if (!form.santri_id || !form.kronologi.trim()) return
    konfirmasi.value = true
}

function simpan() {
    menyimpan.value = true
    errorPesan.value = ''
    router.post(
        '/pengasuhan/disiplin/catat',
        {
            santri_id: form.santri_id,
            jenis_pelanggaran_id: form.jenis,
            kronologi: form.kronologi,
        },
        {
            onSuccess: () => {
                konfirmasi.value = false
                tersimpan.value = true
                setTimeout(() => {
                    router.visit('/pengasuhan/disiplin')
                }, 1200)
            },
            onError: (errors) => {
                errorPesan.value = Object.values(errors)[0] ?? 'Gagal menyimpan, coba lagi.'
            },
            onFinish: () => {
                menyimpan.value = false
            },
        },
    )
}
</script>

<template>
    <Head title="Catat Pelanggaran" />

    <AppShell :nav-items="navigation" brand-name="Kepengasuhan">
        <form class="space-y-4" @submit.prevent="tinjau">
            <PageHeader kicker="Kedisiplinan" title="Catat Pelanggaran" subtitle="Rekam kejadian santri binaan" />

            <UiField label="Santri" required>
                <template #default="{ fieldId }">
                    <select :id="fieldId" v-model="form.santri_id" :class="selectClass" required>
                        <option value="">— Pilih Santri —</option>
                        <option v-for="s in santriOptions" :key="s.id" :value="s.id">{{ s.nama }}</option>
                    </select>
                </template>
            </UiField>

            <UiField label="Jenis Pelanggaran">
                <SegmentedControl v-model="form.jenis" :options="jenisOptions" />
            </UiField>

            <UiField label="Kronologi" required hint="Uraikan kejadian secara objektif">
                <template #default="{ fieldId }">
                    <UiInput :id="fieldId" v-model="form.kronologi" type="textarea" :rows="4" placeholder="mis. Tidak mengikuti shalat Maghrib berjamaah tanpa izin." />
                </template>
            </UiField>

            <UiButton type="submit" variant="primary" block icon-left="check" :disabled="!form.santri_id || !form.kronologi.trim()">
                Tinjau & Simpan
            </UiButton>

            <p v-if="tersimpan" class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-100">
                Pelanggaran tercatat. Mengarahkan ke rekap disiplin...
            </p>
            <p v-if="errorPesan" class="rounded-2xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 ring-1 ring-inset ring-rose-100">
                {{ errorPesan }}
            </p>
        </form>

        <BottomSheet v-model="konfirmasi" title="Konfirmasi Pencatatan">
            <div class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Santri</span>
                    <span class="font-semibold text-slate-900">{{ namaSantri() }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Jenis</span>
                    <span class="font-semibold text-slate-900">{{ labelJenis() }}</span>
                </div>
                <div>
                    <p class="text-slate-500">Kronologi</p>
                    <p class="mt-1 rounded-2xl bg-slate-50 px-3 py-2 text-slate-700 ring-1 ring-inset ring-slate-100">{{ form.kronologi }}</p>
                </div>
            </div>
            <template #footer>
                <div class="flex gap-3">
                    <UiButton type="button" variant="secondary" block @click="konfirmasi = false">Batal</UiButton>
                    <UiButton type="button" variant="primary" block icon-left="check" :loading="menyimpan" @click="simpan">Simpan</UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
