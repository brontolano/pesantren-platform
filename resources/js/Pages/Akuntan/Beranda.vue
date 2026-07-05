<script setup>
import { Head } from '@inertiajs/vue3'
import { AppShell, HeroHeader, StatCard, SectionCard, MoneyText, Chip } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    userName: { type: String, default: 'Akuntan' },
    stats: { type: Array, default: () => [] },
    neracaRingkas: { type: Object, default: () => ({ total_debit: 0, total_kredit: 0, seimbang: true }) },
})
</script>

<template>
    <Head title="Akuntan" />

    <AppShell :nav-items="navigation" brand-name="Akuntan">
        <template #header>
            <HeroHeader :name="userName" greeting="Assalamu'alaikum,">
                <template #subtitle>
                    <Chip :active="true" tone="soft" size="sm" icon="bank">Akuntan</Chip>
                </template>

                <div class="grid grid-cols-2 gap-2">
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
            <SectionCard kicker="Neraca Saldo" title="Ringkasan Saldo">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Debit</p>
                        <MoneyText :amount="neracaRingkas.total_debit" size="lg" />
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Kredit</p>
                        <MoneyText :amount="neracaRingkas.total_kredit" size="lg" />
                    </div>
                </div>
                <p
                    class="mt-3 text-xs font-semibold"
                    :class="neracaRingkas.seimbang ? 'text-emerald-600' : 'text-rose-600'"
                >
                    {{ neracaRingkas.seimbang ? '✓ Neraca seimbang' : '⚠ Neraca tidak seimbang — periksa jurnal' }}
                </p>
            </SectionCard>

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
    </AppShell>
</template>
