<script setup>
import { computed, ref } from 'vue'
import {
    AppShell, PageHeader, SectionCard, ListRow, IconCircle, Chip,
    SegmentedControl, UiField, UiInput, UiButton, StatusBadge, EmptyState, BottomSheet,
} from '@/Shared'

/**
 * Santri (data induk) — filter kelas (SegmentedControl) + cari (UiInput) +
 * tap baris -> BottomSheet kartu identitas. Murni client-side filter atas
 * data dummy controller; tidak ada request server.
 */
const props = defineProps({
    userName: { type: String, default: 'Staf TU' },
    kelasOptions: { type: Array, default: () => [] },
    santri: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])

const kelas = ref('semua')
const cari = ref('')

const hasil = computed(() =>
    props.santri.filter((s) => {
        const cocokKelas = kelas.value === 'semua' || s.kelas === kelas.value
        const q = cari.value.trim().toLowerCase()
        const cocokCari = !q || s.nama.toLowerCase().includes(q) || s.nis.includes(q)
        return cocokKelas && cocokCari
    }),
)

// BottomSheet detail.
const sheetOpen = ref(false)
const aktif = ref(null)
const tersalin = ref(false)

function buka(s) {
    aktif.value = s
    tersalin.value = false
    sheetOpen.value = true
}

function salinNis() {
    const nis = aktif.value?.nis ?? ''
    if (navigator?.clipboard?.writeText) {
        navigator.clipboard.writeText(nis).catch(() => {})
    }
    tersalin.value = true
    setTimeout(() => (tersalin.value = false), 1800)
}

const identitas = computed(() => {
    const s = aktif.value
    if (!s) return []
    return [
        { label: 'NIS', value: s.nis },
        { label: 'NISN', value: s.nisn },
        { label: 'Kelas', value: s.kelas },
        { label: 'Tempat, Tgl Lahir', value: s.ttl },
        { label: 'Nama Ayah', value: s.ayah },
        { label: 'Nama Ibu', value: s.ibu },
        { label: 'Alamat', value: s.alamat },
    ]
})
</script>

<template>
    <AppShell :nav-items="navItems" brand-name="Tata Usaha">
        <div class="space-y-5">
            <PageHeader kicker="Data Induk" title="Data Santri" subtitle="Cari & lihat identitas santri" />

            <SegmentedControl v-model="kelas" :options="kelasOptions" />

            <UiField label="Cari santri">
                <template #default="{ fieldId, describedby }">
                    <UiInput
                        :id="fieldId"
                        :aria-describedby="describedby"
                        v-model="cari"
                        placeholder="Nama atau NIS…"
                    />
                </template>
            </UiField>

            <SectionCard flush>
                <div v-if="hasil.length" class="divide-y divide-slate-100">
                    <ListRow
                        v-for="s in hasil"
                        :key="s.id"
                        :title="s.nama"
                        :subtitle="`NIS ${s.nis}`"
                        chevron
                        @click="buka(s)"
                        role="button"
                        tabindex="0"
                        class="cursor-pointer"
                        @keydown.enter="buka(s)"
                    >
                        <template #leading>
                            <IconCircle icon="user" tone="emerald" size="sm" />
                        </template>
                        <template #trailing>
                            <Chip :active="true" tone="soft" size="sm">{{ s.kelas }}</Chip>
                        </template>
                    </ListRow>
                </div>
                <EmptyState
                    v-else
                    icon="book"
                    title="Tidak ada santri"
                    message="Coba ubah filter kelas atau kata kunci pencarian."
                />
            </SectionCard>
        </div>

        <!-- Detail santri (BottomSheet) -->
        <BottomSheet v-model="sheetOpen" :title="aktif?.nama">
            <div v-if="aktif" class="space-y-4">
                <div class="flex items-center gap-3">
                    <IconCircle icon="user" tone="emerald" variant="gradient" size="lg" />
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold text-slate-900">{{ aktif.nama }}</p>
                        <StatusBadge status="aktif" :label="`Kelas ${aktif.kelas}`" size="sm" />
                    </div>
                </div>

                <dl class="divide-y divide-slate-100 rounded-2xl bg-slate-50/60 px-4 ring-1 ring-slate-100">
                    <div v-for="row in identitas" :key="row.label" class="flex items-start justify-between gap-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ row.label }}</dt>
                        <dd class="text-right text-sm font-medium text-slate-800">{{ row.value }}</dd>
                    </div>
                </dl>
            </div>

            <template #footer>
                <div class="flex gap-3">
                    <UiButton
                        variant="secondary"
                        block
                        :icon-left="tersalin ? 'check' : 'copy'"
                        @click="salinNis"
                    >
                        {{ tersalin ? 'NIS Tersalin' : 'Salin NIS' }}
                    </UiButton>
                    <UiButton variant="primary" block icon-left="document" @click="sheetOpen = false">
                        Cetak Kartu
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
