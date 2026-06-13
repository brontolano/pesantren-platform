<script setup>
import { Head } from '@inertiajs/vue3'
import { reactive, ref, computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, IconCircle, EmptyState, UiButton, UiField, UiInput, BottomSheet } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
    riwayat: { type: Array, default: () => [] },
})

const statusLabel = { menunggu: 'Menunggu', terverifikasi: 'Disetujui', ditolak: 'Ditolak' }

const selectClass = 'w-full rounded-2xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]'

const form = reactive({
    jenis: '',
    alasan: '',
    tanggal: '',
})

const sheetOpen = ref(false)
const terkirim = ref(false)

const bisaKirim = computed(() => form.jenis && form.alasan.trim())

// Optimistic: tutup sheet & tampilkan sukses, tanpa POST ke server.
function kirim() {
    sheetOpen.value = false
    terkirim.value = true
    setTimeout(() => (terkirim.value = false), 3000)
    form.jenis = ''
    form.alasan = ''
    form.tanggal = ''
}
</script>

<template>
    <Head title="Perizinan" />

    <AppShell :nav-items="navigation" brand-name="Aplikasi Santri">
        <div class="space-y-5">
            <PageHeader kicker="Santri" title="Izin" subtitle="Riwayat & pengajuan izin" />

            <UiButton variant="primary" block icon-left="plus" @click="sheetOpen = true">
                Ajukan Izin
            </UiButton>

            <p
                v-if="terkirim"
                class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-100"
            >
                Pengajuan izin terkirim, menunggu verifikasi pengasuh.
            </p>

            <SectionCard kicker="Riwayat" title="Izin Saya" flush>
                <EmptyState
                    v-if="!riwayat.length"
                    icon="document"
                    title="Belum ada izin"
                    message="Pengajuan izin yang kamu buat akan tampil di sini."
                />
                <div v-else class="divide-y divide-slate-100">
                    <ListRow
                        v-for="r in riwayat"
                        :key="r.id"
                        :title="r.jenis"
                        :subtitle="r.alasan"
                    >
                        <template #leading>
                            <IconCircle icon="document" tone="amber" size="md" />
                        </template>
                        <template #trailing>
                            <div class="flex flex-col items-end gap-1.5">
                                <StatusBadge :status="r.status" :label="statusLabel[r.status]" size="sm" />
                                <span class="text-xs font-medium text-slate-400">{{ r.tanggal }}</span>
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Data peragaan. Izin pulang/sakit perlu persetujuan pengasuh kamar.
            </p>
        </div>

        <BottomSheet v-model="sheetOpen" title="Ajukan Izin">
            <form class="space-y-4" @submit.prevent="kirim">
                <UiField label="Jenis Izin" required>
                    <template #default="{ fieldId }">
                        <select :id="fieldId" v-model="form.jenis" :class="selectClass" required>
                            <option value="">— Pilih Jenis —</option>
                            <option v-for="j in jenisOptions" :key="j.value" :value="j.value">{{ j.label }}</option>
                        </select>
                    </template>
                </UiField>

                <UiField label="Tanggal" hint="Perkiraan tanggal izin">
                    <template #default="{ fieldId }">
                        <UiInput :id="fieldId" v-model="form.tanggal" type="date" />
                    </template>
                </UiField>

                <UiField label="Alasan" required>
                    <template #default="{ fieldId }">
                        <UiInput :id="fieldId" v-model="form.alasan" type="textarea" :rows="3" placeholder="mis. Menghadiri acara keluarga di rumah" />
                    </template>
                </UiField>
            </form>
            <template #footer>
                <div class="flex gap-3">
                    <UiButton variant="secondary" block @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" block icon-left="check" :disabled="!bisaKirim" @click="kirim">Kirim</UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
