<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { AppShell, PageHeader, UiCard, UiField, UiInput, UiButton, StatusBadge, IconCircle, EmptyState, BottomSheet } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    nis: { type: String, default: '' },
    santriCard: { type: Object, default: null },
})

const nisInput = ref(props.nis ?? '')

const STATUS_META = {
    hijau: { label: 'Izin aktif', tone: 'emerald' },
    kuning: { label: 'Izin terlambat kembali', tone: 'amber' },
    merah: { label: 'Tanpa izin aktif', tone: 'rose' },
}

function cek() {
    router.get('/keamanan/gerbang', { nis: nisInput.value.trim() }, { preserveState: true, replace: true })
}

function reset() {
    nisInput.value = ''
    router.get('/keamanan/gerbang', {}, { preserveState: true, replace: true })
}

const sheetOpen = ref(false)
const arahDipilih = ref('keluar')
const mengirim = ref(false)

function konfirmasiAksi(arah) {
    arahDipilih.value = arah
    sheetOpen.value = true
}

function catat() {
    if (!props.santriCard?.id) return
    mengirim.value = true
    router.post('/keamanan/gerbang/catat', {
        santri_id: props.santriCard.id,
        arah: arahDipilih.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            mengirim.value = false
            sheetOpen.value = false
        },
    })
}
</script>

<template>
    <Head title="Gerbang · Pos Keamanan" />

    <AppShell :nav-items="navigation" brand-name="Pos Keamanan">
        <div class="space-y-4">
            <PageHeader kicker="Pos Gerbang" title="Cek Gerbang" subtitle="Scan kartu / ketik NIS santri" />

            <UiCard padding="md">
                <UiField label="Nomor Induk Santri (NIS)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            v-model="nisInput"
                            type="text"
                            inputmode="numeric"
                            placeholder="Scan atau ketik NIS…"
                            :aria-describedby="describedby"
                            @keyup.enter="cek"
                        />
                    </template>
                </UiField>
                <div class="mt-3 flex gap-2">
                    <UiButton variant="primary" icon-left="qr" block @click="cek">Cek Santri</UiButton>
                    <UiButton v-if="santriCard" variant="ghost" icon-left="x-circle" @click="reset">Reset</UiButton>
                </div>
            </UiCard>

            <EmptyState
                v-if="santriCard?.tidak_ditemukan"
                icon="info"
                title="NIS tidak ditemukan"
                message="Periksa kembali nomor induk santri."
            />

            <UiCard v-else-if="santriCard" padding="md">
                <div class="flex items-start gap-3">
                    <IconCircle icon="user" :tone="STATUS_META[santriCard.status]?.tone ?? 'slate'" size="lg" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-jost text-[17px] font-bold text-slate-900">{{ santriCard.nama }}</p>
                        <p class="text-sm text-slate-500">NIS {{ santriCard.nis }} · {{ santriCard.kelas }}</p>
                        <div class="mt-2">
                            <StatusBadge :status="santriCard.status" :label="STATUS_META[santriCard.status]?.label" size="sm" />
                        </div>
                    </div>
                </div>

                <div v-if="santriCard.izin" class="mt-3 rounded-2xl bg-blue-50 px-3.5 py-2.5 text-xs text-blue-700 ring-1 ring-inset ring-blue-100">
                    Izin {{ santriCard.izin.jenis_izin }}: {{ santriCard.izin.tanggal_mulai }} – {{ santriCard.izin.tanggal_selesai }}
                </div>

                <div class="mt-3 flex gap-2">
                    <UiButton variant="secondary" icon-left="arrow-up-right" block @click="konfirmasiAksi('keluar')">
                        Catat Keluar
                    </UiButton>
                    <UiButton variant="primary" icon-left="arrow-down-left" block @click="konfirmasiAksi('masuk')">
                        Catat Masuk
                    </UiButton>
                </div>
            </UiCard>

            <EmptyState
                v-else
                icon="qr"
                title="Belum ada santri"
                message="Scan kartu atau masukkan NIS lalu tekan Cek Santri."
            />
        </div>

        <BottomSheet v-model="sheetOpen" :title="arahDipilih === 'keluar' ? 'Catat Keluar' : 'Catat Masuk'">
            <div v-if="santriCard" class="space-y-3">
                <div class="flex items-center gap-3">
                    <IconCircle icon="user" tone="emerald" />
                    <div class="min-w-0">
                        <p class="truncate font-jost text-[15px] font-bold text-slate-900">{{ santriCard.nama }}</p>
                        <p class="text-xs text-slate-500">NIS {{ santriCard.nis }} · {{ santriCard.kelas }}</p>
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-slate-600">
                    Catat <strong>{{ arahDipilih === 'keluar' ? 'keluar' : 'masuk' }}</strong> untuk santri ini sekarang?
                </p>
                <p v-if="santriCard.status === 'merah' && arahDipilih === 'keluar'" class="rounded-2xl bg-rose-50 px-3.5 py-2.5 text-xs text-rose-700 ring-1 ring-inset ring-rose-100">
                    Perhatian: santri tidak memiliki izin keluar aktif — pengasuhan akan dinotifikasi.
                </p>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="mengirim" @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block :disabled="mengirim" @click="catat">Catat Sekarang</UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
