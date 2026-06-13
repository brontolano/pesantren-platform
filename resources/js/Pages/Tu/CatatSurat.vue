<script setup>
import { computed, ref } from 'vue'
import {
    AppShell, PageHeader, SectionCard, IconCircle, SegmentedControl,
    UiField, UiInput, UiButton, BottomSheet,
} from '@/Shared'

/**
 * CatatSurat — form surat keluar. Submit OPTIMISTIC (tidak POST ke server):
 * buka BottomSheet konfirmasi yang menampilkan nomor agenda auto, lalu toast
 * inline. Aman untuk demo: tidak mengubah data apa pun.
 */
const props = defineProps({
    userName: { type: String, default: 'Staf TU' },
    jenisOptions: { type: Array, default: () => [] },
    nomorBerikutnya: { type: String, default: 'KLR-2026-0119' },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])

const form = ref({
    tujuan: '',
    perihal: '',
    jenis: props.jenisOptions[0]?.value ?? 'Keterangan',
    tanggal: '',
})

const errors = ref({})
const sheetOpen = ref(false)
const toast = ref('')

function validasi() {
    const e = {}
    if (!form.value.tujuan.trim()) e.tujuan = 'Tujuan surat wajib diisi.'
    if (!form.value.perihal.trim()) e.perihal = 'Perihal wajib diisi.'
    errors.value = e
    return Object.keys(e).length === 0
}

function ajukan() {
    if (!validasi()) return
    sheetOpen.value = true
}

function selesai() {
    sheetOpen.value = false
    toast.value = `Surat tercatat dengan nomor agenda ${props.nomorBerikutnya}.`
    form.value = { tujuan: '', perihal: '', jenis: props.jenisOptions[0]?.value ?? 'Keterangan', tanggal: '' }
    setTimeout(() => (toast.value = ''), 3500)
}
</script>

<template>
    <AppShell :nav-items="navItems" brand-name="Tata Usaha">
        <div class="space-y-5">
            <PageHeader kicker="Persuratan" title="Catat Surat Keluar" subtitle="Nomor agenda dibuat otomatis" />

            <div
                v-if="toast"
                class="flex items-start gap-2.5 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-100"
                role="status"
            >
                <IconCircle icon="check-circle" tone="emerald" size="sm" />
                <span class="pt-1.5">{{ toast }}</span>
            </div>

            <SectionCard>
                <div class="space-y-4">
                    <div class="flex items-center justify-between rounded-2xl bg-slate-50/70 px-4 py-3 ring-1 ring-slate-100">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">No. Agenda</span>
                        <span class="font-jost text-sm font-bold tabular-nums text-emerald-700">{{ nomorBerikutnya }}</span>
                    </div>

                    <UiField label="Tujuan / Penerima" :error="errors.tujuan" required>
                        <template #default="{ fieldId, describedby, invalid }">
                            <UiInput
                                :id="fieldId"
                                :aria-describedby="describedby"
                                :invalid="invalid"
                                v-model="form.tujuan"
                                placeholder="mis. Kemenag Kab. Sumedang"
                            />
                        </template>
                    </UiField>

                    <UiField label="Perihal" :error="errors.perihal" required>
                        <template #default="{ fieldId, describedby, invalid }">
                            <UiInput
                                :id="fieldId"
                                :aria-describedby="describedby"
                                :invalid="invalid"
                                type="textarea"
                                :rows="3"
                                v-model="form.perihal"
                                placeholder="Ringkasan isi surat…"
                            />
                        </template>
                    </UiField>

                    <UiField label="Jenis surat">
                        <template #default>
                            <SegmentedControl v-model="form.jenis" :options="jenisOptions" />
                        </template>
                    </UiField>

                    <UiField label="Tanggal surat" hint="Kosongkan untuk pakai tanggal hari ini">
                        <template #default="{ fieldId, describedby }">
                            <UiInput
                                :id="fieldId"
                                :aria-describedby="describedby"
                                type="date"
                                v-model="form.tanggal"
                            />
                        </template>
                    </UiField>
                </div>
            </SectionCard>

            <UiButton variant="primary" block icon-left="check" @click="ajukan">
                Simpan & Beri Nomor Agenda
            </UiButton>
        </div>

        <!-- Konfirmasi optimistic -->
        <BottomSheet v-model="sheetOpen" title="Konfirmasi Pencatatan">
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <IconCircle icon="document" tone="emerald" variant="gradient" size="lg" />
                    <div>
                        <p class="text-sm font-bold text-slate-900">Surat akan dicatat</p>
                        <p class="text-xs text-slate-500">Nomor agenda otomatis: <span class="font-semibold text-emerald-700">{{ nomorBerikutnya }}</span></p>
                    </div>
                </div>
                <dl class="divide-y divide-slate-100 rounded-2xl bg-slate-50/60 px-4 ring-1 ring-slate-100">
                    <div class="flex items-start justify-between gap-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tujuan</dt>
                        <dd class="text-right text-sm font-medium text-slate-800">{{ form.tujuan }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Perihal</dt>
                        <dd class="text-right text-sm font-medium text-slate-800">{{ form.perihal }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis</dt>
                        <dd class="text-right text-sm font-medium text-slate-800">{{ form.jenis }}</dd>
                    </div>
                </dl>
            </div>
            <template #footer>
                <div class="flex gap-3">
                    <UiButton variant="secondary" block @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" block icon-left="check" @click="selesai">Catat Surat</UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
