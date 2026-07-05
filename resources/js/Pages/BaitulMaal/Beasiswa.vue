<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, MoneyText, IconCircle, BottomSheet, UiButton, UiField, UiInput, UiSelect, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    beasiswa: { type: Array, default: () => [] },
    santriOptions: { type: Array, default: () => [] },
    sumberDana: { type: Array, default: () => [] },
})

const sheetOpen = ref(false)
const form = ref({ santri_id: '', sumber_dana_id: '', nominal_per_bulan: 0, mulai: '', selesai: null, keterangan: '' })
const menyimpan = ref(false)

function bukaBaru() {
    form.value = { santri_id: '', sumber_dana_id: '', nominal_per_bulan: 0, mulai: '', selesai: '', keterangan: '' }
    sheetOpen.value = true
}

function simpan() {
    menyimpan.value = true
    router.post('/baitul-maal/beasiswa', form.value, {
        preserveScroll: true,
        onFinish: () => { menyimpan.value = false; sheetOpen.value = false },
    })
}

function nonaktifkan(b) {
    router.post(`/baitul-maal/beasiswa/${b.id}`, { aktif: false }, { preserveScroll: true })
}
</script>

<template>
    <Head title="Beasiswa · Baitul Maal" />

    <AppShell :nav-items="navigation" brand-name="Baitul Maal">
        <div class="space-y-5">
            <PageHeader kicker="Baitul Maal" title="Beasiswa Santri" subtitle="Bantuan biaya bulanan">
                <template v-if="can.kelola" #right>
                    <UiButton variant="primary" size="sm" icon-left="add" @click="bukaBaru">Baru</UiButton>
                </template>
            </PageHeader>

            <SectionCard flush>
                <EmptyState v-if="!beasiswa.length" icon="user" title="Belum ada beasiswa" message="Catat beasiswa santri pertama." />
                <div v-else class="divide-y divide-slate-100">
                    <ListRow
                        v-for="b in beasiswa"
                        :key="b.id"
                        :title="b.santri"
                        :subtitle="`${b.mulai} — ${b.selesai || 'berjalan'}`"
                    >
                        <template #leading><IconCircle icon="user" tone="teal" /></template>
                        <template #trailing>
                            <MoneyText :amount="b.nominal_per_bulan" size="md" tone="emerald" />
                            <div class="mt-1 flex items-center gap-2">
                                <StatusBadge :status="b.aktif ? 'lunas' : 'ditolak'" :label="b.aktif ? 'Aktif' : 'Berakhir'" size="sm" />
                                <UiButton v-if="can.kelola && b.aktif" variant="ghost" size="sm" @click="nonaktifkan(b)">
                                    Akhiri
                                </UiButton>
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" title="Beasiswa Baru">
            <div class="space-y-3">
                <UiField label="Santri">
                    <template #default="{ fieldId }">
                        <UiSelect :id="fieldId" v-model="form.santri_id" required>
                            <option value="" disabled>Pilih santri…</option>
                            <option v-for="s in santriOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </UiSelect>
                    </template>
                </UiField>
                <UiField label="Sumber Dana">
                    <template #default="{ fieldId }">
                        <UiSelect :id="fieldId" v-model="form.sumber_dana_id" required>
                            <option value="" disabled>Pilih sumber dana…</option>
                            <option v-for="s in sumberDana" :key="s.id" :value="s.id">{{ s.kode_dana }} — {{ s.nama_sumber }}</option>
                        </UiSelect>
                    </template>
                </UiField>
                <UiField label="Nominal per Bulan (Rp)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model.number="form.nominal_per_bulan" type="number" min="0" :aria-describedby="describedby" />
                    </template>
                </UiField>
                <UiField label="Mulai">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model="form.mulai" type="date" :aria-describedby="describedby" />
                    </template>
                </UiField>
                <UiField label="Keterangan (opsional)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model="form.keterangan" type="text" :aria-describedby="describedby" />
                    </template>
                </UiField>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="menyimpan" @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block :disabled="menyimpan || !form.santri_id || !form.sumber_dana_id || !form.mulai" @click="simpan">
                        Simpan
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
