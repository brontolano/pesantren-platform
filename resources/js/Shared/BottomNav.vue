<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import UiIcon from '@/Components/Ui/UiIcon.vue'

/**
 * BottomNav — navigasi pill mengambang berikon (ekstrak dari PortalWaliLayout).
 * Item didorong server lewat `page.props.navigation = [{key,label,href,active}]`
 * (default), atau di-override via prop `items`.
 * Ikon dipetakan dari `key` lewat DEFAULT_ICONS + `iconMap` (override per app),
 * atau properti `item.icon` langsung dari backend.
 */
const props = defineProps({
    items: { type: Array, default: null },
    iconMap: { type: Object, default: () => ({}) },
})

const page = usePage()
const navItems = computed(() =>
    props.items ?? (Array.isArray(page.props.navigation) ? page.props.navigation : []),
)

// Peta default key->ikon, mencakup peran wali/guru/kasir/pimpinan.
const DEFAULT_ICONS = {
    beranda: 'home', home: 'home', dashboard: 'home', hariini: 'calendar',
    keuangan: 'wallet', catat: 'receipt', belanja: 'receipt', pemasukan: 'cash',
    setor: 'cash', tabungan: 'wallet', slip: 'document', dasbor: 'chart',
    laporan: 'chart', approval: 'inbox', inbox: 'inbox', izin: 'document',
    santri: 'book', akademik: 'book', nilai: 'book', tahfidz: 'book',
    presensi: 'check-circle', kepengasuhan: 'heart', gerbang: 'qr',
    akun: 'user', profil: 'user', notifikasi: 'bell', mutu: 'chart',
}

function iconFor(item) {
    return props.iconMap[item.key] || item.icon || DEFAULT_ICONS[item.key] || 'home'
}
</script>

<template>
    <footer
        class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-md px-[18px] pb-[max(28px,env(safe-area-inset-bottom))]"
    >
        <nav
            class="flex items-center justify-around rounded-full bg-white px-2 py-[9px] shadow-[inset_0_0_0_1px_#efe7da,0_16px_44px_-24px_rgba(17,17,17,0.4)]"
        >
            <Link
                v-for="item in navItems"
                :key="item.key || item.label"
                :href="item.href"
                class="group relative flex flex-col items-center gap-1 rounded-full px-1 py-1.5 transition-all duration-200 active:scale-[.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500"
                :class="item.active ? 'text-emerald-700' : 'text-slate-400 hover:text-slate-600'"
                :aria-current="item.active ? 'page' : undefined"
            >
                <span
                    class="flex h-[34px] w-[34px] items-center justify-center rounded-full transition-all duration-200"
                    :class="item.active
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-transparent text-slate-400 group-hover:bg-slate-900/[0.04]'"
                >
                    <UiIcon :name="iconFor(item)" class="h-[19px] w-[19px]" />
                </span>
                <span
                    class="font-jost text-[9.5px] leading-none tracking-tight transition-all duration-200"
                    :class="item.active ? 'font-semibold text-emerald-700' : 'font-medium'"
                >
                    {{ item.label }}
                </span>
            </Link>
        </nav>
    </footer>
</template>
