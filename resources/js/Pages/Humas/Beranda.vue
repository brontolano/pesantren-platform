<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, HeroHeader, StatCard, SectionCard, ListRow, Chip } from '@/Shared'

const props = defineProps({
    userName: { type: String, default: '' },
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    stats: { type: Array, default: () => [] },
    terbitTerbaru: { type: Array, default: () => [] },
})

const navItems = props.navigation

const COVER = {
    emerald: 'bg-violet-100 text-violet-700',
    teal: 'bg-teal-100 text-teal-700',
    amber: 'bg-amber-100 text-amber-700',
    blue: 'bg-blue-100 text-blue-700',
    rose: 'bg-rose-100 text-rose-700',
    slate: 'bg-slate-100 text-slate-600',
}
</script>

<template>
    <Head title="Humas Pesantren" />

    <AppShell :nav-items="navItems" brand-name="Humas Pesantren">
        <template #header>
            <HeroHeader :name="userName || 'Staf Humas'" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <span class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-0.5 text-[11px] font-semibold text-white ring-1 ring-inset ring-white/25">
                        Staf Humas / PR
                    </span>
                </template>
            </HeroHeader>
        </template>

        <div class="space-y-6">
            <section class="grid grid-cols-2 gap-3">
                <StatCard
                    v-for="s in stats"
                    :key="s.key"
                    :icon="s.icon"
                    :tone="s.tone"
                    :label="s.label"
                    :value="String(s.value)"
                    :hint="s.hint"
                />
            </section>

            <SectionCard kicker="Publikasi" title="Terbit Terbaru" subtitle="Artikel yang baru saja dipublikasikan" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="a in terbitTerbaru"
                        :key="a.id"
                        :href="`/humas/berita/${a.id}`"
                        chevron
                    >
                        <template #leading>
                            <span
                                class="flex h-11 w-11 items-center justify-center rounded-2xl text-sm font-bold ring-1 ring-inset ring-black/5"
                                :class="COVER[a.warna] || COVER.slate"
                            >
                                {{ a.inisial }}
                            </span>
                        </template>
                        <template #title>{{ a.judul }}</template>
                        <template #subtitle>{{ a.kategori }} &middot; {{ a.tanggal }}</template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Ringkasan publikasi humas pesantren. Ketuk artikel untuk membuka detail.
            </p>
        </div>
    </AppShell>
</template>
