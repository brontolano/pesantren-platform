<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    AppShell, PageHeader, SectionCard, IconCircle, SegmentedControl,
    UiField, UiInput, UiButton, BottomSheet,
} from '@/Shared'

/**
 * CatatSurat — form surat keluar. POST nyata ke tenant.tu.catat-surat.store
 * (TuController::simpanSurat → SuratService::catat, penomoran otomatis
 * race-safe). "Jenis surat" (Keterangan/Pengantar/dst) hanya label UI —
 * dikirim sebagai bagian keterangan krn model Surat.jenis sebenarnya
 * menyimpan arah (masuk/keluar), bukan kategori ini.
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
const mengirim = ref(false)

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
    mengirim.value = true
    router.post('/tu/catat-surat', {
        jenis: 'keluar',
        tanggal_surat: form.value.tanggal || new Date().toISOString().slice(0, 10),
        pihak: form.value.tujuan,
        perihal: form.value.perihal,
        keterangan: form.value.jenis,
    }, {
        onError: (e) => { errors.value = e },
        onFinish: () => {
            mengirim.value = false
            sheetOpen.value = false
        },
    })
}
</script>

<template>
    <AppShell :nav-items="navItems" brand-name="Tata Usaha">
        <div class="space-y-5">
            <PageHeader kicker="Persuratan" title="Catat Surat Keluar" subtitle="Nomor agenda dibuat otomatis" />

            <SectionCard>
                <div class="space-y-4">
                    <div class="flex items-center justify-between rounded-2xl bg-slate-50/70 px-4 py-3 ring-1 ring-slate-100">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">No. Agenda</span>
                        <span class="font-jost text-sm font-bold tabular-nums text-violet-700">{{ nomorBerikutnya }}</span>
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
                        <p class="text-xs text-slate-500">Nomor agenda otomatis: <span class="font-semibold text-violet-700">{{ nomorBerikutnya }}</span></p>
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
                    <UiButton variant="secondary" block :disabled="mengirim" @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" block icon-left="check" :disabled="mengirim" @click="selesai">Catat Surat</UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
