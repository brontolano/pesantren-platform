<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { AppShell, PageHeader, StatCard, SectionCard, ListRow, IconCircle, BottomSheet, UiButton, UiField, UiInput, EmptyState } from '@/Shared'

defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: '' },
    penempatan: { type: Array, default: () => [] },
    santriOptions: { type: Array, default: () => [] },
    kamarOptions: { type: Array, default: () => [] },
    occupancy: { type: Object, default: () => ({ total_kamar: 0, total_kapasitas: 0, total_penghuni: 0, occupancy_rate: 0 }) },
})

const selectClass = 'w-full rounded-2xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]'

const sheetOpen = ref(false)
const form = ref({ santri_id: '', kamar_id: '', keterangan: '' })
const menyimpan = ref(false)

function bukaBaru() {
    form.value = { santri_id: '', kamar_id: '', keterangan: '' }
    sheetOpen.value = true
}

function simpan() {
    menyimpan.value = true
    router.post('/pengasuhan/penempatan', form.value, {
        preserveScroll: true,
        onFinish: () => { menyimpan.value = false; sheetOpen.value = false },
    })
}
</script>

<template>
    <Head title="Penempatan Asrama" />

    <AppShell :nav-items="navigation" brand-name="Pengasuhan/Asrama">
        <div class="space-y-5">
            <PageHeader kicker="Asrama" title="Penempatan Santri" subtitle="Kamar & blok asrama">
                <template #right>
                    <UiButton variant="primary" size="sm" icon-left="add" @click="bukaBaru">Tempatkan</UiButton>
                </template>
            </PageHeader>

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="home" tone="blue" label="Kamar" :value="String(occupancy.total_kamar)" :hint="`kapasitas ${occupancy.total_kapasitas}`" />
                <StatCard icon="user" tone="emerald" label="Terisi" :value="String(occupancy.total_penghuni)" :hint="`${occupancy.occupancy_rate}% okupansi`" />
            </div>

            <SectionCard flush>
                <EmptyState v-if="!penempatan.length" icon="home" title="Belum ada penempatan" message="Tempatkan santri ke kamar asrama." />
                <div v-else class="divide-y divide-slate-100">
                    <ListRow
                        v-for="p in penempatan"
                        :key="p.id"
                        :title="p.santri"
                        :subtitle="`${p.kelas} · ${p.kamar}`"
                    >
                        <template #leading><IconCircle icon="user" tone="teal" /></template>
                        <template #trailing>
                            <span class="text-xs text-slate-400">sejak {{ p.tanggal_masuk }}</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" title="Tempatkan Santri">
            <div class="space-y-3">
                <UiField label="Santri">
                    <template #default="{ fieldId }">
                        <select :id="fieldId" v-model="form.santri_id" :class="selectClass" required>
                            <option value="" disabled>Pilih santri…</option>
                            <option v-for="s in santriOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </template>
                </UiField>
                <UiField label="Kamar">
                    <template #default="{ fieldId }">
                        <select :id="fieldId" v-model="form.kamar_id" :class="selectClass" required>
                            <option value="" disabled>Pilih kamar…</option>
                            <option v-for="k in kamarOptions" :key="k.value" :value="k.value">{{ k.label }}</option>
                        </select>
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
                    <UiButton variant="primary" icon-left="check" block :disabled="menyimpan || !form.santri_id || !form.kamar_id" @click="simpan">
                        Simpan
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
