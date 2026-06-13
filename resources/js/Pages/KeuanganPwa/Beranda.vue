<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AppShell, HeroHeader, IconCircle } from '@/Shared'

const props = defineProps({
    can: { type: Object, default: () => ({}) },
    navigation: { type: Array, default: () => [] },
    userName: { type: String, default: '' },
})

const base = '/admin/keuangan/pwa'

const shortcuts = computed(() => [
    { show: props.can.input_belanja, href: `${base}/catat-belanja`, icon: 'receipt', tone: 'emerald', label: 'Catat Belanja' },
    { show: props.can.setor_tabungan, href: `${base}/catat-pemasukan`, icon: 'cash', tone: 'teal', label: 'Catat Pemasukan' },
    { show: props.can.setor_tabungan, href: `${base}/setor-keliling`, icon: 'wallet', tone: 'amber', label: 'Setor Keliling' },
    { show: props.can.view_laporan_pimpinan, href: `${base}/dasbor`, icon: 'chart', tone: 'blue', label: 'Dasbor Pimpinan' },
    { show: props.can.view_slip_sendiri, href: `${base}/slip-saya`, icon: 'document', tone: 'slate', label: 'Slip Gaji Saya' },
].filter((s) => s.show))
</script>

<template>
    <Head title="Keuangan Lapangan" />

    <AppShell :nav-items="navigation" brand-name="Keuangan Lapangan">
        <template #header>
            <HeroHeader :name="userName || 'Petugas'" greeting="Assalamu'alaikum," />
        </template>

        <div class="space-y-5">
            <div class="grid grid-cols-2 gap-3">
                <a
                    v-for="s in shortcuts"
                    :key="s.href"
                    :href="s.href"
                    class="flex flex-col items-center gap-2.5 rounded-3xl bg-white p-5 text-center ring-1 ring-slate-100 shadow-soft transition-all duration-200 active:scale-[.97]"
                >
                    <IconCircle :icon="s.icon" :tone="s.tone" size="lg" />
                    <span class="font-jost text-[13px] font-bold leading-tight text-slate-800">{{ s.label }}</span>
                </a>
            </div>

            <p class="px-1 text-xs leading-5 text-slate-400">
                Mode lapangan — transaksi tersimpan luring saat sinyal lemah lalu tersinkron otomatis.
            </p>
        </div>
    </AppShell>
</template>
