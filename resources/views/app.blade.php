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
    {{-- Token Dark Mode "Nur" — INLINE (bukan app.css; lightningcss menghapus
         rule .dark). Override token semantik → semua komponen Vue ikut gelap.
         AlbaKids dipagari tetap terang. --}}
    <style>
        html.dark body{
            --pst-cream:#0d1411;--pst-cream-2:#0d1411;--pst-cream-muted:#12271f;--pst-parchment:#241d10;--pst-white:#161e1a;--pst-ink:#eef2f0;
            --pst-slate-900:#eef2f0;--pst-slate-800:#dbe5e0;--pst-slate-700:#c3d0ca;--pst-slate-600:#a9b8b1;--pst-slate-500:#9fb0a8;--pst-slate-400:#6b7a73;--pst-slate-300:#3a463f;--pst-slate-200:#26312c;--pst-slate-100:#1c2521;--pst-slate-50:#161e1a;
            --pst-border-warm:#26312c;--pst-border-gold:#3a2f18;--pst-nur-600:#2fbf85;--pst-nur-700:#26a874;--pst-nur-50:#12271f;
            --surface-page:#0d1411;--surface-card:#161e1a;--surface-muted:#12271f;--surface-gold:#241d10;
            --text-strong:#eef2f0;--text-body:#dbe5e0;--text-muted:#9fb0a8;--text-faint:#6b7a73;--text-accent:#5fe0a8;
            --border-card:#26312c;--border-cool:#26312c;--border-gold:#3a2f18;
            --brand-primary:#2fbf85;--brand-primary-strong:#26a874;--brand-primary-tint:#12271f;
            --wali-bg:#0d1411;--wali-surface:#161e1a;--wali-ink:#eef2f0;--wali-muted:#9fb0a8;--wali-border:#26312c;
            color-scheme:dark;background:#0d1411;
        }
        /* Retrofit neutral Tailwind hardcoded → dark (surface/text/border umum). */
        html.dark .bg-white,html.dark .bg-gray-50,html.dark .bg-gray-100,html.dark .bg-slate-50,html.dark .bg-slate-100,html.dark .bg-neutral-50{background-color:#161e1a!important}
        html.dark .text-gray-900,html.dark .text-gray-800,html.dark .text-slate-900,html.dark .text-slate-800,html.dark .text-black{color:#eef2f0!important}
        html.dark .text-gray-600,html.dark .text-gray-500,html.dark .text-slate-600,html.dark .text-slate-500{color:#9fb0a8!important}
        html.dark .border-gray-100,html.dark .border-gray-200,html.dark .border-slate-100,html.dark .border-slate-200,html.dark .ring-gray-200{border-color:#26312c!important}
        /* Pagar AlbaKids: tetap terang. */
        html.dark body.tenant-albakids{--pst-cream:#fffaf3;--pst-white:#fff;--pst-ink:#111;--surface-page:#fffaf3;--surface-card:#fff;--text-strong:#111;--text-body:#1e293b;--text-muted:#64748b;--border-card:#efe7da;--wali-bg:#fffaf3;--wali-surface:#fff;--wali-ink:#111;color-scheme:light;background:#fffaf3}
    </style>
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
    {{-- Tombol tema mengambang "Nur" — hadir di semua halaman Vue tanpa mengedit
         tiap layout. Vanilla JS (di luar Vue), persist localStorage 'nur-theme'.
         AlbaKids dikecualikan (dark-nya di-reset di app.css). --}}
    <button id="nurThemeFab" type="button" aria-label="Ganti tema gelap/terang"
        style="position:fixed;right:14px;bottom:calc(84px + env(safe-area-inset-bottom));z-index:60;width:44px;height:44px;border-radius:9999px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;background:var(--brand-primary,#0b6e4f);color:#fff;box-shadow:0 8px 20px -8px rgba(0,0,0,.5)">
        <svg id="nurThemeIcon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
    </button>
    <script>
        (function () {
            var fab = document.getElementById('nurThemeFab'), icon = document.getElementById('nurThemeIcon');
            var moon = '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>';
            var sun = '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>';
            function sync() { icon.innerHTML = document.documentElement.classList.contains('dark') ? sun : moon; }
            sync();
            fab.addEventListener('click', function () {
                var d = document.documentElement.classList.toggle('dark');
                try { localStorage.setItem('nur-theme', d ? 'dark' : 'light'); } catch (e) {}
                sync();
            });
        })();
    </script>
</body>
</html>
