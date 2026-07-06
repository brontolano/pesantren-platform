/**
 * Bottom-nav STANDAR halaman detail staf (owner 2026-07-06) — persis mockup
 * staf-app-preview: Beranda · Menu · Aktivitas · Akun. Cermin dari
 * App\Support\StafNav (PHP) untuk halaman yang membangun nav di sisi klien.
 * Navigasi kontekstual memakai tombol Kembali, BUKAN mengganti bottom-nav.
 */
export const STAF_NAV = [
    { key: 'beranda', label: 'Beranda', href: '/staf', icon: 'home', active: false },
    { key: 'menu', label: 'Menu', href: '/staf?tab=menu', icon: 'grid', active: false },
    { key: 'aktivitas', label: 'Aktivitas', href: '/staf?tab=aktivitas', icon: 'bell', active: false },
    { key: 'akun', label: 'Akun', href: '/staf?tab=akun', icon: 'user', active: false },
]
