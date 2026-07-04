<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    AppShell, PageHeader, SectionCard, ListRow, IconCircle, StatusBadge,
    SegmentedControl, EmptyState, BottomSheet, UiField, UiInput, UiButton,
} from '@/Shared'

const props = defineProps({
    laporan: { type: Array, default: () => [] },
    statusAktif: { type: String, default: 'semua' },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])

const statusOptions = [
    { value: 'semua', label: 'Semua' },
    { value: 'masuk', label: 'Masuk' },
    { value: 'dikerjakan', label: 'Dikerjakan' },
    { value: 'selesai', label: 'Selesai' },
]

function pilihStatus(value) {
    router.get('/sarpras/kerusakan', { status: value }, { preserveState: true, replace: true })
}

const sheetOpen = ref(false)
const aktif = ref(null)
const statusBaru = ref('')
const biaya = ref('')
const catatan = ref('')

function buka(l) {
    aktif.value = l
    statusBaru.value = l.status
    biaya.value = ''
    catatan.value = ''
    sheetOpen.value = true
}

function simpan() {
    router.post(`/sarpras/kerusakan/${aktif.value.id}/status`, {
        status: statusBaru.value,
        biaya: biaya.value || null,
        catatan: catatan.value || null,
    }, {
        onSuccess: () => { sheetOpen.value = false },
    })
}
</script>

<template>
    <Head title="Sarpras — Kerusakan" />

    <AppShell :nav-items="navItems" brand-name="Sarpras">
        <div class="space-y-5">
            <PageHeader kicker="Sarpras" title="Laporan Kerusakan" subtitle="Tindak lanjuti laporan dari lapangan" />

            <SegmentedControl :model-value="statusAktif" :options="statusOptions" @update:model-value="pilihStatus" />

            <SectionCard flush>
                <div v-if="laporan.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="l in laporan"
                        :key="l.id"
                        :title="l.lokasi"
                        :subtitle="l.deskripsi"
                        chevron
                        role="button"
                        tabindex="0"
                        class="cursor-pointer"
                        @click="buka(l)"
                        @keydown.enter="buka(l)"
                    >
                        <template #leading>
                            <IconCircle icon="wrench" tone="amber" size="sm" />
                        </template>
                        <template #trailing>
                            <StatusBadge :status="l.status" size="sm" />
                        </template>
                    </ListRow>
                </div>
                <EmptyState v-else icon="wrench" title="Tidak ada laporan" message="Belum ada laporan kerusakan pada filter ini." />
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" :title="aktif?.lokasi">
            <div v-if="aktif" class="space-y-4">
                <UiField label="Status">
                    <template #default="{ fieldId }">
                        <select
                            :id="fieldId"
                            v-model="statusBaru"
                            class="w-full rounded-2xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200"
                        >
                            <option value="masuk">Masuk</option>
                            <option value="dikerjakan">Dikerjakan</option>
                            <option value="selesai">Selesai</option>
                        </select>
                    </template>
                </UiField>

                <UiField label="Biaya (opsional)">
                    <template #default="{ fieldId }">
                        <UiInput :id="fieldId" v-model="biaya" type="number" placeholder="0" />
                    </template>
                </UiField>

                <UiField label="Catatan (opsional)">
                    <template #default="{ fieldId }">
                        <UiInput :id="fieldId" v-model="catatan" type="textarea" :rows="3" />
                    </template>
                </UiField>
            </div>

            <template #footer>
                <div class="flex gap-3">
                    <UiButton variant="secondary" block @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" block icon-left="check" @click="simpan">Simpan</UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
