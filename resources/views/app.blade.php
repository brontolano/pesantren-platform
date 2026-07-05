<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Dark Mode "Nur" — terapkan SEBELUM CSS/Vue mount (anti-flash).
         OPT-IN dulu: hanya aktif bila pengguna sudah menyetel via toggle
         (localStorage 'nur-theme' === 'dark'). Auto-ikut-sistem (prefers-color-
         scheme) DITUNDA sampai dark tiap app terverifikasi rapi — agar user
         ber-HP gelap tak mendapat layar dark yang belum dipoles. --}}
    <script>
        (function () {
            try {
                if (localStorage.getItem('nur-theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    {{-- 1-aplikasi (Fase 4 fondasi) — PWA scope root: installable dari Beranda + dasar TWA.
         Hanya di konteks tenant; di domain central endpoint ini 404 (diabaikan browser).
         F1: DIKECUALIKAN di path portal yang punya manifest sendiri (guru/wali/spmb/
         keuangan) — layout Vue portal itu menyuntik <link rel="manifest"> miliknya
         sendiri via Inertia <Head> saat mount; tanpa pengecualian ini, DOM akhir bisa
         memuat DUA tag manifest (statis dari sini + dinamis dari layout) — TWA /spmb
         yang live bergantung pada manifest tunggalnya, jadi ini bukan kosmetik. --}}
    @php($punyaManifestSendiri = request()->is('guru*', 'wali*', 'spmb*', 'keuangan*'))
    @if (tenant() && ! $punyaManifestSendiri)
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="{{ \App\Models\Setting::getTenantStringValue('branding_primary_color', '#0f5132') }}">
        <link rel="apple-touch-icon" href="/icon.svg?size=192">
        {{-- Service worker root (Fase 3) — DIGERBANG flag (default OFF). Aktif hanya
             setelah owner uji-perangkat: SW salah bisa membuat app blank/uninstallable. --}}
        @if ((bool) \App\Models\Setting::getTenantValue('pwa.root_sw_enabled', false))
            <script>
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', function () {
                        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
                    });
                }
            </script>
        @endif
    @endif
    <title inertia>{{ tenant()?->name ?? config('app.name', '') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
