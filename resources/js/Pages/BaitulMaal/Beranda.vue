<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, HeroHeader, StatCard, MoneyText, SectionCard, Chip } from '@/Shared'

defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    userName: { type: String, default: 'Bendahara' },
    stats: { type: Array, default: () => [] },
    totalDonasiBulanIni: { type: Number, default: 0 },
})
</script>

<template>
    <Head title="Baitul Maal" />

    <AppShell :nav-items="navigation" brand-name="Baitul Maal">
        <template #header>
            <HeroHeader :name="userName" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <Chip :active="true" tone="soft" size="sm" icon="heart">Baitul Maal</Chip>
                </template>

                <div class="grid grid-cols-3 gap-2">
                    <div
                        v-for="s in stats"
                        :key="s.key"
                        class="rounded-2xl bg-white/15 px-3 py-3 text-center ring-1 ring-inset ring-white/20"
                    >
                        <p class="text-lg font-extrabold leading-none text-white">{{ s.value }}</p>
                        <p class="mt-1 text-[10.5px] font-medium leading-tight text-white/75">{{ s.label }}</p>
                    </div>
                </div>
            </HeroHeader>
        </template>

        <div class="space-y-5">
            <SectionCard kicker="Donasi" title="Terkumpul Bulan Ini">
                <MoneyText :amount="totalDonasiBulanIni" size="xl" tone="emerald" />
                <p class="mt-1 text-xs font-medium text-slate-400">Donasi terkonfirmasi</p>
            </SectionCard>

            <div class="grid grid-cols-1 gap-3">
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
        </div>
    </AppShell>
</template>
