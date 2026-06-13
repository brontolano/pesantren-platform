<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, PageHeader, StatCard, SectionCard, ListRow, StatusBadge, IconCircle } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    progress: { type: Object, default: () => ({}) },
    setoran: { type: Array, default: () => [] },
})

// Peta nilai setoran -> kunci StatusBadge yang dikenal (tone semantik) + label.
const nilaiMeta = {
    mumtaz: { status: 'lunas', label: 'Mumtaz' },
    jayyid_jiddan: { status: 'terverifikasi', label: 'Jayyid Jiddan' },
    jayyid: { status: 'info', label: 'Jayyid' },
}

const persen = computed(() => {
    const t = props.progress.target || 0
    if (!t) return 0
    return Math.min(100, Math.round(((props.progress.juz || 0) / t) * 100))
})
</script>

<template>
    <Head title="Hafalan" />

    <AppShell :nav-items="navigation" brand-name="Aplikasi Santri">
        <div class="space-y-5">
            <PageHeader kicker="Tahfizh" title="Hafalan" subtitle="Progress & setoran terakhir" />

            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="book" tone="teal" label="Hafalan" :value="`${progress.juz} juz`" :hint="`target ${progress.target} juz`" />
                <StatCard icon="chart" tone="emerald" label="Ketercapaian" :value="`${persen}%`" :hint="`${progress.halaman} halaman`" />
            </div>

            <SectionCard kicker="Pekan ini" title="Ringkasan">
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-500">Progress menuju target</span>
                        <span class="font-bold text-slate-900">{{ progress.juz }} / {{ progress.target }} juz</span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-[var(--wali-primary,#059669)] transition-all duration-500" :style="{ width: `${persen}%` }" />
                    </div>
                    <p class="text-xs text-slate-400">{{ progress.ayatPekanIni }} ayat disetorkan pekan ini.</p>
                </div>
            </SectionCard>

            <SectionCard kicker="Riwayat" title="5 Setoran Terakhir" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="s in setoran"
                        :key="s.id"
                        :title="s.surah"
                        :subtitle="`Juz ${s.juz} • ${s.tanggal}`"
                    >
                        <template #leading>
                            <IconCircle icon="book" tone="teal" size="md" />
                        </template>
                        <template #trailing>
                            <StatusBadge
                                :status="(nilaiMeta[s.nilai] || {}).status || 'info'"
                                :label="(nilaiMeta[s.nilai] || {}).label || s.nilai"
                                size="sm"
                            />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Data peragaan. Nilai setoran dinilai oleh ustadz pembimbing tahfizh.
            </p>
        </div>
    </AppShell>
</template>
