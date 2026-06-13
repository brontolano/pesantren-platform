<script setup>
import { computed, ref } from 'vue'
import {
    AppShell, PageHeader, SectionCard, IconCircle, StatusBadge, UiButton,
} from '@/Shared'

/**
 * DetailSantri (halaman penuh) — kartu identitas santri + aksi Salin NIS / Cetak.
 * Versi BottomSheet ada di Santri.vue; ini fallback untuk akses langsung.
 */
const props = defineProps({
    userName: { type: String, default: 'Staf TU' },
    santri: { type: Object, default: () => ({}) },
    navigation: { type: Array, default: () => [] },
})

const navItems = computed(() => props.navigation ?? [])

const tersalin = ref(false)

function salinNis() {
    const nis = props.santri?.nis ?? ''
    if (navigator?.clipboard?.writeText) {
        navigator.clipboard.writeText(nis).catch(() => {})
    }
    tersalin.value = true
    setTimeout(() => (tersalin.value = false), 1800)
}

const identitas = computed(() => {
    const s = props.santri ?? {}
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
            <PageHeader kicker="Data Induk" title="Detail Santri" subtitle="Kartu identitas santri" />

            <SectionCard>
                <div class="flex items-center gap-3">
                    <IconCircle icon="user" tone="emerald" variant="gradient" size="lg" />
                    <div class="min-w-0">
                        <p class="truncate text-lg font-bold text-slate-900">{{ santri.nama }}</p>
                        <StatusBadge status="aktif" :label="`Kelas ${santri.kelas}`" size="sm" />
                    </div>
                </div>

                <dl class="mt-4 divide-y divide-slate-100 rounded-2xl bg-slate-50/60 px-4 ring-1 ring-slate-100">
                    <div v-for="row in identitas" :key="row.label" class="flex items-start justify-between gap-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ row.label }}</dt>
                        <dd class="text-right text-sm font-medium text-slate-800">{{ row.value }}</dd>
                    </div>
                </dl>
            </SectionCard>

            <div class="flex gap-3">
                <UiButton
                    variant="secondary"
                    block
                    :icon-left="tersalin ? 'check' : 'copy'"
                    @click="salinNis"
                >
                    {{ tersalin ? 'NIS Tersalin' : 'Salin NIS' }}
                </UiButton>
                <UiButton variant="primary" block icon-left="document">
                    Cetak Kartu
                </UiButton>
            </div>
        </div>
    </AppShell>
</template>
