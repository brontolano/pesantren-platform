<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    AppShell, PageHeader, SectionCard, ListRow, StatCard, StatusBadge,
    UiButton, UiCard, BottomSheet, UiField, UiInput,
} from '@/Shared'

const props = defineProps({
    calon: { type: Object, default: () => ({}) },
    navigation: { type: Array, default: () => [] },
})

const navIcons = {
    beranda: 'chart',
    pendaftar: 'user',
    verifikasi: 'check-circle',
    hasil: 'document',
}

const sheetOpen = ref(false)
const form = ref({ kategori_ujian: '', nilai: 0, catatan: '' })
const menyimpan = ref(false)

function bukaFormNilai() {
    form.value = { kategori_ujian: '', nilai: 0, catatan: '' }
    sheetOpen.value = true
}

function simpanNilai() {
    menyimpan.value = true
    router.post(`/ppdb-panitia/nilai-ujian/${props.calon.id}`, form.value, {
        preserveScroll: true,
        onFinish: () => { menyimpan.value = false; sheetOpen.value = false },
    })
}
</script>

<template>
    <Head title="Detail Pendaftar — PPDB Panitia" />

    <AppShell :nav-items="navigation" :nav-icons="navIcons" brand-name="PPDB Panitia">
        <div class="space-y-5">
            <Link
                href="/ppdb-panitia/pendaftar"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-slate-800"
            >
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                Kembali ke daftar
            </Link>

            <!-- Identitas calon -->
            <UiCard>
                <div class="flex items-start gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-lg font-bold text-emerald-600">
                        {{ (calon.nama || '?').charAt(0) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-lg font-bold tracking-tight text-slate-900">{{ calon.nama }}</p>
                        <p class="mt-0.5 text-sm text-slate-500">{{ calon.no }} · {{ calon.program }} · Gel. {{ calon.gelombang }}</p>
                        <div class="mt-2">
                            <StatusBadge :status="calon.status" size="sm" />
                        </div>
                    </div>
                </div>
            </UiCard>

            <!-- Biodata -->
            <SectionCard kicker="Data Diri" title="Biodata" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="(b, i) in calon.biodata" :key="i" :title="b.value">
                        <template #title>
                            <span class="text-[15px] font-bold text-slate-900">{{ b.value }}</span>
                        </template>
                        <template #subtitle>
                            <span class="text-xs uppercase tracking-wide text-slate-400">{{ b.label }}</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <!-- Hasil ujian -->
            <SectionCard kicker="Seleksi" title="Hasil Ujian">
                <div class="grid grid-cols-2 gap-3">
                    <StatCard
                        v-for="(u, i) in calon.ujian"
                        :key="i"
                        :tone="u.nilai >= 85 ? 'emerald' : u.nilai >= 70 ? 'amber' : 'rose'"
                        :label="u.label"
                        compact
                    >
                        <template #value>
                            <span class="text-xl font-extrabold tabular-nums text-slate-900">
                                {{ u.nilai }}<span class="text-sm font-semibold text-slate-400">/{{ u.maks }}</span>
                            </span>
                        </template>
                    </StatCard>
                </div>
                <UiButton variant="secondary" size="sm" class="mt-3" icon-left="add" @click="bukaFormNilai">
                    Catat Nilai
                </UiButton>
            </SectionCard>

            <!-- Dokumen -->
            <SectionCard kicker="Berkas" title="Kelengkapan Dokumen" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="(d, i) in calon.dokumen" :key="i" :title="d.nama">
                        <template #leading>
                            <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-50 text-slate-500">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                            </span>
                        </template>
                        <template #trailing>
                            <StatusBadge :status="d.status" size="sm" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <div class="grid grid-cols-2 gap-3">
                <UiButton href="/ppdb-panitia/verifikasi" variant="secondary" block icon-left="check-circle">
                    Verifikasi
                </UiButton>
                <UiButton href="/ppdb-panitia/keputusan" variant="primary" block icon-left="check">
                    Putuskan
                </UiButton>
            </div>
        </div>

        <BottomSheet v-model="sheetOpen" title="Catat Nilai Ujian">
            <div class="space-y-3">
                <UiField label="Kategori Ujian">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model="form.kategori_ujian" type="text" placeholder="mis. Baca Al-Qur'an" :aria-describedby="describedby" />
                    </template>
                </UiField>
                <UiField label="Nilai (0-100)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model.number="form.nilai" type="number" min="0" max="100" :aria-describedby="describedby" />
                    </template>
                </UiField>
                <UiField label="Catatan (opsional)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model="form.catatan" type="text" :aria-describedby="describedby" />
                    </template>
                </UiField>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="menyimpan" @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block :disabled="menyimpan || !form.kategori_ujian" @click="simpanNilai">
                        Simpan
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
