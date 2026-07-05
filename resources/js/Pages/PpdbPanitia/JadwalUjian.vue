<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, IconCircle, BottomSheet, UiButton, UiField, UiInput, EmptyState } from '@/Shared'

defineProps({
    navigation: { type: Array, default: () => [] },
    jadwal: { type: Array, default: () => [] },
})

const navIcons = {
    beranda: 'chart',
    pendaftar: 'user',
    'jadwal-ujian': 'calendar',
    hasil: 'document',
}

const sheetOpen = ref(false)
const form = ref({ kategori_ujian: '', tanggal: '', waktu_mulai: '', waktu_selesai: '', ruang: '', keterangan: '' })
const menyimpan = ref(false)

function bukaBaru() {
    form.value = { kategori_ujian: '', tanggal: '', waktu_mulai: '', waktu_selesai: '', ruang: '', keterangan: '' }
    sheetOpen.value = true
}

function simpan() {
    menyimpan.value = true
    router.post('/ppdb-panitia/jadwal-ujian', form.value, {
        preserveScroll: true,
        onFinish: () => { menyimpan.value = false; sheetOpen.value = false },
    })
}
</script>

<template>
    <Head title="Jadwal Ujian — PPDB Panitia" />

    <AppShell :nav-items="navigation" :nav-icons="navIcons" brand-name="PPDB Panitia">
        <div class="space-y-5">
            <PageHeader kicker="PPDB" title="Jadwal Ujian" subtitle="Sesi ujian seleksi calon santri">
                <template #right>
                    <UiButton variant="primary" size="sm" icon-left="add" @click="bukaBaru">Baru</UiButton>
                </template>
            </PageHeader>

            <SectionCard flush>
                <EmptyState v-if="!jadwal.length" icon="calendar" title="Belum ada jadwal" message="Buat jadwal ujian pertama." />
                <div v-else class="divide-y divide-slate-100">
                    <ListRow
                        v-for="j in jadwal"
                        :key="j.id"
                        :title="j.kategori_ujian"
                        :subtitle="`${j.tanggal} · ${j.waktu_mulai}${j.waktu_selesai ? '–' + j.waktu_selesai : ''} · ${j.ruang || '-'}`"
                    >
                        <template #leading><IconCircle icon="calendar" tone="blue" /></template>
                    </ListRow>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" title="Jadwal Ujian Baru">
            <div class="space-y-3">
                <UiField label="Kategori Ujian">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model="form.kategori_ujian" type="text" placeholder="mis. Wawancara" :aria-describedby="describedby" />
                    </template>
                </UiField>
                <UiField label="Tanggal">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model="form.tanggal" type="date" :aria-describedby="describedby" />
                    </template>
                </UiField>
                <div class="grid grid-cols-2 gap-3">
                    <UiField label="Mulai">
                        <template #default="{ fieldId, describedby }">
                            <UiInput :id="fieldId" v-model="form.waktu_mulai" type="time" :aria-describedby="describedby" />
                        </template>
                    </UiField>
                    <UiField label="Selesai (opsional)">
                        <template #default="{ fieldId, describedby }">
                            <UiInput :id="fieldId" v-model="form.waktu_selesai" type="time" :aria-describedby="describedby" />
                        </template>
                    </UiField>
                </div>
                <UiField label="Ruang (opsional)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model="form.ruang" type="text" :aria-describedby="describedby" />
                    </template>
                </UiField>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="menyimpan" @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block :disabled="menyimpan || !form.kategori_ujian || !form.tanggal || !form.waktu_mulai" @click="simpan">
                        Simpan
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
