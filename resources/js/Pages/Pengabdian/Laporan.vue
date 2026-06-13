<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, reactive } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, IconCircle, EmptyState, BottomSheet, UiButton, UiField, UiInput } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    userName: { type: String, default: 'Santri Pengabdian' },
    kegiatanPilihan: { type: Array, default: () => [] },
    laporan: { type: Array, default: () => [] },
})

// Salinan lokal — laporan baru optimistic (tidak POST ke server).
const daftar = reactive(props.laporan.map((l) => ({ ...l })))

const statusMap = {
    draft: { status: 'info', label: 'Draft', icon: 'document', tone: 'slate' },
    menunggu: { status: 'menunggu', label: 'Terkirim', icon: 'clock', tone: 'amber' },
    terverifikasi: { status: 'terverifikasi', label: 'Diverifikasi', icon: 'check-circle', tone: 'emerald' },
}

const sheetOpen = ref(false)
const form = reactive({ kegiatan_id: '', judul: '', ringkasan: '' })

const toast = ref('')
let toastTimer = null

const selectClass = 'w-full rounded-2xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]'

function buatLaporan() {
    form.kegiatan_id = ''
    form.judul = ''
    form.ringkasan = ''
    sheetOpen.value = true
}

function simpan() {
    const keg = props.kegiatanPilihan.find((k) => String(k.id) === String(form.kegiatan_id))
    daftar.unshift({
        id: Date.now(),
        judul: form.judul || 'Laporan Khidmah',
        tanggal: 'Hari ini',
        kegiatan: keg?.judul || 'Khidmah',
        status: 'menunggu',
        ringkasan: form.ringkasan || '—',
    })
    sheetOpen.value = false
    showToast('Laporan terkirim ke pembimbing.')
}

function showToast(msg) {
    toast.value = msg
    if (toastTimer) clearTimeout(toastTimer)
    toastTimer = setTimeout(() => (toast.value = ''), 3000)
}
</script>

<template>
    <Head title="Laporan Khidmah" />

    <AppShell :nav-items="navigation" brand-name="Pengabdian Santri">
        <div class="space-y-5">
            <div class="flex items-end justify-between gap-3">
                <PageHeader kicker="Dokumentasi" title="Laporan Khidmah" subtitle="Catat hasil setiap kegiatan" />
                <UiButton variant="primary" size="sm" icon-left="plus" @click="buatLaporan">Buat</UiButton>
            </div>

            <SectionCard flush>
                <div v-if="daftar.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="lap in daftar"
                        :key="lap.id"
                        :title="lap.judul"
                        :subtitle="`${lap.tanggal} · ${lap.kegiatan}`"
                    >
                        <template #leading>
                            <IconCircle :icon="statusMap[lap.status]?.icon || 'document'" :tone="statusMap[lap.status]?.tone || 'slate'" size="md" />
                        </template>
                        <template #trailing>
                            <StatusBadge :status="statusMap[lap.status]?.status || 'info'" size="sm" :label="statusMap[lap.status]?.label" />
                        </template>
                        <p class="text-sm leading-6 text-slate-500">{{ lap.ringkasan }}</p>
                    </ListRow>
                </div>
                <EmptyState
                    v-else
                    icon="document"
                    title="Belum ada laporan"
                    message="Tekan tombol Buat untuk menulis laporan khidmah pertamamu."
                />
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" title="Buat Laporan">
            <form class="space-y-4" @submit.prevent="simpan">
                <UiField label="Kegiatan" required>
                    <template #default="{ fieldId }">
                        <select :id="fieldId" v-model="form.kegiatan_id" :class="selectClass" required>
                            <option value="">— Pilih Kegiatan —</option>
                            <option v-for="k in kegiatanPilihan" :key="k.id" :value="k.id">{{ k.judul }}</option>
                        </select>
                    </template>
                </UiField>

                <UiField label="Judul Laporan" required>
                    <template #default="{ fieldId }">
                        <UiInput :id="fieldId" v-model="form.judul" placeholder="mis. Kebersihan Masjid Jami" />
                    </template>
                </UiField>

                <UiField label="Ringkasan" hint="Ceritakan apa yang dikerjakan dan kendalanya">
                    <template #default="{ fieldId }">
                        <UiInput :id="fieldId" v-model="form.ringkasan" type="textarea" :rows="4" placeholder="Tuliskan ringkasan kegiatan..." />
                    </template>
                </UiField>
            </form>

            <template #footer>
                <UiButton variant="primary" block icon-left="upload" @click="simpan">Kirim Laporan</UiButton>
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
