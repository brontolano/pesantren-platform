/**
 * Hygiene localStorage untuk HP bersama (F0 hardening).
 *
 * Kunci sensitif per-user yang ditulis portal (token API, cache data izin)
 * TIDAK boleh tersisa setelah logout — di perangkat bersama, user berikutnya
 * bisa membaca data user sebelumnya. Daftar kunci dipusatkan di sini;
 * pembersihan dipicu dari app.js setiap kali halaman ter-render TANPA user
 * ter-autentikasi (props Inertia `auth.user` kosong) — mencakup redirect
 * pasca-logout maupun sesi kedaluwarsa.
 */
export const SENSITIVE_STORAGE_KEYS = [
    'sanctum_token', // Bearer token API Disiplin (Pages/PortalGuru/Disiplin/*)
    'gerbang_izin_aktif', // cache offline daftar izin gerbang (Pages/PortalGuru/Gerbang.vue)
]

export function clearSensitiveStorage() {
    try {
        for (const key of SENSITIVE_STORAGE_KEYS) {
            window.localStorage.removeItem(key)
        }
    } catch {
        // Storage bisa tak tersedia (private mode / disabled) — abaikan.
    }
}

/** Bersihkan kunci sensitif bila page props menunjukkan TIDAK ada user login. */
export function clearSensitiveStorageIfGuest(pageProps) {
    if (!pageProps?.auth?.user) {
        clearSensitiveStorage()
    }
}
