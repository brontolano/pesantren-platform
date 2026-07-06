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
 * Item mendukung `badge` (angka, opsional; F0): >0 → bulatan merah di ikon.
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
    menu: 'grid', aktivitas: 'bell',
    keuangan: 'wallet', catat: 'receipt', belanja: 'receipt', pemasukan: 'cash',
    setor: 'cash', tabungan: 'wallet', slip: 'document', dasbor: 'chart',
    laporan: 'chart', approval: 'inbox', inbox: 'inbox', izin: 'document',
    santri: 'book', akademik: 'book', nilai: 'book', tahfidz: 'book',
    presensi: 'check-circle', kepengasuhan: 'heart', gerbang: 'qr',
    akun: 'user', profil: 'user', notifikasi: 'bell', mutu: 'chart',
    // Nav peran lain (dulu jatuh ke 'home' generik). Hanya ikon yang ada di UiIcon.
    tamu: 'user', log: 'clock', riwayat: 'clock',
    sesi: 'heart', konselor: 'heart', konseling: 'heart', catatan: 'document',
    kerusakan: 'info', piket: 'calendar', sarpras: 'home', aset: 'home',
    pendaftar: 'user', pendaftaran: 'user', ppdb: 'user', jadwal: 'calendar', hasil: 'chart',
    berita: 'document', artikel: 'document', galeri: 'camera', humas: 'bell',
    donasi: 'heart', program: 'book', beasiswa: 'book', baitulmaal: 'heart',
    kasir: 'card', pos: 'card', pembayaran: 'wallet', bayar: 'wallet', tagihan: 'receipt',
    jurnal: 'book', bukubesar: 'book', akuntan: 'chart',
    pegawai: 'user', absensi: 'check-circle', cuti: 'calendar', kepegawaian: 'user',
    kegiatan: 'calendar', kedisiplinan: 'info', disiplin: 'info', pelanggaran: 'info',
    kelas: 'book', kbm: 'book', jurnalkbm: 'book', walikelas: 'user', kitab: 'book',
    uks: 'heart', kesehatan: 'heart', obat: 'heart', pengasuhan: 'heart', asrama: 'home',
    tu: 'document', surat: 'document', alumni: 'user', pimpinan: 'chart',
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
                class="group relative flex flex-col items-center gap-1 rounded-full px-1 py-1.5 transition-all duration-200 active:scale-[.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--brand-primary)]"
                :class="item.active ? 'text-[var(--brand-primary)]' : 'text-slate-400 hover:text-slate-600'"
                :aria-current="item.active ? 'page' : undefined"
            >
                <span
                    class="relative flex h-[34px] w-[34px] items-center justify-center rounded-full transition-all duration-200"
                    :class="item.active
                        ? 'bg-[var(--brand-primary-tint)] text-[var(--brand-primary)]'
                        : 'bg-transparent text-slate-400 group-hover:bg-slate-900/[0.04]'"
                >
                    <UiIcon :name="iconFor(item)" class="h-[19px] w-[19px]" />
                    <!-- Badge angka opsional (F0, additif): item.badge > 0 → bulatan merah. -->
                    <span
                        v-if="Number(item.badge) > 0"
                        class="absolute -right-0.5 -top-0.5 flex h-[15px] min-w-[15px] items-center justify-center rounded-full bg-rose-500 px-[3px] text-[9px] font-bold leading-none text-white ring-2 ring-white"
                    >
                        {{ Number(item.badge) > 99 ? '99+' : Number(item.badge) }}
                    </span>
                </span>
                <span
                    class="font-jost text-[9.5px] leading-none tracking-tight transition-all duration-200"
                    :class="item.active ? 'font-semibold text-[var(--brand-primary)]' : 'font-medium'"
                >
                    {{ item.label }}
                </span>
            </Link>
        </nav>
    </footer>
</template>
