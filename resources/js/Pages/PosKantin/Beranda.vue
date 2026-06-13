<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, HeroHeader, StatCard, SectionCard, ListRow, MoneyText, IconCircle } from '@/Shared'

defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: '' },
    omzetHariIni: { type: Number, default: 0 },
    stats: { type: Array, default: () => [] },
    terlaris: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="POS Kantin — Beranda" />

    <AppShell :nav-items="navigation" brand-name="POS Kantin">
        <template #header>
            <HeroHeader :name="userName || 'Kasir Kantin'" greeting="Assalamu'alaikum,">
                <template #subtitle>Omzet kantin hari ini</template>
                <div class="mt-1">
                    <MoneyText :amount="omzetHariIni" size="2xl" tone="inherit" />
                </div>
            </HeroHeader>
        </template>

        <div class="space-y-5">
            <div class="grid grid-cols-3 gap-3">
                <StatCard
                    v-for="s in stats"
                    :key="s.key"
                    :icon="s.icon"
                    :tone="s.tone"
                    :label="s.label"
                    :value="s.value"
                    :hint="s.hint"
                    compact
                />
            </div>

            <SectionCard kicker="Penjualan" title="Produk Terlaris" subtitle="Berdasarkan sesi berjalan" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="(p, i) in terlaris"
                        :key="p.nama"
                        :title="p.nama"
                        :subtitle="`${p.terjual} terjual`"
                    >
                        <template #leading>
                            <IconCircle icon="cash" tone="emerald" variant="tint" size="md" />
                        </template>
                        <template #trailing>
                            <MoneyText :amount="p.omzet" size="sm" tone="emerald" />
                            <p class="mt-0.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">#{{ i + 1 }}</p>
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Mode peragaan — angka contoh kantin koperasi pesantren.
            </p>
        </div>
    </AppShell>
</template>
