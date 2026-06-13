<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, PageHeader, SectionCard, StatusBadge, IconCircle, EmptyState } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    riwayat: { type: Array, default: () => [] },
})

const statusLabel = { pulang: 'Pulang', istirahat: 'Istirahat', rujuk: 'Dirujuk' }
const toneFor = { pulang: 'emerald', istirahat: 'amber', rujuk: 'rose' }

// Kelompokkan riwayat per tanggal untuk tampilan timeline.
const grup = computed(() => {
    const map = new Map()
    for (const r of props.riwayat) {
        if (!map.has(r.tanggal)) map.set(r.tanggal, [])
        map.get(r.tanggal).push(r)
    }
    return [...map.entries()].map(([tanggal, items]) => ({ tanggal, items }))
})
</script>

<template>
    <Head title="Riwayat Kesehatan" />

    <AppShell :nav-items="navigation" brand-name="Kesehatan UKS">
        <div class="space-y-5">
            <PageHeader kicker="Rekam Medis" title="Riwayat Sakit" subtitle="Catatan kesehatan santri terkini" />

            <EmptyState
                v-if="!riwayat.length"
                icon="heart"
                title="Belum ada riwayat"
                message="Riwayat kunjungan & sakit santri akan tampil di sini."
            />

            <SectionCard v-for="g in grup" :key="g.tanggal" :title="g.tanggal" flush>
                <ol class="relative space-y-0 px-4 py-2">
                    <li
                        v-for="(r, i) in g.items"
                        :key="r.id"
                        class="relative flex gap-3.5 pb-4"
                        :class="i === g.items.length - 1 ? '' : 'border-l border-slate-100 pl-0'"
                    >
                        <div class="relative flex flex-col items-center">
                            <IconCircle icon="heart" :tone="toneFor[r.status] || 'slate'" size="sm" />
                            <span
                                v-if="i !== g.items.length - 1"
                                class="mt-1 w-px flex-1 bg-slate-100"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0 flex-1 pb-2">
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate text-[15px] font-bold tracking-tight text-slate-900">
                                    {{ r.nama }}
                                    <span class="text-xs font-medium text-slate-400">• {{ r.kelas }}</span>
                                </p>
                                <span class="shrink-0 text-xs font-medium text-slate-400">{{ r.jam }}</span>
                            </div>
                            <p class="mt-0.5 text-sm font-semibold text-slate-700">{{ r.diagnosa }}</p>
                            <p class="text-sm text-slate-500">{{ r.tindakan }}</p>
                            <div class="mt-2">
                                <StatusBadge :status="r.status" :label="statusLabel[r.status]" size="sm" />
                            </div>
                        </div>
                    </li>
                </ol>
            </SectionCard>
        </div>
    </AppShell>
</template>
