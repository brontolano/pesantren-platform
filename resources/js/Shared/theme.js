// Helper tema "Nur" — dark/light lintas semua app Vue.
// Anti-flash awal ditangani inline di app.blade.php; ini utk toggle & baca state.
const KEY = 'nur-theme';

/** 'dark' | 'light' | null (ikut sistem). */
export function getStoredTheme() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
}

/** Apakah saat ini gelap (berdasar class <html>). */
export function isDark() {
    return document.documentElement.classList.contains('dark');
}

/** Set eksplisit 'dark'/'light' (disimpan), lalu terapkan. */
export function setTheme(mode) {
    try { localStorage.setItem(KEY, mode); } catch (e) {}
    document.documentElement.classList.toggle('dark', mode === 'dark');
}

/** Toggle gelap/terang & simpan pilihan pengguna. */
export function toggleTheme() {
    setTheme(isDark() ? 'light' : 'dark');
    return isDark();
}
