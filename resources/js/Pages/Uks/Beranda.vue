<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, HeroHeader, StatCard, SectionCard, ListRow, StatusBadge, IconCircle } from '@/Shared'

defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    userName: { type: String, default: 'Petugas UKS' },
    stats: { type: Array, default: () => [] },
    kunjunganTerbaru: { type: Array, default: () => [] },
})

const statusLabel = { pulang: 'Pulang', istirahat: 'Istirahat', rujuk: 'Dirujuk' }
</script>

<template>
    <Head title="Kesehatan UKS" />

    <AppShell :nav-items="navigation" brand-name="Kesehatan UKS">
        <template #header>
            <HeroHeader :name="userName" greeting="Assalamu'alaikum,">
                <template #subtitle>Poliklinik &amp; UKS Pesantren</template>
                Ringkasan layanan kesehatan santri hari ini.
            </HeroHeader>
        </template>

        <div class="space-y-6">
            <div class="grid grid-cols-2 gap-3">
                <StatCard
                    v-for="s in stats"
                    :key="s.key"
                    :icon="s.icon"
                    :tone="s.tone"
                    :label="s.label"
                    :value="s.value"
                    :hint="s.hint"
                />
            </div>

            <SectionCard kicker="Hari ini" title="Kunjungan Terbaru" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="k in kunjunganTerbaru"
                        :key="k.id"
                        :title="k.nama"
                        :subtitle="`${k.kelas} • ${k.keluhan}`"
                    >
                        <template #leading>
                            <IconCircle icon="heart" tone="rose" size="md" />
                        </template>
                        <template #trailing>
                            <div class="flex flex-col items-end gap-1.5">
                                <StatusBadge :status="k.status" :label="statusLabel[k.status]" size="sm" />
                                <span class="text-xs font-medium text-slate-400">{{ k.jam }}</span>
                            </div>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Data peragaan. Catat kunjungan baru lewat tab Kunjungan untuk merekam keluhan &amp; tindakan santri.
            </p>
        </div>
    </AppShell>
</template>
