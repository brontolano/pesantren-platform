<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    AppShell, PageHeader, SectionCard, ListRow, StatusBadge, Chip, EmptyState,
} from '@/Shared'

const props = defineProps({
    hasil: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navIcons = {
    beranda: 'chart',
    pendaftar: 'user',
    verifikasi: 'check-circle',
    hasil: 'document',
}

const filter = ref('semua')
const filterOpsi = [
    { value: 'semua', label: 'Semua' },
    { value: 'lulus', label: 'Lulus' },
    { value: 'cadangan', label: 'Cadangan' },
    { value: 'ditolak', label: 'Ditolak' },
]

// Petakan keputusan -> status yang dikenal StatusBadge.
const badge = { lulus: 'terverifikasi', cadangan: 'menunggu', ditolak: 'ditolak' }
const labelKeputusan = { lulus: 'Lulus', cadangan: 'Cadangan', ditolak: 'Ditolak' }

const terfilter = computed(() =>
    filter.value === 'semua' ? props.hasil : props.hasil.filter((h) => h.keputusan === filter.value),
)

const tokenTersalin = ref(null)
function salin(token) {
    if (navigator?.clipboard) navigator.clipboard.writeText(token).catch(() => {})
    tokenTersalin.value = token
    window.setTimeout(() => (tokenTersalin.value = null), 1600)
}
</script>

<template>
    <Head title="Hasil Keputusan — PPDB Panitia" />

    <AppShell :nav-items="navigation" :nav-icons="navIcons" brand-name="PPDB Panitia">
        <div class="space-y-5">
            <PageHeader
                kicker="PPDB"
                title="Hasil Keputusan"
                :subtitle="`${terfilter.length} calon telah diputuskan`"
            />

            <!-- Filter -->
            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <Chip
                    v-for="f in filterOpsi"
                    :key="f.value"
                    :active="f.value === filter"
                    size="sm"
                    @click="filter = f.value"
                >
                    {{ f.label }}
                </Chip>
            </div>

            <SectionCard v-if="terfilter.length" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="h in terfilter"
                        :key="h.id"
                        :title="h.nama"
                        :subtitle="`${h.no} · ${h.program}`"
                    >
                        <template #leading>
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-50 text-sm font-bold text-slate-500">
                                {{ h.nama.charAt(0) }}
                            </span>
                        </template>
                        <template #trailing>
                            <StatusBadge :status="badge[h.keputusan]" :label="labelKeputusan[h.keputusan]" size="sm" />
                        </template>

                        <!-- Tracking token + tanggal -->
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50/80 px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-mono text-xs font-bold tracking-tight text-slate-700 transition active:scale-95"
                                @click="salin(h.token)"
                            >
                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                                {{ h.token }}
                                <span v-if="tokenTersalin === h.token" class="text-emerald-600">tersalin</span>
                            </button>
                            <span class="text-xs font-medium text-slate-400">{{ h.tanggal }}</span>
                        </div>
                    </ListRow>
                </div>
            </SectionCard>

            <EmptyState
                v-else
                icon="document"
                title="Belum ada hasil"
                message="Belum ada calon santri dengan keputusan untuk filter ini."
            />
        </div>
    </AppShell>
</template>
