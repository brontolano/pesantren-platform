{{-- <head> bersama untuk halaman pintu sistem (login & hub). Mandiri (tanpa
     Vite/app.css), font via bunny.net, token :root diturunkan dari brand tenant.
     Vars: $pesantren, $primaryColor, $title (opsional). --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>{{ ($title ?? 'Portal Sistem') . ' — ' . $pesantren }}</title>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&family=amiri:400,700" rel="stylesheet">
<style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
        --brand:{{ $primaryColor }};
        --brand-strong:#0b3d27;
        --brand-strong:color-mix(in srgb, var(--brand) 78%, #06281a);
        --gold:#c9a24a;--gold-soft:#e7d3a1;
        --cream:#fffaf3;--ink:#16241c;--muted:#5b6b62;
        --surface:#ffffff;--border:#ece3d5;
        --shadow-soft:0 16px 44px -24px rgba(11,40,26,.30);
        --shadow-brand:0 12px 28px -14px rgba(11,40,26,.45);
        --shadow-brand:0 12px 28px -14px color-mix(in srgb, var(--brand) 55%, transparent);
        --radius:22px;
    }
    html,body{min-height:100dvh}
    body{font-family:'Plus Jakarta Sans',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased;background:var(--cream)}
    .arabic{font-family:'Amiri',serif}
    a{color:inherit;text-decoration:none}
    .brandmark{display:grid;place-items:center;background:#fff;border-radius:16px;overflow:hidden}
    .brandmark img{width:100%;height:100%;object-fit:contain}
    .brandmark svg{stroke:var(--brand);fill:none;stroke-width:1.5;width:60%;height:60%}
    :focus-visible{outline:2px solid var(--brand);outline-offset:2px}
    @media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}}
</style>
