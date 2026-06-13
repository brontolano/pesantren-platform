<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, reactive } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, SegmentedControl, IconCircle, BottomSheet, UiButton } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    userName: { type: String, default: 'Santri Pengabdian' },
    kegiatanHariIni: { type: Array, default: () => [] },
})

// Salinan lokal — presensi optimistic (tidak POST ke server).
const daftar = reactive(props.kegiatanHariIni.map((k) => ({ ...k })))

const statusOptions = [
    { value: 'hadir', label: 'Hadir', icon: 'check-circle' },
    { value: 'izin', label: 'Izin', icon: 'document' },
    { value: 'sakit', label: 'Sakit', icon: 'heart' },
    { value: 'alpa', label: 'Alpa', icon: 'x-circle' },
]
const labelStatus = { hadir: 'Hadir', izin: 'Izin', sakit: 'Sakit', alpa: 'Alpa' }
const toneStatus = { hadir: 'aktif', izin: 'info', sakit: 'menunggu', alpa: 'ditolak' }

const sheetOpen = ref(false)
const selected = ref(null)
const pilihan = ref('hadir')

const toast = ref('')
let toastTimer = null

function buka(item) {
    selected.value = item
    pilihan.value = item.status || 'hadir'
    sheetOpen.value = true
}

function kirim() {
    if (selected.value) {
        const row = daftar.find((d) => d.id === selected.value.id)
        if (row) row.status = pilihan.value // optimistic update
    }
    sheetOpen.value = false
    showToast(`Presensi "${labelStatus[pilihan.value]}" tersimpan.`)
}

function showToast(msg) {
    toast.value = msg
    if (toastTimer) clearTimeout(toastTimer)
    toastTimer = setTimeout(() => (toast.value = ''), 3000)
}
</script>

<template>
    <Head title="Presensi Khidmah" />

    <AppShell :nav-items="navigation" brand-name="Pengabdian Santri">
        <div class="space-y-5">
            <PageHeader kicker="Kehadiran" title="Presensi Khidmah" subtitle="Pilih kegiatan lalu tandai kehadiranmu" />

            <SectionCard flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="item in daftar"
                        :key="item.id"
                        :title="item.judul"
                        :subtitle="`${item.waktu} · ${item.lokasi}`"
                        chevron
                        @click="buka(item)"
                        class="cursor-pointer"
                    >
                        <template #leading>
                            <IconCircle icon="check-circle" tone="emerald" size="md" />
                        </template>
                        <template #trailing>
                            <StatusBadge
                                v-if="item.status"
                                :status="toneStatus[item.status]"
                                size="sm"
                                :label="labelStatus[item.status]"
                            />
                            <span v-else class="text-xs font-semibold text-slate-400">Belum diisi</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Tandai kehadiran sebelum atau saat kegiatan berlangsung. Pembimbing akan memverifikasi.
            </p>
        </div>

        <BottomSheet v-model="sheetOpen" :title="selected?.judul">
            <div v-if="selected" class="space-y-4">
                <p class="text-sm text-slate-500">{{ selected.waktu }} · {{ selected.lokasi }}</p>

                <div>
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">Status Kehadiran</p>
                    <SegmentedControl v-model="pilihan" :options="statusOptions" />
                </div>
            </div>

            <template #footer>
                <UiButton variant="primary" block icon-left="check" @click="kirim">Simpan Presensi</UiButton>
            </template>
        </BottomSheet>

        <Transition name="fade">
            <div
                v-if="toast"
                class="fixed inset-x-0 top-3 z-[60] mx-auto flex w-full max-w-md justify-center px-4"
            >
                <p class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-center text-sm font-semibold text-white shadow-soft-lg">
                    {{ toast }}
                </p>
            </div>
        </Transition>
    </AppShell>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
