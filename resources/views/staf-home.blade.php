<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#0F5132">
<title>{{ $namaPesantren }} — Staf</title>
<link rel="manifest" href="/beranda/manifest.webmanifest">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
@if($logoUrl)<link rel="apple-touch-icon" href="{{ $logoUrl }}"><link rel="icon" href="{{ $logoUrl }}">@endif
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
/* blok zoom: double-tap & pinch */
html{touch-action:manipulation;-webkit-text-size-adjust:100%}
:root{
  --g:#059669;--gd:#0F5132;--gs:#d1fae5;--gi:#ecfdf5;
  --gold:#b48a2e;--goldb:#fef9c3;
  --ink:#111827;--sub:#6b7280;--line:#e5e7eb;--card:#fff;
  --bg:#f9fafb;
  --grad:linear-gradient(155deg,#0a4e31 0%,#0e5c3a 60%,#0F5132 100%);
  --r:14px;--rs:8px;
}
html,body{height:100%;font-family:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;-webkit-user-select:none;user-select:none}
/* elemen interaktif: hapus delay tap 300ms & double-tap zoom */
a,button,input{touch-action:manipulation}
a{text-decoration:none;color:inherit}
/* ─── layout ─── */
.app{max-width:540px;margin:0 auto;min-height:100%;display:flex;flex-direction:column}
.scroll-area{flex:1;overflow-y:auto;padding-bottom:calc(80px + env(safe-area-inset-bottom))}
/* ─── hero ─── */
.hero{background:var(--grad);color:#fff;padding:calc(18px + env(safe-area-inset-top)) 18px 20px;position:relative;overflow:hidden}
.hero::before{content:"";position:absolute;right:-30px;top:-30px;width:160px;height:160px;border-radius:50%;background:radial-gradient(circle,rgba(212,175,55,.25) 0%,transparent 70%);pointer-events:none}
.hero-top{display:flex;align-items:center;gap:12px;position:relative}
.logo{width:44px;height:44px;border-radius:12px;overflow:hidden;background:rgba(255,255,255,.15);flex:0 0 auto;display:flex;align-items:center;justify-content:center}
.logo img{width:100%;height:100%;object-fit:cover}
.hero-info{flex:1;min-width:0}
.hero-greeting{font-size:11.5px;color:#a7f3d0;font-weight:500;margin-bottom:2px}
.hero-name{font-size:17px;font-weight:800;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.role-chip{display:inline-flex;align-items:center;gap:5px;margin-top:9px;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.18);border-radius:99px;padding:4px 11px;font-size:11px;font-weight:600;position:relative}
/* ─── search ─── */
.search-wrap{padding:14px 16px 2px;position:relative}
.search-wrap svg{position:absolute;left:26px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:#9ca3af;pointer-events:none}
#search{width:100%;border:1.5px solid var(--line);border-radius:var(--r);padding:10px 14px 10px 40px;font-size:14px;font-family:inherit;background:var(--card);outline:none;color:var(--ink);transition:border-color .15s}
#search:focus{border-color:var(--g);box-shadow:0 0 0 3px rgba(5,150,105,.1)}
#search::placeholder{color:#9ca3af}
/* ─── empty state search ─── */
#no-result{display:none;text-align:center;padding:40px 24px;color:var(--sub);font-size:14px}
/* ─── group ─── */
.group{margin-top:18px}
.group-header{display:flex;align-items:center;justify-content:space-between;padding:0 16px 8px}
.group-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--sub)}
.group-count{font-size:11px;font-weight:600;background:var(--gs);color:var(--gd);border-radius:99px;padding:2px 8px}
/* ─── menu list ─── */
.menu-list{display:flex;flex-direction:column;gap:2px;padding:0 12px}
.menu-item{display:flex;align-items:center;gap:12px;background:var(--card);border-radius:var(--r);padding:12px 14px;border:1px solid var(--line);transition:background .1s,transform .08s;cursor:pointer;-webkit-tap-highlight-color:transparent}
.menu-item:active{background:#f3f4f6;transform:scale(.98)}
.menu-icon{width:40px;height:40px;border-radius:10px;background:var(--gi);color:var(--g);display:flex;align-items:center;justify-content:center;flex:0 0 auto;transition:background .15s}
.menu-label{font-size:14px;font-weight:600;color:var(--ink);flex:1;line-height:1.2}
.menu-arrow{color:#d1d5db;flex:0 0 auto}
.menu-arrow svg{width:16px;height:16px}
/* colored groups */
.group[data-c="amber"] .menu-icon{background:#fef9c3;color:#92400e}
.group[data-c="sky"]   .menu-icon{background:#e0f2fe;color:#075985}
.group[data-c="rose"]  .menu-icon{background:#ffe4e6;color:#9f1239}
.group[data-c="violet"].menu-icon{background:#ede9fe;color:#5b21b6}
.group[data-c="orange"].menu-icon{background:#ffedd5;color:#9a3412}
.group[data-c="teal"]  .menu-icon{background:#ccfbf1;color:#0f766e}
.group[data-c="slate"] .menu-icon{background:#f1f5f9;color:#475569}
/* ─── bottom nav ─── */
.nav{position:fixed;bottom:0;left:0;right:0;max-width:540px;margin:0 auto;background:rgba(255,255,255,.97);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-top:1px solid var(--line);display:flex;padding:6px 4px calc(6px + env(safe-area-inset-bottom));z-index:30}
.nav a,.nav button{flex:1;border:none;background:none;cursor:pointer;padding:6px 2px;display:flex;flex-direction:column;align-items:center;gap:2px;font-size:10.5px;font-weight:600;color:#9ca3af;font-family:inherit;border-radius:var(--rs);transition:color .15s;-webkit-tap-highlight-color:transparent}
.nav a:active,.nav button:active{background:#f3f4f6}
.nav a.on{color:var(--g)}
.nav svg{width:22px;height:22px}
</style>
</head>
<body>
@php
use Illuminate\Support\Str;
$I=[
  'users'=>'M8.4 11.4a3.3 3.3 0 1 0 0-6.6 3.3 3.3 0 0 0 0 6.6Zm0 1.8c-3.5 0-6.4 1.9-6.4 4.3v1.7h12.8v-1.7c0-2.4-2.9-4.3-6.4-4.3Zm8-1.8a3 3 0 0 0 .7-5.9 4.9 4.9 0 0 1 0 5.6c.4.2.8.3 1.3.3Zm.6 1.8h-1.1c1 .9 1.6 2.1 1.6 3.5v1.5H22v-1.7c0-1.9-2.2-3.3-4.4-3.3Z',
  'user'=>'M12 12.2a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Zm0 2c-4.1 0-7.2 2.2-7.2 5.1V21h14.4v-1.7c0-2.9-3.1-5.1-7.2-5.1Z',
  'grid'=>'M3.6 3.6h7v7h-7zM13.4 3.6h7v7h-7zM3.6 13.4h7v7h-7zM13.4 13.4h7v7h-7z',
  'doc'=>'M6.2 2.4h7.6l5 5v14.2a1 1 0 0 1-1 1H6.2a1 1 0 0 1-1-1V3.4a1 1 0 0 1 1-1Zm7.6 1.6V8h4z',
  'bell'=>'M12 2.2a6.2 6.2 0 0 0-6.2 6.2c0 4.8-2 6.1-2 6.1h16.4s-2-1.3-2-6.1A6.2 6.2 0 0 0 12 2.2Zm0 20a2.6 2.6 0 0 0 2.5-2H9.5a2.6 2.6 0 0 0 2.5 2Z',
  'shield'=>'M12 2.2 4.2 5v6.2c0 5 3.4 8.6 7.8 10.6 4.4-2 7.8-5.6 7.8-10.6V5z',
  'cal'=>'M7 2.4v2H5.4a2 2 0 0 0-2 2v1.8h17.2V6.4a2 2 0 0 0-2-2H17v-2h-2.2v2H9.2v-2zM3.4 10v9a2 2 0 0 0 2 2h13.2a2 2 0 0 0 2-2v-9z',
  'home'=>'M5 9.6 12 4l7 5.6V20a1 1 0 0 1-1 1h-3.4v-5.2H9.4V21H6a1 1 0 0 1-1-1z',
  'check'=>'M9.2 16.4 4.8 12l-1.5 1.5 5.9 5.9L21 7.6 19.5 6.1z',
  'heart'=>'M12 20.7 4.3 13a4.7 4.7 0 0 1 6.6-6.7l1.1 1 1.1-1A4.7 4.7 0 0 1 19.7 13z',
  'chat'=>'M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H8l-4 4V5a1 1 0 0 1 1-1Z',
  'book'=>'M6 2.6h11.4a1 1 0 0 1 1 1v15.2a1 1 0 0 1-1 1H8.2a2.2 2.2 0 0 0-2.2 2.2zM8.4 5v9.2l2.3-1.4 2.3 1.4V5z',
  'wallet'=>'M3.2 7.2c0-1.6 1.2-2.8 2.8-2.8h11.2a2 2 0 0 1 2 2v1.4h-4.8a3.2 3.2 0 0 0 0 6.4h4.8v1.4a2 2 0 0 1-2 2H6a2.8 2.8 0 0 1-2.8-2.8zM16.4 10.6a1.6 1.6 0 0 0 0 3.2h4.4v-3.2z',
  'chart'=>'M3.6 13.2h3.4V20H3.6zM10.3 7.4h3.4V20h-3.4zM17 4h3.4v16H17z',
  'cog'=>'M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2Zm9 3.6-2-1.2.3-2.3-2-1.2-1.9 1.3-2-.9L12 3l-1.4 2.7-2 .9L6.7 5.3l-2 1.2.3 2.3-2 1.2.8 2.2-.8 2.2 2 1.2-.3 2.3 2 1.2 1.9-1.3 2 .9L12 21l1.4-2.7 2-.9 1.9 1.3 2-1.2-.3-2.3 2-1.2-.8-2.2z',
  'login'=>'M11 2.6h6a1 1 0 0 1 1 1v16.8a1 1 0 0 1-1 1h-6v-2h5V4.6h-5zM3 11h8.4l-2.7-2.7 1.4-1.4L15 11.9l-4.9 5-1.4-1.4L11.4 13H3z',
  'arrow'=>'M9 18l6-6-6-6',
  'search'=>'M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z',
];
$svg=fn($n,$s=20)=>'<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="'.($n==='arrow'||$n==='search'?'none':'currentColor').'" stroke="'.($n==='arrow'||$n==='search'?'currentColor':'none').'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="'.($I[$n]??$I['grid']).'"/></svg>';
// warna aksen per grup
$warna=['Kesantrian'=>'green','Perizinan & Keamanan'=>'amber','Asrama & Pengasuhan'=>'sky','Kesehatan & Konseling'=>'rose','Akademik'=>'violet','PPDB'=>'orange','Keuangan'=>'teal','Website & Pengaturan'=>'slate'];
@endphp
<div class="app">
  <div class="scroll-area">
    {{-- hero --}}
    <div class="hero">
      <div class="hero-top">
        <div class="logo">@if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else<span>🕌</span>@endif</div>
        <div class="hero-info">
          <div class="hero-greeting">{{ $sapa }},</div>
          <div class="hero-name">{{ $user->name }}</div>
        </div>
      </div>
      @if($peran)<span class="role-chip">{{ $peran }}</span>@endif
    </div>

    {{-- search --}}
    <div class="search-wrap">
      {!! $svg('search',18) !!}
      <input id="search" type="search" placeholder="Cari menu…" autocomplete="off" autocorrect="off">
    </div>

    {{-- menu groups --}}
    <div id="menu-root">
      @forelse($groups as $judul => $items)
        @php $c=$warna[$judul]??'green'; @endphp
        <div class="group" data-c="{{ $c }}" data-group>
          <div class="group-header">
            <span class="group-title">{{ $judul }}</span>
            <span class="group-count">{{ count($items) }}</span>
          </div>
          <div class="menu-list">
            @foreach($items as $it)
              <a class="menu-item" href="{{ $it[3] }}" data-label="{{ mb_strtolower($it[0]) }}">
                <span class="menu-icon">{!! $svg($it[1],20) !!}</span>
                <span class="menu-label">{{ $it[0] }}</span>
                <span class="menu-arrow">{!! $svg('arrow',16) !!}</span>
              </a>
            @endforeach
          </div>
        </div>
      @empty
        <div style="text-align:center;padding:48px 24px;color:#6b7280;font-size:14px">
          Belum ada menu untuk peran Anda.<br>Hubungi admin pesantren.
        </div>
      @endforelse
    </div>
    <div id="no-result">Tidak ada menu yang cocok.</div>
    <div style="height:16px"></div>
  </div>

  {{-- bottom nav --}}
  <nav class="nav">
    <a class="on" href="/staf">
      {!! $svg('home',22) !!}
      Beranda
    </a>
    <a href="{{ $adminUrl }}">
      {!! $svg('grid',22) !!}
      Panel Lengkap
    </a>
    <a href="/beranda">
      {!! $svg('book',22) !!}
      Publik
    </a>
    <button onclick="document.getElementById('frmKeluar').submit()">
      {!! $svg('login',22) !!}
      Keluar
    </button>
  </nav>
</div>
<form id="frmKeluar" method="POST" action="/keluar" style="display:none">@csrf</form>

<script>
(function(){
  const inp=document.getElementById('search');
  const noRes=document.getElementById('no-result');
  const groups=[...document.querySelectorAll('[data-group]')];
  inp.addEventListener('input',function(){
    const q=this.value.trim().toLowerCase();
    let total=0;
    groups.forEach(function(g){
      const items=[...g.querySelectorAll('.menu-item')];
      let vis=0;
      items.forEach(function(a){
        const match=!q||a.dataset.label.includes(q);
        a.style.display=match?'':'none';
        if(match)vis++;
      });
      g.style.display=vis?'':'none';
      total+=vis;
    });
    noRes.style.display=total?'none':'block';
  });
})();
</script>
</body>
</html>
