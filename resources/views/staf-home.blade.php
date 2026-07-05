<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#0b6e4f">
<script>try{if(localStorage.getItem('nur-theme')==='dark')document.documentElement.classList.add('dark');}catch(e){}</script>
<title>{{ $namaPesantren }} — Staf</title>
<link rel="manifest" href="/beranda/manifest.webmanifest">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
@if($logoUrl)<link rel="apple-touch-icon" href="{{ $logoUrl }}"><link rel="icon" href="{{ $logoUrl }}">@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{touch-action:manipulation;-webkit-text-size-adjust:100%}
:root{--nur:#0b6e4f;--nur-strong:#085840;--nur-tint:#ecfdf6;--nur-100:#d1faea;--terra:#c14f2a;--terra-tint:#fef3ee;--gold:#c9a24a;--gold-soft:#e7d3a1;--goldb:#fef9c3;--goldink:#7a5e1e;--ink:#111;--sub:#64748b;--faint:#94a3b8;--line:#efe7da;--card:#fff;--bg:#fffaf3;--grad:linear-gradient(160deg,#17a179 0%,#0b6e4f 48%,#062a20 100%);--r:16px;--r2:20px;--rs:12px;--fd:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;--fb:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;--sh:inset 0 0 0 1px var(--line),0 2px 4px -2px rgba(17,17,17,.06),0 12px 28px -16px rgba(17,17,17,.18)}
html,body{height:100%;font-family:var(--fb);background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;-webkit-user-select:none;user-select:none}
a{text-decoration:none;color:inherit}button{font-family:inherit;color:inherit}a,button,input{touch-action:manipulation}
/* Dark Mode "Nur" — aktif bila <html class="dark"> (di-set toggle, persist localStorage). */
html.dark{--nur:#2fbf85;--nur-strong:#26a874;--nur-tint:#12271f;--nur-100:#1c3a2e;--terra-tint:#33180f;--goldb:#2a230f;--goldink:#e7c877;--ink:#eef2f0;--sub:#9fb0a8;--faint:#6b7a73;--line:#26312c;--card:#161e1a;--bg:#0d1411;--sh:inset 0 0 0 1px var(--line),0 10px 26px -18px rgba(0,0,0,.7)}
html.dark body{background:var(--bg)}
html.dark .c-nur{background:#12271f;color:#5fe0a8}html.dark .c-gold{background:#2a230f;color:#e7c877}html.dark .c-violet{background:#241c3a;color:#c4b5fd}html.dark .c-sky{background:#12293a;color:#7dd3fc}html.dark .c-rose{background:#3a1420;color:#fda4c0}html.dark .c-terra{background:#33180f;color:#f0a084}html.dark .c-slate{background:#1e2630;color:#b0c0d0}
html.dark .tile[data-c="gold"] .tile-icon,html.dark .menu-item[data-c="gold"] .menu-icon{background:#2a230f;color:#e7c877}html.dark .tile[data-c="sky"] .tile-icon,html.dark .menu-item[data-c="sky"] .menu-icon{background:#12293a;color:#7dd3fc}html.dark .tile[data-c="rose"] .tile-icon,html.dark .menu-item[data-c="rose"] .menu-icon{background:#3a1420;color:#fda4c0}html.dark .tile[data-c="violet"] .tile-icon,html.dark .menu-item[data-c="violet"] .menu-icon{background:#241c3a;color:#c4b5fd}html.dark .tile[data-c="terra"] .tile-icon,html.dark .menu-item[data-c="terra"] .menu-icon{background:#33180f;color:#f0a084}html.dark .tile[data-c="slate"] .tile-icon,html.dark .menu-item[data-c="slate"] .menu-icon{background:#1e2630;color:#b0c0d0}
html.dark .nav{background:rgba(18,24,21,.96)}
@media(prefers-reduced-motion:reduce){*{animation-duration:.001ms!important;transition-duration:.001ms!important}}
.app{max-width:540px;margin:0 auto;min-height:100%;position:relative}
.scroll-area{overflow-y:auto;height:100vh;padding-bottom:calc(96px + env(safe-area-inset-bottom))}
.tab{display:none}.tab.on{display:block;animation:fade .25s ease}
@keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.hero{background:var(--grad);color:#fff;padding:calc(24px + env(safe-area-inset-top)) 18px 20px;position:relative;overflow:hidden;border-bottom-left-radius:28px;border-bottom-right-radius:28px;box-shadow:0 12px 30px -18px rgba(6,42,32,.7)}
.hero-pat{position:absolute;inset:0;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='44' height='44' viewBox='0 0 44 44'%3E%3Cg fill='none' stroke='%23ffffff' stroke-width='1' opacity='0.5'%3E%3Crect x='13' y='13' width='18' height='18'/%3E%3Crect x='13' y='13' width='18' height='18' transform='rotate(45 22 22)'/%3E%3C/g%3E%3C/svg%3E");background-size:44px 44px;opacity:.10;pointer-events:none}
.hero-glow{position:absolute;right:-46px;top:-46px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(231,211,161,.22) 0%,transparent 66%);pointer-events:none}
.hero-top{display:flex;align-items:center;gap:12px;position:relative}
.avatar{width:48px;height:48px;border-radius:15px;overflow:hidden;background:rgba(255,255,255,.18);border:1px solid rgba(231,211,161,.35);display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-weight:800;font-size:20px;flex:0 0 auto}
.avatar img{width:100%;height:100%;object-fit:cover}
.hero-info{flex:1;min-width:0}.hero-greeting{font-size:12px;color:var(--gold-soft);font-weight:700}.hero-name{font-family:var(--fd);font-size:20px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hero-bell{width:42px;height:42px;border-radius:13px;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.14);color:#fff;display:flex;align-items:center;justify-content:center;position:relative;cursor:pointer}
.datestrip{display:flex;align-items:center;justify-content:space-between;margin-top:14px;position:relative;font-size:11.5px;color:#c9f2e2;font-weight:600}
.datestrip b{color:#fff;font-weight:800}
.datestrip .cd{background:rgba(255,255,255,.1);border-radius:99px;padding:3px 10px}
.hairline{height:1px;margin:14px 0 0;background:linear-gradient(90deg,transparent,rgba(231,211,161,0) 6%,var(--gold-soft) 50%,rgba(231,211,161,0) 94%,transparent);opacity:.55;position:relative}
.prayer{display:flex;gap:6px;margin-top:14px;position:relative}
.pcell{flex:1;text-align:center;padding:9px 2px;border-radius:13px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08)}
.pcell.next{background:rgba(231,211,161,.16);border:1px solid rgba(231,211,161,.5);box-shadow:0 6px 16px -10px rgba(0,0,0,.5)}
.pcell .pn{font-size:10px;font-weight:700;color:#c9f2e2}.pcell.next .pn{color:#fff}
.pcell .pt{font-size:13px;font-weight:800;margin-top:3px;font-variant-numeric:tabular-nums}
.pcell .pnext{font-size:8.5px;font-weight:800;color:#0b6e4f;background:var(--gold-soft);border-radius:99px;padding:1px 6px;margin-top:4px;display:inline-block}
.sec{padding:20px 16px 0}.sec-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.sec-title{font-family:var(--fd);font-size:15px;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:8px}
.sec-title::before{content:"";width:4px;height:15px;border-radius:99px;background:var(--nur)}
.sec-link{font-size:12px;font-weight:800;color:var(--nur);cursor:pointer;background:none;border:none;display:flex;align-items:center;gap:1px}
.quick{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}
.qa{background:var(--card);border:none;border-radius:var(--r);padding:14px 4px 11px;display:flex;flex-direction:column;align-items:center;gap:8px;box-shadow:var(--sh);cursor:pointer;text-align:center;transition:transform .1s}
.qa:active{transform:scale(.95)}
.qa-ic{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center}
.qa-lbl{font-size:10.5px;font-weight:700;color:var(--ink);line-height:1.15}
.c-nur{background:var(--nur-tint);color:var(--nur)}.c-gold{background:var(--goldb);color:var(--goldink)}.c-terra{background:var(--terra-tint);color:var(--terra)}.c-violet{background:#ede9fe;color:#5b21b6}.c-sky{background:#e0f2fe;color:#075985}.c-rose{background:#ffe4e6;color:#9f1239}.c-slate{background:#f1f5f9;color:#475569}
.feat{margin:0 16px;border-radius:var(--r2);overflow:hidden;position:relative;box-shadow:var(--sh);cursor:pointer;background:linear-gradient(120deg,#0b6e4f,#17a179)}
.feat-pat{position:absolute;inset:0;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='44' height='44' viewBox='0 0 44 44'%3E%3Cg fill='none' stroke='%23ffffff' stroke-width='1' opacity='0.5'%3E%3Crect x='13' y='13' width='18' height='18'/%3E%3Crect x='13' y='13' width='18' height='18' transform='rotate(45 22 22)'/%3E%3C/g%3E%3C/svg%3E");background-size:44px 44px;opacity:.12}
.feat-in{position:relative;padding:18px;color:#fff;min-height:110px;display:flex;flex-direction:column;justify-content:flex-end}
.feat-chip{position:absolute;top:14px;left:18px;font-size:9.5px;font-weight:800;letter-spacing:.08em;background:rgba(231,211,161,.9);color:#5a4415;border-radius:99px;padding:3px 10px}
.feat-t{font-family:var(--fd);font-size:17px;font-weight:800;line-height:1.2}
.feat-s{font-size:11.5px;color:#d7f0e5;margin-top:5px}
.clist{display:flex;flex-direction:column;gap:9px}
.crow{display:flex;align-items:center;gap:12px;background:var(--card);border-radius:var(--r);padding:13px 14px;box-shadow:var(--sh)}
.crow-ic{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.crow-body{flex:1;min-width:0}.crow-t{font-size:13.5px;font-weight:700;line-height:1.25}.crow-s{font-size:11.5px;color:var(--sub);margin-top:2px}
.menu-arrow{color:#cbd5e1}.menu-arrow svg{width:16px;height:16px}
.empty{text-align:center;padding:52px 24px;color:var(--sub);font-size:14px}
.empty .ei{width:64px;height:64px;border-radius:20px;background:var(--nur-tint);color:var(--nur);display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.page-head{padding:calc(24px + env(safe-area-inset-top)) 18px 4px}.page-title{font-family:var(--fd);font-size:22px;font-weight:800}.page-sub{font-size:12.5px;color:var(--sub);margin-top:3px;font-weight:600}
.search-wrap{padding:14px 16px 2px;position:relative}
.search-wrap svg{position:absolute;left:28px;top:calc(50% + 3px);transform:translateY(-50%);width:18px;height:18px;color:var(--faint);pointer-events:none}
#search{width:100%;border:1.5px solid var(--line);border-radius:var(--r);padding:13px 14px 13px 42px;font-size:14px;font-family:inherit;background:var(--card);outline:none;color:var(--ink)}
#search:focus{border-color:var(--nur);box-shadow:0 0 0 3px rgba(11,110,79,.12)}
.grid-wrap{padding:14px 14px 0}.grid-title{font-size:11.5px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--faint);padding:0 4px 11px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.tile{position:relative;background:var(--card);border-radius:var(--r);padding:16px 8px 13px;display:flex;flex-direction:column;align-items:center;gap:9px;text-align:center;box-shadow:var(--sh);cursor:pointer;border:none;transition:transform .1s}
.tile:active{transform:scale(.95)}
.tile-icon{width:48px;height:48px;border-radius:15px;background:var(--nur-tint);color:var(--nur);display:flex;align-items:center;justify-content:center}
.tile-label{font-size:12px;font-weight:700;color:var(--ink);line-height:1.2}
.tile-count{position:absolute;top:8px;right:8px;min-width:19px;height:19px;padding:0 5px;border-radius:99px;background:var(--nur-100);color:var(--nur-strong);font-size:10.5px;font-weight:800;display:flex;align-items:center;justify-content:center}
.tile[data-c="terra"] .tile-icon{background:var(--terra-tint);color:var(--terra)}.tile[data-c="gold"] .tile-icon{background:var(--goldb);color:var(--goldink)}.tile[data-c="sky"] .tile-icon{background:#e0f2fe;color:#075985}.tile[data-c="rose"] .tile-icon{background:#ffe4e6;color:#9f1239}.tile[data-c="violet"] .tile-icon{background:#ede9fe;color:#5b21b6}.tile[data-c="slate"] .tile-icon{background:#f1f5f9;color:#475569}
#detail{display:none;padding-top:6px}.detail-bar{display:flex;align-items:center;gap:12px;padding:20px 16px 8px}
.back-btn{width:40px;height:40px;border-radius:12px;border:none;background:var(--card);box-shadow:var(--sh);color:var(--ink);display:flex;align-items:center;justify-content:center;cursor:pointer}
.back-btn:active{transform:scale(.94)}
.detail-title{font-family:var(--fd);font-size:18px;font-weight:800;line-height:1.15}.detail-sub{font-size:11.5px;color:var(--sub);font-weight:600;margin-top:1px}
.menu-list{display:flex;flex-direction:column;gap:8px;padding:6px 14px 0}
.menu-item{display:flex;align-items:center;gap:13px;background:var(--card);border-radius:var(--r);padding:13px 15px;box-shadow:var(--sh);cursor:pointer;transition:transform .08s;-webkit-tap-highlight-color:transparent}
.menu-item:active{transform:scale(.98)}
.menu-icon{width:42px;height:42px;border-radius:12px;background:var(--nur-tint);color:var(--nur);display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.menu-label{font-size:14.5px;font-weight:700;color:var(--ink);flex:1;line-height:1.2}
.menu-item[data-c="terra"] .menu-icon{background:var(--terra-tint);color:var(--terra)}.menu-item[data-c="gold"] .menu-icon{background:var(--goldb);color:var(--goldink)}.menu-item[data-c="sky"] .menu-icon{background:#e0f2fe;color:#075985}.menu-item[data-c="rose"] .menu-icon{background:#ffe4e6;color:#9f1239}.menu-item[data-c="violet"] .menu-icon{background:#ede9fe;color:#5b21b6}.menu-item[data-c="slate"] .menu-icon{background:#f1f5f9;color:#475569}
#results{display:none;padding-top:6px}.results-title{font-size:11.5px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--faint);padding:12px 18px 4px}#no-result{display:none;text-align:center;padding:40px 24px;color:var(--sub);font-size:14px}
.profile{margin:16px;border-radius:var(--r2);padding:22px 20px;position:relative;overflow:hidden;background:var(--grad);color:#fff;box-shadow:0 14px 34px -20px rgba(6,42,32,.7)}
.profile-pat{position:absolute;inset:0;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='44' height='44' viewBox='0 0 44 44'%3E%3Cg fill='none' stroke='%23ffffff' stroke-width='1' opacity='0.5'%3E%3Crect x='13' y='13' width='18' height='18'/%3E%3Crect x='13' y='13' width='18' height='18' transform='rotate(45 22 22)'/%3E%3C/g%3E%3C/svg%3E");background-size:44px 44px;opacity:.1}
.profile-row{display:flex;align-items:center;gap:15px;position:relative}
.profile .avatar{width:58px;height:58px;font-size:24px}
.profile-name{font-family:var(--fd);font-size:19px;font-weight:800}.profile-role{font-size:12px;color:var(--gold-soft);margin-top:3px;font-weight:600}
.profile-stats{display:flex;gap:22px;margin-top:16px;position:relative;padding-top:15px;border-top:1px solid rgba(231,211,161,.25)}
.ps b{font-family:var(--fd);font-size:17px;font-weight:800;display:block}.ps span{font-size:10.5px;color:#c9f2e2;font-weight:600}
.setlist{margin:6px 16px 0;display:flex;flex-direction:column;gap:8px}
.setrow{display:flex;align-items:center;gap:13px;background:var(--card);border-radius:var(--r);padding:14px 15px;box-shadow:var(--sh);cursor:pointer}
.setrow-ic{width:38px;height:38px;border-radius:11px;background:var(--nur-tint);color:var(--nur);display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.setrow-lbl{flex:1;font-size:14px;font-weight:700}
.logout{margin:16px;padding:15px;border-radius:var(--r);border:1.5px solid #fecdd3;background:#fff1f2;color:#be123c;font-weight:800;font-size:14px;display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;width:calc(100% - 32px)}
.nav{position:fixed;bottom:0;left:0;right:0;max-width:540px;margin:0 auto;background:rgba(255,255,255,.96);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-top:1px solid var(--line);display:flex;padding:9px 8px calc(12px + env(safe-area-inset-bottom));z-index:30;box-shadow:0 -8px 24px -18px rgba(17,17,17,.3)}
.nav button{flex:1;border:none;background:none;cursor:pointer;padding:0;display:flex;flex-direction:column;align-items:center;gap:5px;font-size:10.5px;font-weight:800;color:var(--faint);position:relative}
.nav button.on{color:var(--nur)}
.nav .ic{width:52px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:99px;transition:background .2s}
.nav button.on .ic{background:var(--nur-tint)}
.nav .nbadge{position:absolute;top:-1px;right:calc(50% - 20px);min-width:16px;height:16px;padding:0 4px;border-radius:99px;background:var(--terra);color:#fff;font-size:9px;font-weight:800;display:flex;align-items:center;justify-content:center;border:2px solid #fff}
</style>
</head>
<body>
@php
use Illuminate\Support\Str;
$I=[
  'users'=>'<circle cx="9" cy="7" r="4"/><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  'user'=>'<circle cx="12" cy="7" r="4"/><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>',
  'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
  'doc'=>'<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
  'bell'=>'<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
  'shield'=>'<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
  'cal'=>'<path d="M8 2v4"/><path d="M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/>',
  'home'=>'<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
  'check'=>'<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
  'heart'=>'<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.5 4.04 3 5.5l7 7Z"/>',
  'chat'=>'<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
  'book'=>'<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
  'wallet'=>'<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
  'chart'=>'<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
  'cog'=>'<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
  'clock'=>'<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
  'apps'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
  'moon'=>'<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
  'arrow'=>'<path d="m9 18 6-6-6-6"/>','back'=>'<path d="m15 18-6-6 6-6"/>','chevron'=>'<path d="m9 18 6-6-6-6"/>',
  'search'=>'<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
  'login'=>'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><path d="M21 12H9"/>',
];
$svg=fn($n,$s=20)=>'<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.($I[$n]??$I['grid']).'</svg>';
$warna=['Santri'=>'','Master Akademik'=>'violet','Pembelajaran'=>'violet','Perizinan & Kunjungan'=>'gold','Kegiatan'=>'gold','Kedisiplinan'=>'terra','Asrama & Pengasuhan'=>'sky','Kesehatan'=>'rose','Konseling'=>'rose','Pendaftaran'=>'terra','Tagihan Santri'=>'','Laporan Keuangan'=>'','Buku Besar'=>'','Penggajian'=>'','Komunikasi'=>'gold','Pengaturan Sistem'=>'slate'];
$grupIkon=['Santri'=>'users','Master Akademik'=>'grid','Pembelajaran'=>'book','Perizinan & Kunjungan'=>'doc','Kegiatan'=>'cal','Kedisiplinan'=>'shield','Asrama & Pengasuhan'=>'home','Kesehatan'=>'heart','Konseling'=>'chat','Pendaftaran'=>'user','Tagihan Santri'=>'wallet','Laporan Keuangan'=>'chart','Buku Besar'=>'book','Penggajian'=>'wallet','Komunikasi'=>'bell','Pengaturan Sistem'=>'cog'];
$qcolor=['nur','gold','violet','terra'];
$inisial=strtoupper(mb_substr($user->name,0,1));
$featArt=$pengumuman->first();
@endphp
<div class="app">
<div class="scroll-area">

{{-- BERANDA --}}
<div class="tab on" id="tab-beranda">
  <div class="hero">
    <div class="hero-pat"></div><div class="hero-glow"></div>
    <div class="hero-top">
      <div class="avatar">@if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else{{ $inisial }}@endif</div>
      <div class="hero-info"><div class="hero-greeting">{{ $sapa }},</div><div class="hero-name">{{ $user->name }}</div></div>
      <button class="hero-bell" id="btnTema" aria-label="Ganti tema gelap/terang" style="margin-right:8px">{!! $svg('moon',20) !!}</button>
      <button class="hero-bell" data-go="aktivitas">{!! $svg('bell',20) !!}</button>
    </div>
    <div class="datestrip"><span>{!! $svg('moon',13) !!} &nbsp;<b>{{ $tanggal['masehi'] ?? '' }}</b></span>@if(!empty($tanggal['hijriah']))<span class="cd">{{ $tanggal['hijriah'] }}</span>@endif</div>
    @if(count($sholat) > 0)
      <div class="hairline"></div>
      <div class="prayer">
        @foreach($sholat as $s)
          <div class="pcell {{ $s['next'] ? 'next' : '' }}"><div class="pn">{{ $s['label'] }}</div><div class="pt">{{ $s['time'] }}</div>@if($s['next'])<div class="pnext">berikutnya</div>@endif</div>
        @endforeach
      </div>
    @endif
  </div>

  @if(count($pintasan) > 0)
  <div class="sec">
    <div class="sec-head"><div class="sec-title">Pintasan Cepat</div><button class="sec-link" data-go="menu">Semua {!! $svg('chevron',14) !!}</button></div>
    <div class="quick">
      @foreach($pintasan as $i => $p)
        <a class="qa" href="{{ $p[3] }}"><span class="qa-ic c-{{ $qcolor[$i % 4] }}">{!! $svg($p[1],22) !!}</span><span class="qa-lbl">{{ $p[0] }}</span></a>
      @endforeach
    </div>
  </div>
  @endif

  <div class="sec">
    <div class="sec-head"><div class="sec-title">Pengumuman</div></div>
    @if($pengumuman->count() > 0)
      <a class="feat" href="/beranda"><div class="feat-pat"></div><span class="feat-chip">PENGUMUMAN</span><div class="feat-in"><div class="feat-t">{{ $featArt->title }}</div>@if($featArt->published_at)<div class="feat-s">{{ $featArt->published_at->locale('id')->translatedFormat('l, d F Y') }}</div>@endif</div></a>
      @if($pengumuman->count() > 1)
        <div style="height:10px"></div>
        <div class="clist">
          @foreach($pengumuman->slice(1)->take(3) as $art)
            <a class="crow" href="/beranda"><span class="crow-ic c-gold">{!! $svg('cal',20) !!}</span><div class="crow-body"><div class="crow-t">{{ $art->title }}</div>@if($art->published_at)<div class="crow-s">{{ $art->published_at->locale('id')->translatedFormat('d M Y') }}</div>@endif</div><span class="menu-arrow">{!! $svg('arrow',16) !!}</span></a>
          @endforeach
        </div>
      @endif
    @else
      <div class="empty" style="padding:32px 24px">Belum ada pengumuman.</div>
    @endif
  </div>
  <div style="height:10px"></div>
</div>

{{-- MENU --}}
<div class="tab" id="tab-menu">
  <div class="page-head"><div class="page-title">Menu Aplikasi</div><div class="page-sub">Dikelompokkan sama seperti panel admin</div></div>
  @if(count($groups) === 0)
    <div class="empty"><div class="ei">{!! $svg('apps',30) !!}</div>Belum ada menu untuk peran Anda.<br>Hubungi admin pesantren.</div>
  @else
    <div class="search-wrap">{!! $svg('search',18) !!}<input id="search" type="search" placeholder="Cari menu…" autocomplete="off" autocorrect="off"></div>
    <div id="grid-view" class="grid-wrap">
      <div class="grid-title">{{ count($groups) }} grup menu</div>
      <div class="grid">
        @foreach($groups as $judul => $items)
          @php $c=$warna[$judul]??''; $gi=$grupIkon[$judul]??'grid'; @endphp
          <button type="button" class="tile" @if($c)data-c="{{ $c }}"@endif data-open="{{ Str::slug($judul) }}"><span class="tile-count">{{ count($items) }}</span><span class="tile-icon">{!! $svg($gi,25) !!}</span><span class="tile-label">{{ $judul }}</span></button>
        @endforeach
      </div>
    </div>
    <div id="detail">
      @foreach($groups as $judul => $items)
        @php $c=$warna[$judul]??''; @endphp
        <div class="detail-pane" id="pane-{{ Str::slug($judul) }}" style="display:none">
          <div class="detail-bar"><button type="button" class="back-btn" data-back>{!! $svg('back',20) !!}</button><div><div class="detail-title">{{ $judul }}</div><div class="detail-sub">{{ count($items) }} menu</div></div></div>
          <div class="menu-list">
            @foreach($items as $it)
              <a class="menu-item" @if($c)data-c="{{ $c }}"@endif href="{{ $it[3] }}"><span class="menu-icon">{!! $svg($it[1],20) !!}</span><span class="menu-label">{{ $it[0] }}</span><span class="menu-arrow">{!! $svg('arrow',16) !!}</span></a>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
    <div id="results"><div class="results-title">Hasil pencarian</div><div class="menu-list">
      @foreach($groups as $judul => $items)@php $c=$warna[$judul]??''; @endphp @foreach($items as $it)
        <a class="menu-item search-item" @if($c)data-c="{{ $c }}"@endif href="{{ $it[3] }}" data-label="{{ mb_strtolower($it[0].' '.$judul) }}"><span class="menu-icon">{!! $svg($it[1],20) !!}</span><span class="menu-label">{{ $it[0] }}</span><span class="menu-arrow">{!! $svg('arrow',16) !!}</span></a>
      @endforeach @endforeach
      <div id="no-result">Tidak ada menu yang cocok.</div>
    </div></div>
  @endif
</div>

{{-- AKTIVITAS --}}
<div class="tab" id="tab-aktivitas">
  <div class="page-head"><div class="page-title">Aktivitas</div></div>
  <div class="empty"><div class="ei">{!! $svg('bell',30) !!}</div>Belum ada aktivitas terbaru.</div>
</div>

{{-- AKUN --}}
<div class="tab" id="tab-akun">
  <div class="page-head"><div class="page-title">Akun</div></div>
  <div class="profile"><div class="profile-pat"></div>
    <div class="profile-row"><div class="avatar">@if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else{{ $inisial }}@endif</div><div style="min-width:0"><div class="profile-name">{{ $user->name }}</div><div class="profile-role">{{ $peran }} · {{ $namaPesantren }}</div></div></div>
    <div class="profile-stats"><div class="ps"><b>{{ count($groups) }}</b><span>Grup menu</span></div><div class="ps"><b>{{ $totalFitur }}</b><span>Fitur akses</span></div><div class="ps"><b>Aktif</b><span>Status akun</span></div></div>
  </div>
  <div class="setlist">
    <a class="setrow" href="{{ $adminUrl }}"><span class="setrow-ic">{!! $svg('grid',18) !!}</span><span class="setrow-lbl">Panel Lengkap (Admin)</span><span class="menu-arrow">{!! $svg('arrow',16) !!}</span></a>
    <a class="setrow" href="/beranda"><span class="setrow-ic">{!! $svg('home',18) !!}</span><span class="setrow-lbl">Beranda Publik</span><span class="menu-arrow">{!! $svg('arrow',16) !!}</span></a>
  </div>
  <button class="logout" onclick="document.getElementById('frmKeluar').submit()">{!! $svg('login',18) !!} Keluar</button>
</div>

</div>
<nav class="nav">
  <button class="on" data-tab="beranda"><span class="ic">{!! $svg('home',22) !!}</span>Beranda</button>
  <button data-tab="menu"><span class="ic">{!! $svg('apps',22) !!}</span>Menu</button>
  <button data-tab="aktivitas"><span class="ic">{!! $svg('bell',22) !!}</span>Aktivitas</button>
  <button data-tab="akun"><span class="ic">{!! $svg('user',22) !!}</span>Akun</button>
</nav>
</div>
<form id="frmKeluar" method="POST" action="/keluar" style="display:none">@csrf</form>
<script>
(function(){
  const scroller=document.querySelector('.scroll-area'),tabs=[...document.querySelectorAll('.tab')],navs=[...document.querySelectorAll('.nav button')];
  function go(n){tabs.forEach(t=>t.classList.toggle('on',t.id==='tab-'+n));navs.forEach(b=>b.classList.toggle('on',b.dataset.tab===n));scroller.scrollTop=0;}
  navs.forEach(b=>b.addEventListener('click',()=>go(b.dataset.tab)));
  document.querySelectorAll('[data-go]').forEach(el=>el.addEventListener('click',()=>go(el.dataset.go)));
  const btnTema=document.getElementById('btnTema');
  if(btnTema)btnTema.addEventListener('click',function(){const d=document.documentElement.classList.toggle('dark');try{localStorage.setItem('nur-theme',d?'dark':'light');}catch(e){}});
  const gridView=document.getElementById('grid-view');
  if(gridView){
    const detail=document.getElementById('detail'),results=document.getElementById('results'),noRes=document.getElementById('no-result'),inp=document.getElementById('search'),panes=[...document.querySelectorAll('.detail-pane')];
    function showGrid(){detail.style.display='none';results.style.display='none';panes.forEach(p=>p.style.display='none');gridView.style.display='';scroller.scrollTop=0;}
    function openGroup(s){gridView.style.display='none';results.style.display='none';detail.style.display='block';panes.forEach(p=>p.style.display=(p.id==='pane-'+s)?'block':'none');scroller.scrollTop=0;}
    document.querySelectorAll('.tile').forEach(t=>t.addEventListener('click',()=>openGroup(t.dataset.open)));
    document.querySelectorAll('[data-back]').forEach(b=>b.addEventListener('click',showGrid));
    const si=[...document.querySelectorAll('.search-item')];
    inp.addEventListener('input',function(){const q=this.value.trim().toLowerCase();if(!q){showGrid();return;}gridView.style.display='none';detail.style.display='none';panes.forEach(p=>p.style.display='none');results.style.display='block';let v=0;si.forEach(a=>{const m=a.dataset.label.includes(q);a.style.display=m?'':'none';if(m)v++;});noRes.style.display=v?'none':'block';});
  }
})();
</script>
</body>
</html>
