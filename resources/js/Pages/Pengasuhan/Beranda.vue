<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { AppShell, HeroHeader, StatCard, SectionCard, ListRow, IconCircle, StatusBadge } from '@/Shared'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: '' },
    ringkasan: { type: Object, default: () => ({}) },
    kamar: { type: Array, default: () => [] },
})

const navItems = props.navigation

const menu = [
    { href: '/pengasuhan/presensi', icon: 'check-circle', tone: 'emerald', label: 'Presensi', hint: 'Absensi malam' },
    { href: '/pengasuhan/disiplin', icon: 'chart', tone: 'amber', label: 'Disiplin', hint: 'Poin pelanggaran' },
    { href: '/pengasuhan/catat-pelanggaran', icon: 'plus', tone: 'rose', label: 'Catat', hint: 'Pelanggaran baru' },
    { href: '/pengasuhan/izin', icon: 'document', tone: 'blue', label: 'Izin', hint: 'Keluar / pulang' },
]
</script>

<template>
    <Head title="Kepengasuhan" />

    <AppShell :nav-items="navItems" brand-name="Kepengasuhan">
        <template #header>
            <HeroHeader :name="userName || 'Musyrif'" greeting="Assalamu'alaikum,">
                <template #subtitle>Pengasuhan Asrama</template>
            </HeroHeader>
        </template>

        <div class="space-y-5">
            <!-- Ringkasan -->
            <div class="grid grid-cols-2 gap-3">
                <StatCard icon="user" tone="emerald" label="Santri Binaan" :value="ringkasan.santri_binaan" hint="Total asuhan" />
                <StatCard icon="document" tone="blue" label="Izin Aktif" :value="ringkasan.izin_aktif" hint="Sedang berlaku" />
                <StatCard icon="chart" tone="amber" label="Pelanggaran" :value="ringkasan.pelanggaran_pending" hint="Menunggu tindak" />
                <StatCard icon="check-circle" tone="teal" label="Hadir Malam" :value="ringkasan.kehadiran_malam" hint="Apel terakhir" />
            </div>

            <!-- Daftar kamar binaan -->
            <SectionCard kicker="Asrama" title="Kamar Binaan" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow v-for="k in kamar" :key="k.id" :href="`/pengasuhan/presensi`" chevron>
                        <template #leading><IconCircle icon="home" tone="emerald" /></template>
                        <template #title>{{ k.nama }}</template>
                        <template #subtitle>{{ k.lantai }} · {{ k.jumlah }} santri</template>
                        <template #trailing>
                            <StatusBadge :status="k.status" :label="`${k.hadir}/${k.jumlah} hadir`" size="sm" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <!-- Menu -->
            <section class="space-y-3">
                <h3 class="font-jost text-lg font-bold tracking-tight text-slate-900">Menu Pengasuhan</h3>
                <div class="grid grid-cols-2 gap-2.5">
                    <Link
                        v-for="m in menu"
                        :key="m.href"
                        :href="m.href"
                        class="flex items-center gap-3 rounded-3xl bg-white p-4 text-left ring-1 ring-slate-100 shadow-soft transition-all duration-200 active:scale-[.97]"
                    >
                        <IconCircle :icon="m.icon" :tone="m.tone" />
                        <div class="min-w-0">
                            <p class="font-jost text-[13px] font-bold text-slate-900">{{ m.label }}</p>
                            <p class="truncate text-[11px] text-slate-400">{{ m.hint }}</p>
                        </div>
                    </Link>
                </div>
            </section>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Mode peragaan — seluruh data contoh; tindakan tersimpan sementara di perangkat.
            </p>
        </div>
    </AppShell>
</template>
