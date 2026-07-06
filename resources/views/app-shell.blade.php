<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#0b6e4f">
<title>{{ $namaPesantren }}</title>
{{-- PWA / "Tambahkan ke Layar Utama" --}}
<link rel="manifest" href="/beranda/manifest.webmanifest">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $namaPesantren }}">
@if($logoUrl)<link rel="apple-touch-icon" href="{{ $logoUrl }}"><link rel="icon" href="{{ $logoUrl }}">@endif
<script>try{if(localStorage.getItem('nur-theme')==='dark')document.documentElement.classList.add('dark');}catch(e){}try{if(/PesantrenApp/i.test(navigator.userAgent))document.documentElement.classList.add('inapp');}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Amiri&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--nur:#0b6e4f;--nur-strong:#085840;--nur-tint:#ecfdf6;--nur-100:#d1faea;--terra:#c14f2a;--terra-tint:#fef3ee;--gold:#c9a24a;--gold-soft:#e7d3a1;--goldb:#fef9c3;--goldink:#7a5e1e;--ink:#111;--sub:#64748b;--faint:#94a3b8;--line:#efe7da;--card:#fff;--bg:#fffaf3;--grad:linear-gradient(160deg,#17a179 0%,#0b6e4f 48%,#062a20 100%);--r:16px;--r2:20px;--rs:12px;--f:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;--sh:inset 0 0 0 1px var(--line),0 2px 4px -2px rgba(17,17,17,.06),0 12px 28px -16px rgba(17,17,17,.18)}
html.dark{--nur:#2fbf85;--nur-strong:#26a874;--nur-tint:#12271f;--nur-100:#1c3a2e;--terra-tint:#33180f;--goldb:#2a230f;--goldink:#e7c877;--ink:#eef2f0;--sub:#9fb0a8;--faint:#6b7a73;--line:#26312c;--card:#161e1a;--bg:#0d1411;--sh:inset 0 0 0 1px var(--line),0 10px 26px -18px rgba(0,0,0,.7)}
html.dark body{background:var(--bg)}
html.dark .c-violet{background:#241c3a;color:#c4b5fd}html.dark .c-sky{background:#12293a;color:#7dd3fc}
html,body{height:100%;font-family:var(--f);background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;-webkit-user-select:none;user-select:none}
a{text-decoration:none;color:inherit}button{font-family:inherit;color:inherit}
.app{max-width:540px;margin:0 auto;min-height:100%;position:relative}
.scroll-area{overflow-y:auto;height:100vh;padding-bottom:96px}
.tab{display:none}.tab.on{display:block;animation:fade .25s ease}
@keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@media(prefers-reduced-motion:reduce){*{animation-duration:.001ms!important;transition-duration:.001ms!important}}
.c-nur{background:var(--nur-tint);color:var(--nur)}.c-gold{background:var(--goldb);color:var(--goldink)}.c-violet{background:#ede9fe;color:#5b21b6}.c-sky{background:#e0f2fe;color:#075985}.c-terra{background:var(--terra-tint);color:var(--terra)}
.hero{background:var(--grad);color:#fff;padding:22px 18px 20px;position:relative;overflow:hidden;border-bottom-left-radius:28px;border-bottom-right-radius:28px;box-shadow:0 12px 30px -18px rgba(6,42,32,.7)}
.hero.sm{padding-bottom:18px}
.hero-pat{position:absolute;inset:0;background-image:url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2244%22 height=%2244%22 viewBox=%220 0 44 44%22%3E%3Cg fill=%22none%22 stroke=%22%23ffffff%22 stroke-width=%221%22 opacity=%220.5%22%3E%3Crect x=%2213%22 y=%2213%22 width=%2218%22 height=%2218%22/%3E%3Crect x=%2213%22 y=%2213%22 width=%2218%22 height=%2218%22 transform=%22rotate(45 22 22)%22/%3E%3C/g%3E%3C/svg%3E");background-size:44px 44px;opacity:.09}
.hero-glow{position:absolute;right:-46px;top:-46px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(231,211,161,.22),transparent 66%)}
.hero-top{display:flex;align-items:center;gap:12px;position:relative}
.logo{width:50px;height:50px;border-radius:15px;overflow:hidden;background:rgba(255,255,255,.16);border:1px solid rgba(231,211,161,.35);display:flex;align-items:center;justify-content:center;font-size:26px;flex:0 0 auto}
.logo img{width:100%;height:100%;object-fit:cover}
.hero-info{flex:1;min-width:0}.hero-name{font-size:18px;font-weight:800;line-height:1.15;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.hero-tag{font-size:11.5px;color:#c9f2e2;margin-top:2px;font-weight:500}
.hero-h1{font-size:20px;font-weight:800;position:relative}.hero-h2{font-size:12px;color:#c9f2e2;position:relative;margin-top:3px;font-weight:500}
.masuk{display:flex;align-items:center;gap:6px;background:var(--gold-soft);color:#5a4415;border:none;border-radius:99px;padding:8px 14px;font-size:12.5px;font-weight:800;cursor:pointer}.masuk svg{width:15px;height:15px}
.datestrip{display:flex;align-items:center;justify-content:space-between;margin-top:14px;position:relative;font-size:11.5px;color:#c9f2e2;font-weight:600;gap:8px}
.datestrip b{color:#fff;font-weight:800}.datestrip .cd{background:rgba(255,255,255,.1);border-radius:99px;padding:3px 10px;white-space:nowrap}
.datestrip .l{display:flex;align-items:center;gap:5px;min-width:0}.datestrip .l b{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hairline{height:1px;margin:14px 0 0;background:linear-gradient(90deg,transparent,var(--gold-soft),transparent);opacity:.55;position:relative}
.prayer{display:flex;gap:6px;margin-top:14px;position:relative}
.pcell{flex:1;text-align:center;padding:9px 2px;border-radius:13px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08)}
.pcell.next{background:rgba(231,211,161,.16);border:1px solid rgba(231,211,161,.5)}
.pcell .pn{font-size:10px;font-weight:700;color:#c9f2e2}.pcell.next .pn{color:#fff}
.pcell .pt{font-size:13px;font-weight:800;margin-top:3px;font-variant-numeric:tabular-nums}
.pcell .pnext{font-size:8.5px;font-weight:800;color:#0b6e4f;background:var(--gold-soft);border-radius:99px;padding:1px 6px;margin-top:4px;display:inline-block}
.sec{padding:20px 16px 0}.sec-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.sec-title{font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px}.sec-title::before{content:"";width:4px;height:15px;border-radius:99px;background:var(--nur)}
.sec-link{font-size:12px;font-weight:800;color:var(--nur);background:none;border:none;display:flex;align-items:center;gap:1px}
.about{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}
.ab{background:var(--card);border-radius:var(--r);padding:14px 4px 11px;display:flex;flex-direction:column;align-items:center;gap:8px;box-shadow:var(--sh);text-align:center}
.ab-ic{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center}.ab-lbl{font-size:11px;font-weight:700;line-height:1.15}
.spmb{display:block;margin:0 16px;border-radius:var(--r2);overflow:hidden;position:relative;background:linear-gradient(125deg,#8f6a1e,#5a3d0e);box-shadow:var(--sh)}
.spmb-pat{position:absolute;inset:0;background-image:url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2244%22 height=%2244%22 viewBox=%220 0 44 44%22%3E%3Cg fill=%22none%22 stroke=%22%23ffffff%22 stroke-width=%221%22 opacity=%220.5%22%3E%3Crect x=%2213%22 y=%2213%22 width=%2218%22 height=%2218%22/%3E%3Crect x=%2213%22 y=%2213%22 width=%2218%22 height=%2218%22 transform=%22rotate(45 22 22)%22/%3E%3C/g%3E%3C/svg%3E");background-size:44px 44px;opacity:.16}
.spmb-in{position:relative;padding:18px;color:#fff;display:flex;align-items:center;gap:14px}
.spmb-in .t{font-size:16px;font-weight:800;line-height:1.2}.spmb-in .s{font-size:11.5px;color:rgba(255,255,255,.9);margin-top:3px}
.spmb-btn{margin-left:auto;background:#fff;color:#8a6516;border-radius:99px;padding:9px 15px;font-size:12.5px;font-weight:800;white-space:nowrap}
.gal{display:flex;gap:9px;overflow-x:auto;padding:0 16px 2px;scrollbar-width:none}.gal::-webkit-scrollbar{display:none}
.gal .ph{width:112px;height:112px;border-radius:var(--r);flex:0 0 auto;box-shadow:var(--sh);background:linear-gradient(135deg,var(--nur-100),#e0f2fe);display:flex;align-items:center;justify-content:center;color:var(--nur)}
.tv{margin:0 16px;border-radius:var(--r);overflow:hidden;box-shadow:var(--sh);position:relative;aspect-ratio:16/9;background:linear-gradient(135deg,#0b6e4f,#17a179);display:flex;align-items:center;justify-content:center;color:#fff}
.tv .live{position:absolute;top:12px;left:12px;background:var(--terra);color:#fff;font-size:10px;font-weight:800;border-radius:99px;padding:3px 9px;display:flex;align-items:center;gap:5px}
.tv .live::before{content:"";width:6px;height:6px;border-radius:50%;background:#fff}
.clist{display:flex;flex-direction:column;gap:9px}
.crow{display:flex;align-items:center;gap:12px;background:var(--card);border-radius:var(--r);padding:13px 14px;box-shadow:var(--sh)}
.crow-ic{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.crow-body{flex:1;min-width:0}.crow-t{font-size:13.5px;font-weight:700;line-height:1.25}.crow-s{font-size:11.5px;color:var(--sub);margin-top:2px}
.donasi{margin:20px 16px 0;border-radius:var(--r2);background:var(--nur-tint);border:1px solid var(--nur-100);padding:18px;display:flex;align-items:center;gap:14px}
.donasi-ic{width:46px;height:46px;border-radius:14px;background:var(--card);color:var(--nur);display:flex;align-items:center;justify-content:center;flex:0 0 auto;box-shadow:var(--sh)}
.donasi .t{font-size:14.5px;font-weight:800}.donasi .s{font-size:11.5px;color:var(--sub);margin-top:2px}
.donasi-btn{margin-left:auto;background:var(--nur);color:#fff;border-radius:99px;padding:10px 16px;font-size:12.5px;font-weight:800;white-space:nowrap}
.prayer-list{margin:0 16px;background:var(--card);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden}
.pli{display:flex;align-items:center;gap:12px;padding:13px 15px;border-bottom:1px solid var(--line)}.pli:last-child{border-bottom:none}.pli.now{background:var(--nur-tint)}
.pli .pic{width:34px;height:34px;border-radius:10px;background:var(--nur-tint);color:var(--nur);display:flex;align-items:center;justify-content:center;flex:0 0 auto}.pli.now .pic{background:var(--nur);color:#fff}
.pli .pnm{flex:1;font-weight:700;font-size:14px}.pli .ptm{font-weight:800;font-size:15px;font-variant-numeric:tabular-nums}
.pli .badge{font-size:9.5px;font-weight:800;color:#fff;background:var(--nur);border-radius:99px;padding:2px 8px;margin-left:8px}
.kompas{margin:0 16px;background:var(--card);border-radius:var(--r2);box-shadow:var(--sh);padding:22px;text-align:center}
.kompas .lbl{font-size:12px;color:var(--sub);font-weight:600}.kompas .lbl b{color:var(--nur)}
.dial{width:210px;height:210px;margin:16px auto 8px;border-radius:50%;position:relative;background:radial-gradient(circle at 50% 42%,var(--card),var(--nur-tint));box-shadow:inset 0 0 0 2px var(--line),inset 0 0 30px -10px rgba(11,110,79,.15)}
.dial .card-pt{position:absolute;font-size:12px;font-weight:800;color:var(--faint)}
.dial .n{top:10px;left:50%;transform:translateX(-50%);color:var(--terra)}.dial .s{bottom:10px;left:50%;transform:translateX(-50%)}.dial .e{right:12px;top:50%;transform:translateY(-50%)}.dial .w{left:12px;top:50%;transform:translateY(-50%)}
.qibla-arm{position:absolute;left:50%;top:50%;width:0;height:0;transition:transform .18s ease-out}
.qibla-arm .needle{position:absolute;left:-2.5px;top:-86px;width:5px;height:86px;background:linear-gradient(var(--nur),rgba(11,110,79,.12));border-radius:5px}
.qibla-arm .kaaba{position:absolute;left:-17px;top:-104px;width:34px;height:34px;border-radius:10px;background:#1a1a1a;border:2px solid var(--gold);display:flex;align-items:center;justify-content:center;font-size:17px;box-shadow:0 8px 16px -6px rgba(0,0,0,.5)}
.dial .center{position:absolute;left:50%;top:50%;width:14px;height:14px;border-radius:50%;background:var(--nur);transform:translate(-50%,-50%);box-shadow:0 0 0 4px var(--card)}
.kompas .hint{font-size:11px;color:var(--faint);margin-top:6px;line-height:1.4}
.kompas .kbtn{margin-top:12px;background:var(--nur-tint);color:var(--nur);border:none;border-radius:99px;padding:9px 16px;font-size:12.5px;font-weight:800;cursor:pointer}
.gpsbtn{display:flex;align-items:center;justify-content:center;gap:8px;width:calc(100% - 32px);margin:16px 16px 0;background:var(--nur);color:#fff;border:none;border-radius:14px;padding:13px;font-size:13.5px;font-weight:800;cursor:pointer}
.gpsbtn:disabled{opacity:.6}
.gpsnote{margin:8px 16px 0;font-size:11.5px;font-weight:600;color:var(--faint);text-align:center;line-height:1.4}
.svc{margin:0 16px;background:var(--card);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden}
.sbtn{display:flex;align-items:center;gap:12px;padding:14px 15px;border-bottom:1px solid var(--line)}.sbtn:last-child{border-bottom:none}
.sbtn .sic{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.sbtn .snm{font-weight:700;font-size:14px}.sbtn .ssub{font-size:11.5px;color:var(--sub);margin-top:1px}
.art{display:flex;gap:13px;background:var(--card);border-radius:var(--r);box-shadow:var(--sh);padding:12px;margin-bottom:10px}
.art .thumb{width:88px;height:72px;border-radius:12px;flex:0 0 auto;object-fit:cover;background:linear-gradient(135deg,var(--nur-100),#e0f2fe);display:flex;align-items:center;justify-content:center;color:var(--nur)}
.art .at{font-weight:800;font-size:13.5px;line-height:1.25}.art .ax{font-size:11.5px;color:var(--sub);margin-top:3px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.art .ad{font-size:10.5px;color:var(--faint);font-weight:700;margin-top:5px}
.empty{text-align:center;padding:40px 24px;color:var(--sub);font-size:13.5px}
.nav{position:fixed;bottom:0;left:0;right:0;max-width:540px;margin:0 auto;background:rgba(255,255,255,.96);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-top:1px solid var(--line);display:flex;padding:7px 8px max(8px,env(safe-area-inset-bottom));z-index:30;box-shadow:0 -8px 24px -18px rgba(17,17,17,.3)}
html.dark .nav{background:rgba(18,24,21,.96)}
/* TWA/aplikasi terpasang (standalone): env(safe-area-inset-bottom) sering over-report
   di perangkat tombol-3 → ruang kosong besar di bawah ikon. Batasi agar nav rapat. */
@media(display-mode:standalone),(display-mode:fullscreen),(display-mode:minimal-ui){.nav{padding-bottom:10px}.scroll-area{padding-bottom:84px}}
/* WebView app (userAgent 'PesantrenApp') tak melapor display-mode:standalone → deteksi via JS class .inapp */
html.inapp .nav{padding-bottom:10px}
html.inapp .scroll-area{padding-bottom:84px}
.nav button,.nav a{flex:1;border:none;background:none;cursor:pointer;padding:0;display:flex;flex-direction:column;align-items:center;gap:5px;font-size:10.5px;font-weight:800;color:var(--faint)}
.nav button.on{color:var(--nur)}
.nav .ic{width:52px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:99px;transition:background .2s}
.nav button.on .ic{background:var(--nur-tint)}
.fab{position:fixed;right:14px;bottom:calc(84px + env(safe-area-inset-bottom));z-index:40;width:44px;height:44px;border-radius:99px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;background:var(--nur);color:#fff;box-shadow:0 8px 20px -8px rgba(0,0,0,.5)}
</style>
</head>
<body>
@php
$I=[
 'login'=>'<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><path d="M15 12H3"/>',
 'moon'=>'<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>','sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
 'cal'=>'<path d="M8 2v4"/><path d="M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/>',
 'chevron'=>'<path d="m9 18 6-6-6-6"/>','arrow'=>'<path d="m9 18 6-6-6-6"/>',
 'image'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
 'play'=>'<circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>',
 'heart'=>'<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.5 4.04 3 5.5l7 7Z"/>',
 'home'=>'<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
 'user'=>'<circle cx="12" cy="7" r="4"/><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>',
 'users'=>'<circle cx="9" cy="7" r="4"/><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
 'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
 'mosque'=>'<path d="M4 22V11l8-6 8 6v11"/><path d="M8 22v-5a4 4 0 0 1 8 0v5"/><path d="M2 22h20"/>',
 'news'=>'<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8M15 18h-5M10 6h8v4h-8Z"/>',
 'book'=>'<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
 'gift'=>'<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
 'moonp'=>'<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
];
$svg=fn($n,$s=20)=>'<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.($I[$n]??$I['grid']).'</svg>';
$jadwal = collect($ibadah['jadwal'] ?? []);
// tentukan sholat berikutnya (server) — untuk highlight strip
$nowMin = (int) now()->format('G')*60 + (int) now()->format('i');
$nextKey = null;
foreach ($jadwal as $s) {
    if (preg_match('/(\d{1,2}):(\d{2})/', $s['time'] ?? '', $mm)) {
        if (((int)$mm[1]*60 + (int)$mm[2]) > $nowMin) { $nextKey = $s['label'] ?? null; break; }
    }
}
if ($nextKey === null && $jadwal->isNotEmpty()) { $nextKey = $jadwal->first()['label'] ?? null; }
$inisial = mb_strtoupper(mb_substr($namaPesantren, 0, 1));
$prJson = $jadwal->map(fn($s)=>['n'=>$s['label']??'','t'=>$s['time']??''])->values();
@endphp
<div class="app"><div class="scroll-area">

{{-- ===== BERANDA ===== --}}
<div class="tab on" id="tab-beranda">
  <div class="hero">
    <div class="hero-pat"></div><div class="hero-glow"></div>
    <div class="hero-top">
      <div class="logo">@if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else🕌@endif</div>
      <div class="hero-info"><div class="hero-name">{{ $namaPesantren }}</div><div class="hero-tag">{{ $tagline }}</div></div>
      <a class="masuk" href="{{ $masukUrl }}">{!! $svg('login',15) !!} Masuk</a>
    </div>
    <div class="datestrip"><span class="l">{!! $svg('cal',13) !!} <b>{{ $tanggal['masehi'] ?? '' }}</b></span>@if(!empty($tanggal['hijriah']))<span class="cd">{{ $tanggal['hijriah'] }}</span>@endif</div>
    @if($jadwal->isNotEmpty())
      <div class="hairline"></div>
      <div class="prayer">
        @foreach($jadwal as $s)
          <div class="pcell {{ ($s['label']??'')===$nextKey ? 'next' : '' }}" data-nm="{{ $s['label']??'' }}"><div class="pn">{{ $s['label']??'' }}</div><div class="pt">{{ $s['time']??'' }}</div>@if(($s['label']??'')===$nextKey)<div class="pnext" id="cd">—</div>@endif</div>
        @endforeach
      </div>
    @endif
  </div>

  <div class="sec"><div class="sec-head"><div class="sec-title">Tentang Pesantren</div></div>
    <div class="about">
      <a class="ab" href="{{ $cfg['app_profil_pondok_url'] }}"><span class="ab-ic c-nur">{!! $svg('home',22) !!}</span><span class="ab-lbl">Profil Pondok</span></a>
      <a class="ab" href="{{ $cfg['app_profil_abuya_url'] }}"><span class="ab-ic c-gold">{!! $svg('user',22) !!}</span><span class="ab-lbl">Profil Abuya</span></a>
      <a class="ab" href="{{ $cfg['app_profil_guru_url'] }}"><span class="ab-ic c-violet">{!! $svg('users',22) !!}</span><span class="ab-lbl">Dewan Guru</span></a>
      <a class="ab" href="{{ $cfg['app_struktur_url'] }}"><span class="ab-ic c-sky">{!! $svg('grid',22) !!}</span><span class="ab-lbl">Struktur</span></a>
    </div>
  </div>

  <div class="sec"><a class="spmb" href="/spmb"><div class="spmb-pat"></div><div class="spmb-in"><div><div class="t">Penerimaan Santri Baru</div><div class="s">Daftar online — cek gelombang & jadwal</div></div><span class="spmb-btn">Daftar</span></div></a></div>

  @if(!empty($cfg['app_instagram_url']))
  <div class="sec"><div class="sec-head"><div class="sec-title">Galeri Foto</div><a class="sec-link" href="{{ $cfg['app_instagram_url'] }}">Instagram {!! $svg('chevron',14) !!}</a></div>
    <div class="gal">@for($i=0;$i<5;$i++)<a class="ph" href="{{ $cfg['app_instagram_url'] }}">{!! $svg('image',26) !!}</a>@endfor</div>
  </div>
  @endif

  @if(!empty($cfg['app_youtube_url']))
  <div class="sec"><div class="sec-head"><div class="sec-title">{{ $namaPesantren }} TV</div><a class="sec-link" href="{{ $cfg['app_youtube_url'] }}">YouTube {!! $svg('chevron',14) !!}</a></div>
    <a class="tv" href="{{ $cfg['app_youtube_url'] }}"><span class="live">LIVE</span>{!! $svg('play',54) !!}</a>
  </div>
  @endif

  @if($artikel->isNotEmpty())
  <div class="sec"><div class="sec-head"><div class="sec-title">Kabar Terbaru</div><button class="sec-link" onclick="tab('kabar')">Semua {!! $svg('chevron',14) !!}</button></div>
    <div class="clist">
      @foreach($artikel->take(2) as $a)
        <div class="crow"><span class="crow-ic c-gold">{!! $svg('cal',20) !!}</span><div class="crow-body"><div class="crow-t">{{ $a->title }}</div>@if($a->published_at)<div class="crow-s">{{ $a->published_at->locale('id')->translatedFormat('d M Y') }}</div>@endif</div><span style="color:var(--faint)">{!! $svg('arrow',16) !!}</span></div>
      @endforeach
    </div>
  </div>
  @endif

  @if(!empty($cfg['app_donasi_url']))
  <a class="donasi" href="{{ $cfg['app_donasi_url'] }}"><span class="donasi-ic">{!! $svg('heart',22) !!}</span><div><div class="t">Infak & Donasi</div><div class="s">Dukung pendidikan santri</div></div><span class="donasi-btn">Salurkan</span></a>
  @endif
  <div style="text-align:center;font-size:9px;color:var(--faint);padding:10px 0 2px;letter-spacing:.04em">Versi {{ $appBuild ?? '-' }}</div>
  <div style="height:12px"></div>
</div>

{{-- ===== IBADAH ===== --}}
<div class="tab" id="tab-ibadah">
  <div class="hero sm">
    <div class="hero-pat"></div><div class="hero-glow"></div>
    <div class="hero-h1">Ibadah</div><div class="hero-h2">Jadwal sholat, arah kiblat & layanan</div>
    <div class="datestrip"><span class="l">{!! $svg('cal',13) !!} <b>{{ $tanggal['masehi'] ?? '' }}</b></span>@if(!empty($tanggal['hijriah']))<span class="cd">{{ $tanggal['hijriah'] }}</span>@endif</div>
  </div>
  @php $adaKiblat = ($ibadah['kiblat'] ?? null) !== null; @endphp
  {{-- Tombol GPS: hitung jadwal & kiblat dari lokasi PERANGKAT (jalan walau admin belum set lokasi) --}}
  <button class="gpsbtn" id="btnGps" type="button">{!! $svg('mosque',17) !!} Pakai lokasi saya (GPS)</button>
  <div class="gpsnote" id="gpsNote"></div>

  {{-- Jadwal Sholat (server-render bila lokasi pesantren ada; kalau tidak, diisi GPS) --}}
  <div class="sec" id="secPrayer" @if($jadwal->isEmpty())style="display:none"@endif><div class="sec-head"><div class="sec-title">Jadwal Sholat</div></div></div>
  <div class="prayer-list" id="prayerList" @if($jadwal->isEmpty())style="display:none"@endif>
    @foreach($jadwal as $s)
      <div class="pli {{ ($s['label']??'')===$nextKey ? 'now' : '' }}"><span class="pic">{!! $svg('mosque',18) !!}</span><span class="pnm">{{ $s['label']??'' }}</span>@if(($s['label']??'')===$nextKey)<span class="badge">berikutnya</span>@endif<span class="ptm">{{ $s['time']??'' }}</span></div>
    @endforeach
  </div>

  {{-- Arah Kiblat --}}
  <div class="sec" id="secQibla" @unless($adaKiblat)style="display:none"@endunless><div class="sec-head"><div class="sec-title">Arah Kiblat</div></div></div>
  <div class="kompas" id="kiblatCard" data-qibla="{{ $ibadah['kiblat'] ?? '' }}" @unless($adaKiblat)style="display:none"@endunless>
    <div class="lbl">Arah Kiblat · <b id="qiblaDeg">{{ $adaKiblat ? $ibadah['kiblat'].'° dari utara' : '—' }}</b></div>
    <div class="dial">
      <span class="card-pt n">U</span><span class="card-pt s">S</span><span class="card-pt e">T</span><span class="card-pt w">B</span>
      <div class="qibla-arm" id="qiblaArm" style="transform:rotate({{ $ibadah['kiblat'] ?? 0 }}deg)"><div class="needle"></div><div class="kaaba">🕋</div></div><div class="center"></div>
    </div>
    <div class="hint" id="kompasHint">Arahkan HP mengikuti jarum hijau menuju 🕋 — itulah arah kiblat.</div>
    <button class="kbtn" id="btnKompas" style="display:none">Aktifkan Kompas</button>
  </div>

  {{-- Empty hanya bila TAK ada data server (GPS akan mengisinya) --}}
  <div class="empty" id="ibadahEmpty" @if($jadwal->isNotEmpty())style="display:none"@endif>Jadwal belum diatur admin — ketuk <b>Pakai lokasi saya</b> untuk memakai GPS perangkat.</div>

  @php $adaLayanan = !empty($cfg['app_jadwal_kajian_url']) || !empty($cfg['app_donasi_url']) || !empty($cfg['app_zakat_wakaf_url']); @endphp
  @if($adaLayanan)
  <div class="sec"><div class="sec-head"><div class="sec-title">Layanan Ibadah</div></div></div>
  <div class="svc">
    @if(!empty($cfg['app_jadwal_kajian_url']))<a class="sbtn" href="{{ $cfg['app_jadwal_kajian_url'] }}"><span class="sic c-nur">{!! $svg('book',18) !!}</span><span style="flex:1"><span class="snm">Jadwal Kajian</span><div class="ssub">Majelis & pengajian</div></span><span style="color:var(--faint)">{!! $svg('chevron',16) !!}</span></a>@endif
    @if(!empty($cfg['app_donasi_url']))<a class="sbtn" href="{{ $cfg['app_donasi_url'] }}"><span class="sic c-terra">{!! $svg('heart',18) !!}</span><span style="flex:1"><span class="snm">Donasi / Infaq</span><div class="ssub">Salurkan kebaikan</div></span><span style="color:var(--faint)">{!! $svg('chevron',16) !!}</span></a>@endif
    @if(!empty($cfg['app_zakat_wakaf_url']))<a class="sbtn" href="{{ $cfg['app_zakat_wakaf_url'] }}"><span class="sic c-gold">{!! $svg('gift',18) !!}</span><span style="flex:1"><span class="snm">Zakat & Wakaf</span><div class="ssub">Tunaikan zakat & wakaf</div></span><span style="color:var(--faint)">{!! $svg('chevron',16) !!}</span></a>@endif
  </div>
  @endif
  <div style="height:16px"></div>
</div>

{{-- ===== KABAR ===== --}}
<div class="tab" id="tab-kabar">
  <div class="hero sm"><div class="hero-pat"></div><div class="hero-glow"></div><div class="hero-h1">Kabar</div><div class="hero-h2">Berita & pengumuman pesantren</div></div>
  <div class="sec">
    @forelse($artikel as $a)
      <div class="art"><span class="thumb">@if($a->featured_image_url)<img src="{{ $a->featured_image_url }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:12px">@else{!! $svg('image',24) !!}@endif</span><div style="min-width:0"><div class="at">{{ $a->title }}</div>@if($a->excerpt)<div class="ax">{{ $a->excerpt }}</div>@endif<div class="ad">{{ optional($a->published_at)->locale('id')->translatedFormat('d M Y') }}</div></div></div>
    @empty
      <div class="empty">Belum ada kabar dipublikasikan.</div>
    @endforelse
  </div>
  <div style="height:16px"></div>
</div>

</div>

<button class="fab" id="fabTema" aria-label="Ganti tema gelap/terang">{!! $svg('moon',20) !!}</button>
<nav class="nav">
  <button class="on" data-tab="beranda" onclick="tab('beranda')"><span class="ic">{!! $svg('home',22) !!}</span>Beranda</button>
  <button data-tab="ibadah" onclick="tab('ibadah')"><span class="ic">{!! $svg('mosque',22) !!}</span>Ibadah</button>
  <button data-tab="kabar" onclick="tab('kabar')"><span class="ic">{!! $svg('news',22) !!}</span>Kabar</button>
  <a href="{{ $masukUrl }}"><span class="ic">{!! $svg('login',22) !!}</span>Masuk</a>
</nav>
</div>

<script>
function tab(n){document.querySelectorAll('.tab').forEach(t=>t.classList.toggle('on',t.id==='tab-'+n));document.querySelectorAll('.nav button').forEach(b=>b.classList.toggle('on',b.dataset.tab===n));document.querySelector('.scroll-area').scrollTop=0;}
// toggle tema
(function(){var f=document.getElementById('fabTema');if(!f)return;var moon='{!! $svg('moon',20) !!}',sun='{!! $svg('sun',20) !!}';function sync(){f.innerHTML=document.documentElement.classList.contains('dark')?sun:moon;}sync();f.addEventListener('click',function(){var d=document.documentElement.classList.toggle('dark');try{localStorage.setItem('nur-theme',d?'dark':'light');}catch(e){}sync();});})();
// hitung mundur sholat berikutnya (.pnext)
(function(){var PR=@json($prJson);var cd=document.getElementById('cd');if(!cd||!PR.length)return;function pad(n){return String(n).padStart(2,'0');}function tick(){var now=new Date(),target=null;for(var i=0;i<PR.length;i++){var a=(PR[i].t||'').match(/(\d{1,2}):(\d{2})/);if(!a)continue;var t=new Date(now);t.setHours(+a[1],+a[2],0,0);if(t>now){target=t;break;}}if(!target){var a2=(PR[0].t||'').match(/(\d{1,2}):(\d{2})/);target=new Date(now);target.setDate(target.getDate()+1);if(a2)target.setHours(+a2[1],+a2[2],0,0);}var d=Math.max(0,target-now),hh=Math.floor(d/3.6e6),mm=Math.floor(d%3.6e6/6e4);cd.textContent=(hh>0?hh+'j ':'')+mm+'m';}tick();setInterval(tick,30000);})();
// kompas kiblat (DeviceOrientation) — arm menunjuk arah kiblat relatif heading HP
(function(){var card=document.getElementById('kiblatCard');if(!card)return;var qibla=function(){return parseFloat(card.dataset.qibla)||0;};var arm=document.getElementById('qiblaArm'),hint=document.getElementById('kompasHint'),btn=document.getElementById('btnKompas');var norm=function(a){return((a%360)+360)%360;};var smooth=null,got=false,absSeen=false;function screenAngle(){return(screen.orientation&&screen.orientation.angle)||window.orientation||0;}function apply(h){h=norm(h+screenAngle());if(smooth==null)smooth=h;else{var d=((h-smooth+540)%360)-180;smooth=norm(smooth+d*0.18);}var rel=norm(qibla()-smooth);arm.style.transform='rotate('+rel+'deg)';var selisih=Math.abs(((rel+540)%360)-180);hint.textContent=selisih<=6?'✓ Tepat menghadap kiblat.':'Putar HP perlahan hingga jarum 🕋 lurus ke atas.';hint.style.color=selisih<=6?'var(--nur)':'';}function handle(e){var h=null;if(e.webkitCompassHeading!=null)h=e.webkitCompassHeading;else if(e.alpha!=null){if(e.absolute){absSeen=true;}else if(absSeen){return;}h=360-e.alpha;}if(h==null)return;got=true;apply(h);}function start(){window.addEventListener('deviceorientationabsolute',function(e){absSeen=true;handle(e);},true);window.addEventListener('deviceorientation',handle,true);setTimeout(function(){if(!got){hint.textContent='Kompas tak tersedia — arah kiblat '+Math.round(qibla())+'° dari utara, gunakan kompas terpisah.';}},2000);}if(typeof DeviceOrientationEvent!=='undefined'&&typeof DeviceOrientationEvent.requestPermission==='function'){btn.style.display='';btn.onclick=function(){DeviceOrientationEvent.requestPermission().then(function(s){if(s==='granted'){btn.style.display='none';start();}}).catch(function(){});};}else{start();}})();
// GPS — jadwal sholat & arah kiblat dari lokasi PERANGKAT (opsional; jalan walau admin belum set lokasi tenant)
(function(){var btn=document.getElementById('btnGps'),note=document.getElementById('gpsNote');if(!btn)return;var MOSQUE={!! json_encode($svg('mosque',18)) !!};
function tzHours(){return -(new Date().getTimezoneOffset())/60;}
function fill(d){var arr=d.jadwalSholat||[];var pl=document.getElementById('prayerList'),sec=document.getElementById('secPrayer'),emp=document.getElementById('ibadahEmpty');
if(arr.length){var now=new Date(),hm=now.getHours()*60+now.getMinutes(),ni=-1;for(var i=0;i<arr.length;i++){var t=(arr[i].time||'').split(':');if(t.length===2){var mm=(+t[0])*60+(+t[1]);if(mm>=hm){ni=i;break;}}}var h='';for(var j=0;j<arr.length;j++){var on=j===ni;h+='<div class="pli'+(on?' now':'')+'"><span class="pic">'+MOSQUE+'</span><span class="pnm">'+arr[j].label+'</span>'+(on?'<span class="badge">berikutnya</span>':'')+'<span class="ptm">'+arr[j].time+'</span></div>';}pl.innerHTML=h;pl.style.display='';if(sec)sec.style.display='';if(emp)emp.style.display='none';}
if(d.arahKiblat!=null){var card=document.getElementById('kiblatCard'),secQ=document.getElementById('secQibla'),deg=document.getElementById('qiblaDeg'),arm=document.getElementById('qiblaArm');card.dataset.qibla=d.arahKiblat;card.style.display='';if(secQ)secQ.style.display='';if(deg)deg.textContent=Math.round(d.arahKiblat)+'° dari utara';if(arm)arm.style.transform='rotate('+d.arahKiblat+'deg)';}
note.textContent=d.dariGps?'✓ Memakai lokasi GPS perangkat Anda.':'GPS tak terpakai — memakai lokasi pesantren.';note.style.color=d.dariGps?'var(--nur)':'var(--faint)';}
function load(la,lo){note.textContent='Menghitung untuk lokasi Anda…';note.style.color='var(--sub)';fetch('/api/v1/jadwal-sholat?lat='+encodeURIComponent(la)+'&lng='+encodeURIComponent(lo)+'&tz='+encodeURIComponent(tzHours()),{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(j){if(j&&j.ok){fill(j.data);}else{note.textContent='Gagal memuat jadwal.';}}).catch(function(){note.textContent='Gagal memuat jadwal untuk lokasi Anda.';});}
btn.onclick=function(){if(!navigator.geolocation){note.textContent='Perangkat tak mendukung GPS.';return;}btn.disabled=true;note.textContent='Meminta izin lokasi…';note.style.color='var(--sub)';navigator.geolocation.getCurrentPosition(function(p){btn.disabled=false;var la=p.coords.latitude,lo=p.coords.longitude;try{localStorage.setItem('nur-geo',JSON.stringify({la:la,lo:lo}));}catch(e){}load(la,lo);},function(err){btn.disabled=false;note.textContent=err&&err.code===1?'Izin lokasi ditolak. Aktifkan izin lokasi di pengaturan browser.':'Tak bisa mendapatkan lokasi GPS.';note.style.color='var(--terra)';},{enableHighAccuracy:true,timeout:10000,maximumAge:300000});};
try{var g=JSON.parse(localStorage.getItem('nur-geo')||'null');if(g&&g.la!=null)load(g.la,g.lo);}catch(e){}
})();
</script>
</body>
</html>
