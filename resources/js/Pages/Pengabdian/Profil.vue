<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, StatCard, ListRow, StatusBadge, IconCircle, UiButton } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: 'Santri Pengabdian' },
    profil: { type: Object, default: () => ({}) },
    ringkasan: { type: Array, default: () => [] },
    riwayat: { type: Array, default: () => [] },
    sertifikat: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="Profil Khidmah" />

    <AppShell :nav-items="navigation" brand-name="Pengabdian Santri">
        <div class="space-y-5">
            <PageHeader kicker="Akun" title="Profil Khidmah" />

            <SectionCard>
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-brand-grad text-xl font-bold text-white">
                        {{ (profil.nama || '?').charAt(0).toUpperCase() }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-lg font-bold text-slate-900">{{ profil.nama }}</p>
                        <p class="text-sm text-slate-500">NIS {{ profil.nis }}</p>
                    </div>
                </div>

                <dl class="mt-4 space-y-2.5 rounded-2xl bg-slate-50 px-4 py-3.5 ring-1 ring-inset ring-slate-100">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-sm text-slate-500">Unit Khidmah</dt>
                        <dd class="text-sm font-semibold text-slate-800">{{ profil.unit }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-sm text-slate-500">Angkatan</dt>
                        <dd class="text-sm font-semibold text-slate-800">{{ profil.angkatan }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-sm text-slate-500">Pembimbing</dt>
                        <dd class="text-sm font-semibold text-slate-800">{{ profil.pembimbing }}</dd>
                    </div>
                </dl>
            </SectionCard>

            <div class="grid grid-cols-2 gap-3">
                <StatCard
                    v-for="r in ringkasan"
                    :key="r.key"
                    :icon="r.icon"
                    :tone="r.tone"
                    :label="r.label"
                    :value="r.value"
                    :hint="r.hint"
                />
            </div>

            <SectionCard kicker="Rekam Jejak" title="Riwayat Khidmah" subtitle="Kegiatan yang telah terverifikasi" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="r in riwayat"
                        :key="r.id"
                        :title="r.judul"
                        :subtitle="`${r.tanggal} · ${r.jam}`"
                    >
                        <template #leading>
                            <IconCircle icon="clock" tone="teal" size="md" />
                        </template>
                        <template #trailing>
                            <StatusBadge :status="r.status" size="sm" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <SectionCard kicker="Apresiasi" title="Sertifikat & Piagam" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="s in sertifikat"
                        :key="s.id"
                        :title="s.judul"
                        :subtitle="s.tanggal"
                    >
                        <template #leading>
                            <IconCircle icon="heart" tone="rose" size="md" />
                        </template>
                        <template #trailing>
                            <StatusBadge :status="s.status" size="sm" label="Terbit" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <UiButton variant="ghost" block icon-left="arrow-left" as="a" href="/logout">Keluar</UiButton>
        </div>
    </AppShell>
</template>
