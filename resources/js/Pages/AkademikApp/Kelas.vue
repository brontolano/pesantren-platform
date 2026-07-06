<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { AppShell, PageHeader, SectionCard, IconCircle, EmptyState } from '@/Shared'

defineProps({ daftar: { type: Array, default: () => [] } })

const nav = [
    { key: 'beranda', label: 'Beranda', href: '/staf', active: false },
    { key: 'kelas', label: 'Kelas', href: '/akademik-app/kelas', active: true },
    { key: 'presensi', label: 'Presensi', href: '/akademik-app/presensi', active: false },
]
</script>

<template>
    <AppShell :nav-items="nav" brand-name="Akademik">
        <Head><title>Kelas</title></Head>
        <div class="space-y-5">
            <PageHeader kicker="Master Akademik" title="Daftar Kelas" subtitle="Ketuk kelas untuk melihat isi santrinya." />

            <EmptyState v-if="daftar.length === 0" icon="book" title="Belum ada kelas" message="Kelas yang dibuat akan tampil di sini." />

            <SectionCard v-else title="Kelas" :subtitle="`${daftar.length} kelas`" flush>
                <div class="divide-y divide-slate-100">
                    <Link v-for="k in daftar" :key="k.id" :href="`/akademik-app/kelas/${k.id}`" class="flex items-center gap-3 px-5 py-4 transition active:bg-slate-50">
                        <IconCircle icon="book" tone="emerald" variant="tint" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-slate-900">{{ k.nama }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                <span v-if="k.tingkat">Tingkat {{ k.tingkat }}</span><span v-if="k.jurusan"> · {{ k.jurusan }}</span>
                                <span v-if="k.wali"> · Wali: {{ k.wali }}</span>
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">{{ k.jumlah }}<span v-if="k.kapasitas">/{{ k.kapasitas }}</span></span>
                    </Link>
                </div>
            </SectionCard>
        </div>
    </AppShell>
</template>
