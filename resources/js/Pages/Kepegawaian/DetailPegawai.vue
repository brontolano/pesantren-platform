<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, MoneyText, IconCircle, UiButton } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    pegawai: { type: Object, default: () => ({}) },
    riwayatSk: { type: Array, default: () => [] },
})

const totalGaji = computed(
    () => (props.pegawai.gaji_pokok ?? 0) + (props.pegawai.tunjangan ?? 0) - (props.pegawai.potongan ?? 0),
)
</script>

<template>
    <Head :title="pegawai.nama || 'Detail Pegawai'" />

    <AppShell :nav-items="navigation" brand-name="HRD Kepegawaian">
        <div class="space-y-5">
            <UiButton variant="ghost" size="sm" href="/kepegawaian/pegawai" icon-left="arrow-left">
                Kembali ke daftar
            </UiButton>

            <SectionCard>
                <div class="flex items-start gap-4">
                    <IconCircle icon="user" tone="emerald" size="lg" variant="gradient" />
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-extrabold leading-tight tracking-tight text-slate-900">
                            {{ pegawai.nama }}
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">{{ pegawai.jabatan }} · {{ pegawai.unit }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <StatusBadge
                                :status="pegawai.status === 'aktif' ? 'aktif' : 'info'"
                                :label="pegawai.status === 'aktif' ? 'Aktif' : 'Nonaktif'"
                                size="sm"
                            />
                            <StatusBadge status="info" :label="`Pegawai ${pegawai.jenis}`" size="sm" />
                        </div>
                        <p class="mt-2 text-xs font-medium text-slate-400">NIP {{ pegawai.nip }}</p>
                    </div>
                </div>
            </SectionCard>

            <PageHeader title="Riwayat SK & Jabatan" subtitle="Surat keputusan terkini" />
            <SectionCard flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="sk in riwayatSk"
                        :key="sk.no_sk"
                        :title="sk.judul"
                        :subtitle="`${sk.no_sk} · ${sk.tanggal}`"
                    >
                        <template #leading><IconCircle icon="document" tone="slate" /></template>
                    </ListRow>
                </div>
            </SectionCard>

            <PageHeader title="Kontrak Kerja" />
            <SectionCard flush>
                <div class="divide-y divide-slate-100">
                    <ListRow title="Jenis Kepegawaian" :subtitle="pegawai.jenis === 'Tetap' ? 'Pegawai tetap' : 'Pegawai kontrak'">
                        <template #leading><IconCircle icon="user" tone="teal" /></template>
                        <template #trailing>
                            <StatusBadge status="info" :label="pegawai.jenis" size="sm" />
                        </template>
                    </ListRow>
                    <ListRow title="Berakhir Kontrak" subtitle="Tanggal akhir masa kerja saat ini">
                        <template #leading><IconCircle icon="calendar" tone="amber" /></template>
                        <template #trailing>
                            <p class="text-sm font-bold text-slate-900">{{ pegawai.kontrak_berakhir }}</p>
                            <StatusBadge
                                v-if="pegawai.sisa_hari"
                                status="info"
                                :label="`${pegawai.sisa_hari} hari lagi`"
                                size="sm"
                                class="mt-1"
                            />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <PageHeader title="Struktur Gaji" subtitle="Komponen penggajian bulanan" />
            <SectionCard>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Gaji Pokok</span>
                        <MoneyText :amount="pegawai.gaji_pokok" size="md" />
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Tunjangan</span>
                        <MoneyText :amount="pegawai.tunjangan" size="md" tone="emerald" />
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Potongan</span>
                        <MoneyText :amount="-pegawai.potongan" size="md" tone="rose" signed />
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                        <span class="text-sm font-bold text-slate-900">Take Home Pay</span>
                        <MoneyText :amount="totalGaji" size="lg" tone="emerald" />
                    </div>
                </div>
            </SectionCard>
        </div>
    </AppShell>
</template>
