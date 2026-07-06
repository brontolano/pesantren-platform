<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, ListRow, EmptyState, UiButton } from '@/Shared'

const props = defineProps({
    daftar: { type: Array, default: () => [] },
    q: { type: String, default: '' },
})

const nav = [
    { key: 'beranda', label: 'Beranda', href: '/staf', active: false },
    { key: 'santri', label: 'Santri', href: '/santri-info', active: true },
]

const cari = ref(props.q)
function submitCari() {
    router.get('/santri-info', { q: cari.value }, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <AppShell :nav-items="nav" brand-name="Data Santri">
        <Head><title>Data Santri</title></Head>
        <div class="space-y-5">
            <PageHeader kicker="Santri" title="Data Santri" subtitle="Rujukan santri aktif (baca-saja)." />

            <form class="flex gap-2" @submit.prevent="submitCari">
                <input v-model="cari" type="search" placeholder="Cari nama / NIS…" class="w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-[var(--brand-primary)] focus:ring-2 focus:ring-[var(--brand-primary)]/20" />
                <UiButton type="submit" variant="secondary">Cari</UiButton>
            </form>

            <EmptyState v-if="daftar.length === 0" icon="user" title="Tidak ada santri" message="Coba kata kunci lain." />

            <SectionCard v-else title="Santri Aktif" :subtitle="`${daftar.length} ditampilkan`" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="s in daftar" :key="s.id" :title="s.nama" :subtitle="[s.nis, s.kelas].filter(Boolean).join(' · ')">
                        <template #trailing>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ s.jk || '—' }}</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </div>
    </AppShell>
</template>
