<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import { PageHeader, SectionCard, ListRow, EmptyState, UiButton, IconCircle } from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

defineProps({
    hari: { type: String, required: true },
    tanggal: { type: String, required: true },
    sesi: { type: Array, default: () => [] },
    halaqah: { type: Array, default: () => [] },
    istilah: { type: Object, default: () => ({}) },
})

const menu = [
    { href: '/guru/nilai', icon: 'book', tone: 'emerald', label: 'Nilai', hint: 'Grid massal' },
    { href: '/guru/kitab', icon: 'document', tone: 'amber', label: 'Kitab', hint: 'Capaian kajian' },
    { href: '/guru/wali-kelas', icon: 'user', tone: 'teal', label: 'Wali Kelas', hint: 'Rekap binaan' },
]
</script>

<template>
    <div class="space-y-5">
        <Head><title>Hari Ini</title></Head>

        <PageHeader :kicker="`Hari Ini · ${hari}`" title="Jadwal Mengajar" :subtitle="tanggal" />

        <EmptyState
            v-if="sesi.length === 0 && halaqah.length === 0"
            icon="calendar" tone="emerald"
            title="Tidak ada jadwal hari ini"
            :message="`Belum ada sesi mengajar atau ${istilah.halaqah || 'halaqah'} terjadwal hari ini. Selamat beristirahat.`"
        />

        <!-- Sesi kelas -->
        <SectionCard v-if="sesi.length > 0" kicker="Akademik" title="Sesi Kelas" flush>
            <div class="divide-y divide-slate-100">
                <ListRow v-for="item in sesi" :key="item.id">
                    <template #leading><IconCircle icon="book" tone="emerald" /></template>
                    <template #title>{{ item.pelajaran }}</template>
                    <template #subtitle>
                        {{ item.kelas }} · {{ item.jam_mulai }}–{{ item.jam_selesai }}<span v-if="item.ruangan"> · {{ item.ruangan }}</span>
                    </template>
                    <template #trailing>
                        <UiButton :href="item.presensi_url" size="sm" icon-right="chevron-right">Presensi</UiButton>
                    </template>
                </ListRow>
            </div>
        </SectionCard>

        <!-- Halaqah binaan -->
        <SectionCard v-if="halaqah.length > 0" kicker="Tahfidz" :title="`${istilah.halaqah || 'Halaqah'} Binaan`" flush>
            <div class="divide-y divide-slate-100">
                <ListRow v-for="item in halaqah" :key="item.id">
                    <template #leading><IconCircle icon="book" tone="amber" /></template>
                    <template #title>{{ item.nama }}</template>
                    <template #subtitle>{{ item.jenis }} · {{ item.jumlah_santri }} {{ istilah.santri || 'santri' }}</template>
                    <template #trailing>
                        <UiButton :href="item.setoran_url" size="sm" variant="secondary" icon-right="chevron-right">Setoran</UiButton>
                    </template>
                </ListRow>
            </div>
        </SectionCard>

        <!-- Menu guru -->
        <section class="space-y-3">
            <h3 class="font-jost text-lg font-bold tracking-tight text-slate-900">Menu Guru</h3>
            <div class="grid grid-cols-3 gap-2.5">
                <Link
                    v-for="m in menu"
                    :key="m.href"
                    :href="m.href"
                    class="flex flex-col items-center gap-2 rounded-3xl bg-white p-4 text-center ring-1 ring-slate-100 shadow-soft transition-all duration-200 active:scale-[.97]"
                >
                    <IconCircle :icon="m.icon" :tone="m.tone" />
                    <div>
                        <p class="font-jost text-[13px] font-bold text-slate-900">{{ m.label }}</p>
                        <p class="text-[11px] text-slate-400">{{ m.hint }}</p>
                    </div>
                </Link>
            </div>
        </section>
    </div>
</template>
