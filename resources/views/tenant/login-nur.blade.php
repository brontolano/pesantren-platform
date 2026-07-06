<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#0b6e4f">
<title>Masuk — {{ $pesantren }}</title>
@if($logoUrl)<link rel="apple-touch-icon" href="{{ $logoUrl }}"><link rel="icon" href="{{ $logoUrl }}">@endif
<script>try{if(localStorage.getItem('nur-theme')==='dark')document.documentElement.classList.add('dark');}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Amiri&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--nur:#0b6e4f;--nur-strong:#085840;--nur-tint:#ecfdf6;--gold:#c9a24a;--gold-soft:#e7d3a1;--ink:#111;--sub:#64748b;--faint:#94a3b8;--line:#efe7da;--card:#fff;--bg:#fffaf3;--grad:linear-gradient(160deg,#17a179 0%,#0b6e4f 48%,#062a20 100%);--r:16px;--sh:inset 0 0 0 1px var(--line),0 2px 4px -2px rgba(17,17,17,.06),0 12px 28px -16px rgba(17,17,17,.18);--f:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif}
html.dark{--nur:#2fbf85;--nur-strong:#26a874;--nur-tint:#12271f;--ink:#eef2f0;--sub:#9fb0a8;--faint:#6b7a73;--line:#26312c;--card:#161e1a;--bg:#0d1411;--sh:inset 0 0 0 1px var(--line),0 10px 26px -18px rgba(0,0,0,.7)}
html.dark body{background:var(--bg)}
html,body{min-height:100%;font-family:var(--f);background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}button{font-family:inherit;color:inherit}
@media(prefers-reduced-motion:reduce){*{transition-duration:.001ms!important}}
.wrap{max-width:480px;margin:0 auto;min-height:100dvh;display:flex;flex-direction:column}
.hero{background:var(--grad);color:#fff;padding:calc(40px + env(safe-area-inset-top)) 24px 56px;position:relative;overflow:hidden;border-bottom-left-radius:32px;border-bottom-right-radius:32px;text-align:center}
.hero-pat{position:absolute;inset:0;background-image:url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2244%22 height=%2244%22 viewBox=%220 0 44 44%22%3E%3Cg fill=%22none%22 stroke=%22%23ffffff%22 stroke-width=%221%22 opacity=%220.5%22%3E%3Crect x=%2213%22 y=%2213%22 width=%2218%22 height=%2218%22/%3E%3Crect x=%2213%22 y=%2213%22 width=%2218%22 height=%2218%22 transform=%22rotate(45 22 22)%22/%3E%3C/g%3E%3C/svg%3E");background-size:44px 44px;opacity:.09}
.hero-glow{position:absolute;left:50%;top:-60px;transform:translateX(-50%);width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(231,211,161,.25),transparent 66%)}
.brandmark{width:76px;height:76px;border-radius:22px;overflow:hidden;background:rgba(255,255,255,.16);border:1px solid rgba(231,211,161,.4);display:flex;align-items:center;justify-content:center;font-size:38px;margin:0 auto 16px;position:relative;box-shadow:0 12px 30px -14px rgba(0,0,0,.5)}
.brandmark img{width:100%;height:100%;object-fit:cover}
.bismillah{font-family:'Amiri',serif;font-size:1.7rem;color:var(--gold-soft);position:relative;margin-bottom:8px}
.hero h1{font-size:1.4rem;font-weight:800;position:relative}
.hero .sub{font-size:.85rem;color:#c9f2e2;margin-top:5px;position:relative;font-weight:500}
.themebtn{position:absolute;top:calc(16px + env(safe-area-inset-top));right:18px;z-index:3;width:38px;height:38px;border-radius:99px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.16);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer}
.sheet{flex:1;margin-top:-32px;background:var(--bg);border-top-left-radius:32px;border-top-right-radius:32px;position:relative;z-index:2;padding:30px 22px calc(96px + env(safe-area-inset-bottom))}
.card{background:var(--card);border-radius:22px;box-shadow:var(--sh);padding:22px 20px}
.card-t{font-size:1.05rem;font-weight:800;text-align:center}
.card-s{font-size:.8rem;color:var(--sub);text-align:center;margin-top:3px}
form{margin-top:20px;display:flex;flex-direction:column;gap:14px}
.field label{display:block;font-size:.78rem;font-weight:700;margin-bottom:7px;color:var(--ink)}
.inputwrap{position:relative}
.inputwrap>.lead{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--faint);pointer-events:none}
.inputwrap>.lead svg{width:19px;height:19px}
.field input{width:100%;border:1.5px solid var(--line);background:var(--bg);border-radius:14px;padding:14px 44px 14px 42px;font-size:.95rem;font-family:inherit;color:var(--ink);transition:border-color .15s,box-shadow .15s}
.field input:focus{outline:none;border-color:var(--nur);box-shadow:0 0 0 3px rgba(11,110,79,.12)}
.field input::placeholder{color:var(--faint)}
.eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--faint);cursor:pointer;padding:6px;display:flex}
.row{display:flex;align-items:center;justify-content:space-between}
.remember{display:flex;align-items:center;gap:8px;font-size:.82rem;color:var(--sub);font-weight:600;cursor:pointer}
.remember input{width:17px;height:17px;accent-color:var(--nur)}
.forgot{font-size:.8rem;color:var(--nur);font-weight:700}
.err{background:var(--terra-tint,#fef3ee);border:1px solid #fecdd3;color:#be123c;border-radius:12px;padding:11px 14px;font-size:.82rem;margin-top:16px;font-weight:600}
html.dark .err{background:#33180f;border-color:#7f1d1d;color:#fda4c0}
.btn{appearance:none;border:none;cursor:pointer;width:100%;border-radius:9999px;padding:15px;font-size:1rem;font-weight:800;font-family:inherit;color:#fff;background:linear-gradient(135deg,#17a179,#0b6e4f);box-shadow:0 10px 22px -12px rgba(11,110,79,.7);transition:transform .15s;margin-top:2px}
html.dark .btn{background:linear-gradient(135deg,#2fbf85,#0b6e4f)}
.btn:active{transform:scale(.97)}
.divider{display:flex;align-items:center;gap:12px;margin:22px 4px 16px;color:var(--faint);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
.divider::before,.divider::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,transparent,var(--gold-soft),transparent);opacity:.6}
.alt{background:var(--card);border-radius:var(--r);padding:15px 16px;box-shadow:var(--sh);display:flex;flex-direction:row;align-items:center;gap:13px}
.alt-ic{width:40px;height:40px;border-radius:12px;background:var(--nur-tint);color:var(--nur);display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.alt .t{font-weight:700;font-size:.9rem;display:block}.alt .d{font-size:.75rem;color:var(--sub);line-height:1.3;display:block;margin-top:2px}
.alt-arrow{color:#cbd5e1;flex:0 0 auto}
footer{text-align:center;font-size:.72rem;color:var(--faint);margin-top:22px;font-weight:500}
footer a{color:var(--nur);font-weight:700}
.nav{position:fixed;bottom:0;left:0;right:0;max-width:480px;margin:0 auto;background:rgba(255,255,255,.96);backdrop-filter:blur(16px);border-top:1px solid var(--line);display:flex;padding:9px 8px calc(12px + env(safe-area-inset-bottom));z-index:30;box-shadow:0 -8px 24px -18px rgba(17,17,17,.3)}
html.dark .nav{background:rgba(18,24,21,.96)}
/* KUNCI IDENTIFIKASI DEVICE di halaman login (owner 2026-07-06):
   desktop (pointer halus & layar lebar) = tampilan login DESKTOP — TANPA
   bottom-nav ala app HP. HP tetap bentuk app.
   Refresh 2026-07-06: sebelumnya kartu HP cuma disempitkan & ditengahkan
   di kanvas kosong — sekarang split-screen sungguhan (branding kiri,
   form kanan) mengisi seluruh viewport, bukan kartu mengambang. */
@media(min-width:900px) and (pointer:fine){
  .nav{display:none}
  body{overflow:hidden}
  .wrap{max-width:none;min-height:100dvh;margin:0;flex-direction:row}
  .hero{
    flex:1 1 46%;max-width:640px;min-height:100dvh;display:flex;flex-direction:column;
    align-items:center;justify-content:center;border-radius:0;padding:48px;
  }
  .hero-glow{top:auto;bottom:-80px}
  .brandmark{width:104px;height:104px;font-size:52px;margin-bottom:22px}
  .bismillah{font-size:2.1rem}
  .hero h1{font-size:2rem;max-width:22ch;text-wrap:balance}
  .hero .sub{font-size:1rem;margin-top:8px}
  .sheet{
    flex:1 1 54%;margin-top:0;border-radius:0;display:flex;flex-direction:column;
    align-items:center;justify-content:center;padding:48px;overflow-y:auto;
  }
  .sheet>*{width:100%;max-width:400px}
  .card{padding:32px 30px}
  .card-t{font-size:1.3rem}
  .card-s{font-size:.88rem}
}
@media(min-width:1180px) and (pointer:fine){
  .hero{flex-basis:50%}
  .sheet{flex-basis:50%}
}
.nav a{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px;font-size:10.5px;font-weight:800;color:var(--faint)}
.nav a.on{color:var(--nur)}
.nav .ic{width:52px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:99px}
.nav a.on .ic{background:var(--nur-tint)}
</style>
</head>
<body>
@php
$sv=fn($inner,$s=20)=>'<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$inner.'</svg>';
$icMail='<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>';
$icLock='<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>';
$icEye='<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>';
$icDoc='<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>';
$icHome='<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>';
$icMosque='<path d="M4 22V11l8-6 8 6v11"/><path d="M8 22v-5a4 4 0 0 1 8 0v5"/><path d="M2 22h20"/>';
$icNews='<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8M15 18h-5M10 6h8v4h-8Z"/>';
$icLogin='<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><path d="M15 12H3"/>';
$icArrow='<path d="m9 18 6-6-6-6"/>';
$icMoon='<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>';
$icSun='<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>';
@endphp
<div class="wrap">
  <div class="hero">
    <div class="hero-pat"></div><div class="hero-glow"></div>
    <button class="themebtn" id="fabTema" aria-label="Ganti tema">{!! $sv($icMoon,18) !!}</button>
    <div class="brandmark">@if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else🕌@endif</div>
    <div class="bismillah">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</div>
    <h1>{{ $pesantren }}</h1>
    <div class="sub">Portal Sistem Terpadu</div>
  </div>
  <div class="sheet">
    <div class="card">
      <div class="card-t">Masuk</div>
      <div class="card-s">Silakan masuk untuk melanjutkan</div>
      @if ($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
      <form method="POST" action="{{ route('tenant.masuk.store') }}">
        @csrf
        {{-- Sinyal device NYATA (viewport/pointer) → server memutuskan tujuan
             pasca-login: desktop = panel admin, HP = PWA. Lebih akurat dari
             User-Agent (mis. iPad baru lapor UA desktop). Fallback ke UA bila
             JS mati (field kosong). --}}
        <input type="hidden" name="dv" id="dv" value="">
        <div class="field"><label for="email">Email</label>
          <div class="inputwrap"><span class="lead">{!! $sv($icMail) !!}</span><input id="email" name="email" type="email" autocomplete="username" required autofocus value="{{ old('email') }}" placeholder="nama@pesantren.id"></div>
        </div>
        <div class="field"><label for="password">Kata Sandi</label>
          <div class="inputwrap"><span class="lead">{!! $sv($icLock) !!}</span><input id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••••"><button type="button" class="eye" aria-label="Lihat sandi" onclick="var p=document.getElementById('password');p.type=p.type==='password'?'text':'password'">{!! $sv($icEye) !!}</button></div>
        </div>
        <div class="row"><label class="remember"><input type="checkbox" name="remember" value="1"> Ingat saya</label></div>
        <button class="btn" type="submit">Masuk</button>
      </form>
    </div>
    <div class="divider">Belum punya akun?</div>
    <a class="alt" href="{{ $ppdbUrl }}"><span class="alt-ic">{!! $sv($icDoc,18) !!}</span><span style="flex:1"><span class="t">Pendaftaran Santri Baru (PPDB)</span><span class="d">Untuk calon santri yang ingin mendaftar</span></span><span class="alt-arrow">{!! $sv($icArrow,16) !!}</span></a>
    <footer>&copy; {{ date('Y') }} {{ $pesantren }} &middot; <a href="/kebijakan-privasi">Kebijakan Privasi</a></footer>
  </div>
</div>
<nav class="nav">
  <a href="/beranda"><span class="ic">{!! $sv($icHome,22) !!}</span>Beranda</a>
  <a href="/beranda?tab=ibadah"><span class="ic">{!! $sv($icMosque,22) !!}</span>Ibadah</a>
  <a href="/beranda?tab=kabar"><span class="ic">{!! $sv($icNews,22) !!}</span>Kabar</a>
  <a class="on" href="{{ route('tenant.masuk.show') }}"><span class="ic">{!! $sv($icLogin,22) !!}</span>Masuk</a>
</nav>
<script>
(function(){var f=document.getElementById('fabTema');if(!f)return;var moon='{!! $sv($icMoon,18) !!}',sun='{!! $sv($icSun,18) !!}';function s(){f.innerHTML=document.documentElement.classList.contains('dark')?sun:moon;}s();f.addEventListener('click',function(){var d=document.documentElement.classList.toggle('dark');try{localStorage.setItem('nur-theme',d?'dark':'light');}catch(e){}s();});})();
// Deteksi device NYATA saat login: pointer kasar (sentuh) ATAU viewport sempit
// = HP → PWA; selain itu desktop → panel. Diset saat muat & tiap submit.
(function(){var dv=document.getElementById('dv');if(!dv)return;function set(){var m=(window.matchMedia&&window.matchMedia('(pointer: coarse)').matches)||window.innerWidth<768;dv.value=m?'m':'d';}set();if(dv.form)dv.form.addEventListener('submit',set);})();
</script>
</body>
</html>
