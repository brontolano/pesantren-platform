<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { AppShell, PageHeader, UiCard, UiField, UiInput, UiButton, StatusBadge, IconCircle, EmptyState, BottomSheet } from '@/Shared'

// Gerbang — scan/ketik NIS → cek → kartu santri + status + catat keluar/masuk.
// Optimistic: aksi catat hanya menampilkan konfirmasi & toast (tidak POST).
const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    direktori: { type: Object, default: () => ({}) },
})

const nis = ref('')
const kartu = ref(null)
const belumKetemu = ref(false)
const sheetOpen = ref(false)
const toast = ref('')

const aksiLabel = computed(() => (kartu.value?.posisi === 'luar' ? 'Catat Masuk' : 'Catat Keluar'))
const aksiIcon = computed(() => (kartu.value?.posisi === 'luar' ? 'arrow-down-left' : 'arrow-up-right'))

function cek() {
    const key = String(nis.value || '').trim()
    kartu.value = null
    belumKetemu.value = false
    if (!key) return
    const data = props.direktori[key]
    if (data) {
        kartu.value = { nis: key, ...data }
    } else {
        belumKetemu.value = true
    }
}

function konfirmasiAksi() {
    sheetOpen.value = true
}

function simpanOptimistic() {
    // DEMO: tidak ada POST — balik posisi secara optimistic + toast.
    const isMasuk = kartu.value.posisi === 'luar'
    kartu.value.posisi = isMasuk ? 'dalam' : 'luar'
    toast.value = `${isMasuk ? 'Masuk' : 'Keluar'} tercatat: ${kartu.value.nama} (${kartu.value.jam || 'sekarang'})`
    sheetOpen.value = false
    setTimeout(() => (toast.value = ''), 2600)
}

function reset() {
    nis.value = ''
    kartu.value = null
    belumKetemu.value = false
}
</script>

<template>
    <Head title="Gerbang · Pos Keamanan" />

    <AppShell :nav-items="navigation" brand-name="Pos Keamanan">
        <div class="space-y-4">
            <PageHeader kicker="Pos Gerbang" title="Cek Gerbang" subtitle="Scan kartu / ketik NIS santri" />

            <UiCard padding="md">
                <UiField label="Nomor Induk Santri (NIS)" hint="Contoh demo: 2024001, 2024002, 2024003">
                    <template #default="{ fieldId, describedby }">
                        <UiInput
                            :id="fieldId"
                            v-model="nis"
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
                    <UiButton v-if="kartu || belumKetemu" variant="ghost" icon-left="x-circle" @click="reset">Reset</UiButton>
                </div>
            </UiCard>

            <EmptyState
                v-if="belumKetemu"
                icon="info"
                title="NIS tidak ditemukan"
                message="Periksa kembali nomor induk atau gunakan NIS contoh."
            />

            <UiCard v-if="kartu" padding="md">
                <div class="flex items-start gap-3">
                    <IconCircle icon="user" :tone="kartu.status === 'ditolak' ? 'rose' : kartu.status === 'menunggu' ? 'amber' : 'emerald'" size="lg" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-jost text-[17px] font-bold text-slate-900">{{ kartu.nama }}</p>
                        <p class="text-sm text-slate-500">NIS {{ kartu.nis }} · {{ kartu.kelas }}</p>
                        <p class="mt-0.5 text-xs text-slate-400">{{ kartu.kamar }}</p>
                        <div class="mt-2">
                            <StatusBadge :status="kartu.status" :label="kartu.statusLabel" size="sm" />
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2 rounded-2xl bg-slate-50 px-3.5 py-2.5 text-xs text-slate-600 ring-1 ring-inset ring-slate-100">
                    <IconCircle :icon="kartu.posisi === 'luar' ? 'arrow-up-right' : 'check-circle'" :tone="kartu.posisi === 'luar' ? 'amber' : 'emerald'" size="sm" />
                    <span>
                        Posisi saat ini:
                        <strong class="font-jost">{{ kartu.posisi === 'luar' ? 'Di luar pesantren' : 'Di dalam pesantren' }}</strong>
                    </span>
                </div>

                <p v-if="kartu.catatan" class="mt-2 rounded-2xl bg-blue-50 px-3.5 py-2.5 text-xs text-blue-700 ring-1 ring-inset ring-blue-100">
                    {{ kartu.catatan }}
                </p>

                <UiButton
                    class="mt-3"
                    :variant="kartu.status === 'ditolak' ? 'danger' : 'primary'"
                    :icon-left="aksiIcon"
                    block
                    @click="konfirmasiAksi"
                >
                    {{ aksiLabel }}
                </UiButton>
            </UiCard>

            <EmptyState
                v-else-if="!belumKetemu"
                icon="qr"
                title="Belum ada santri"
                message="Scan kartu atau masukkan NIS lalu tekan Cek Santri."
            />
        </div>

        <BottomSheet v-model="sheetOpen" :title="aksiLabel">
            <div v-if="kartu" class="space-y-3">
                <div class="flex items-center gap-3">
                    <IconCircle icon="user" tone="emerald" />
                    <div class="min-w-0">
                        <p class="truncate font-jost text-[15px] font-bold text-slate-900">{{ kartu.nama }}</p>
                        <p class="text-xs text-slate-500">NIS {{ kartu.nis }} · {{ kartu.kelas }}</p>
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-slate-600">
                    Catat <strong>{{ aksiLabel.toLowerCase() }}</strong> untuk santri ini sekarang?
                </p>
                <p v-if="kartu.status === 'ditolak'" class="rounded-2xl bg-rose-50 px-3.5 py-2.5 text-xs text-rose-700 ring-1 ring-inset ring-rose-100">
                    Perhatian: santri tidak memiliki izin keluar tercatat.
                </p>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block @click="simpanOptimistic">Catat Sekarang</UiButton>
                </div>
            </template>
        </BottomSheet>

        <Transition name="toast-pop">
            <p v-if="toast" class="fixed inset-x-0 bottom-28 z-40 mx-auto w-full max-w-md px-6">
                <span class="block rounded-2xl bg-slate-900/90 px-4 py-3 text-sm font-medium text-white shadow-soft-lg backdrop-blur">
                    {{ toast }}
                </span>
            </p>
        </Transition>
    </AppShell>
</template>

<style scoped>
.toast-pop-enter-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.toast-pop-leave-active { transition: all 0.2s ease-in; }
.toast-pop-enter-from, .toast-pop-leave-to { opacity: 0; transform: translateY(12px); }
</style>
