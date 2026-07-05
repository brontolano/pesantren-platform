<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, PageHeader, StatCard, SectionCard, ListRow, StatusBadge, Chip, IconCircle } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    akun: { type: Array, default: () => [] },
})

const TIPE_TONE = {
    ASET: 'blue', KEWAJIBAN: 'rose', EKUITAS: 'amber', PENDAPATAN: 'emerald', BEBAN: 'teal',
}

const totalAktif = computed(() => props.akun.filter((a) => a.is_active).length)
</script>

<template>
    <Head title="Bagan Akun · Akuntan" />

    <AppShell :nav-items="navigation" brand-name="Akuntan">
        <div class="space-y-5">
            <PageHeader kicker="Buku Besar" title="Bagan Akun (COA)" subtitle="Daftar akun perkiraan" />

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="bank" tone="blue" label="Total Akun" :value="String(akun.length)" />
                <StatCard icon="check-circle" tone="emerald" label="Aktif" :value="String(totalAktif)" />
            </div>

            <SectionCard flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="a in akun"
                        :key="a.id"
                        :title="`${a.kode} — ${a.nama}`"
                    >
                        <template #leading>
                            <IconCircle icon="bank" :tone="TIPE_TONE[a.tipe] ?? 'slate'" size="md" />
                        </template>
                        <template #subtitle>
                            <span class="inline-flex items-center gap-2">
                                <Chip :active="false" tone="soft" size="sm">{{ a.tipe }}</Chip>
                                <Chip v-if="a.is_kas_bank" :active="false" tone="soft" size="sm">Kas/Bank</Chip>
                            </span>
                        </template>
                        <template #trailing>
                            <StatusBadge v-if="!a.is_active" status="info" label="Nonaktif" size="sm" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Tambah/ubah akun perkiraan dilakukan lewat panel admin (setup awal, jarang berubah).
            </p>
        </div>
    </AppShell>
</template>
