<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, ListRow, EmptyState, UiButton, STAF_NAV, KembaliBar } from '@/Shared'

const props = defineProps({
    daftar: { type: Array, default: () => [] },
    q: { type: String, default: '' },
})

const nav = STAF_NAV

const cari = ref(props.q)
function submitCari() {
    router.get('/akademik-app/alumni', { q: cari.value }, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <AppShell :nav-items="nav" brand-name="Alumni">
        <Head><title>Alumni</title></Head>
        <div class="space-y-5">
            <KembaliBar />
            <PageHeader kicker="Santri" title="Data Alumni" subtitle="Lulusan pesantren per tahun." />

            <form class="flex gap-2" @submit.prevent="submitCari">
                <input v-model="cari" type="search" placeholder="Cari nama alumni…" class="w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-[var(--brand-primary)] focus:ring-2 focus:ring-[var(--brand-primary)]/20" />
                <UiButton type="submit" variant="secondary">Cari</UiButton>
            </form>

            <EmptyState v-if="daftar.length === 0" icon="user" title="Belum ada alumni" message="Data alumni akan tampil di sini." />

            <SectionCard v-else title="Alumni" :subtitle="`${daftar.length} alumni terbaru`" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="a in daftar" :key="a.id" :title="a.nama || '-'" :subtitle="[a.nis, a.lembaga, a.lanjutan].filter(Boolean).join(' · ')">
                        <template #trailing>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">{{ a.tahun }}</span>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>
        </div>
    </AppShell>
</template>
