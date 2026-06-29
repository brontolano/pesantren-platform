<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0F5132">
<title>{{ $namaPesantren }}</title>
<style>
  *,*::before,*::after{ box-sizing:border-box; }
  :root{
    --emr:#059669; --emr-deep:#0F5132; --ink:#1a1a17; --body:#41433c; --muted:#7c7768;
    --cream:#fffaf3; --card:#ffffff; --line:#efe7da; --gold:#b48a2e;
    --grad:linear-gradient(135deg,#0e5c3a 0%,#0a4e31 55%,#0F5132 100%);
  }
  html,body{ margin:0; height:100%; }
  body{ font-family:'Plus Jakarta Sans',system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--cream); color:var(--ink); -webkit-font-smoothing:antialiased; }
  .wrap{ max-width:520px; margin:0 auto; min-height:100vh; }
  .screen{ overflow-y:auto; padding-bottom:90px; }
  .pad{ padding:16px 16px 0; }
  h1,h2,h3{ margin:0; }
  .disp{ font-weight:800; letter-spacing:-.01em; }
  .muted{ color:var(--muted); } .small{ font-size:12.5px; }
  .eyebrow{ font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.14em; color:var(--emr); }
  a{ text-decoration:none; color:inherit; }

  .appbar{ display:flex; align-items:center; gap:11px; padding:14px 16px 6px; }
  .appbar .lg{ width:38px; height:38px; border-radius:11px; overflow:hidden; background:#E6F4EE; display:flex; align-items:center; justify-content:center; }
  .appbar .lg img{ width:100%; height:100%; object-fit:cover; }
  .appbar .nm{ font-weight:800; font-size:15px; line-height:1.15; }

  /* ── Banner jadwal sholat (ala dashboard) ── */
  .banner{ margin:8px 16px 0; background:var(--grad); color:#fff; border-radius:20px; padding:18px 18px 14px; position:relative; overflow:hidden; }
  .banner::after{ content:""; position:absolute; right:-44px; top:-44px; width:150px; height:150px; border-radius:50%; background:radial-gradient(circle,#d8b461 0%,transparent 70%); opacity:.22; }
  .banner .row1{ display:flex; align-items:center; justify-content:space-between; position:relative; }
  .banner .nx{ font-size:12px; color:#cdeede; font-weight:600; }
  .banner .nxt{ font-size:25px; font-weight:800; margin-top:2px; }
  .banner .kib{ background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.2); border-radius:12px; padding:8px 11px; text-align:center; }
  .banner .kib .v{ font-size:18px; font-weight:800; line-height:1; }
  .banner .kib .l{ font-size:9px; color:#cdeede; margin-top:3px; }
  .banner .strip{ display:flex; gap:6px; margin-top:14px; position:relative; }
  .banner .s{ flex:1; text-align:center; background:rgba(255,255,255,.10); border-radius:11px; padding:8px 2px; }
  .banner .s.on{ background:rgba(255,255,255,.92); }
  .banner .s .n{ font-size:10px; color:#cdeede; font-weight:600; }
  .banner .s.on .n{ color:var(--emr-deep); }
  .banner .s .t{ font-size:13px; font-weight:800; margin-top:2px; color:#fff; font-variant-numeric:tabular-nums; }
  .banner .s.on .t{ color:var(--emr-deep); }

  .sectitle{ font-weight:800; font-size:15px; margin:20px 0 11px; padding:0 16px; }

  /* ── Grid menu ikon ── */
  .grid{ display:grid; grid-template-columns:1fr 1fr; gap:11px; padding:0 16px; }
  .gitem{ background:var(--card); border-radius:16px; padding:15px; box-shadow:inset 0 0 0 1px var(--line); display:flex; align-items:center; gap:12px; transition:transform .12s; }
  .gitem:active{ transform:scale(.97); }
  .gitem .ic{ width:42px; height:42px; border-radius:12px; background:#E6F4EE; color:var(--emr); display:flex; align-items:center; justify-content:center; flex:0 0 auto; }
  .gitem .tx{ font-weight:700; font-size:13px; line-height:1.25; }
  .gitem .ex{ font-size:10px; color:var(--muted); margin-top:1px; }

  .card{ background:var(--card); border-radius:16px; box-shadow:inset 0 0 0 1px var(--line); margin:0 16px; }
  .sbtn{ display:flex; align-items:center; gap:12px; padding:14px 16px; border-bottom:1px solid var(--line); }
  .sbtn:last-child{ border-bottom:none; }
  .sbtn .ic{ width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center; flex:0 0 auto; color:#fff; }
  .sbtn .nm{ font-weight:700; font-size:14px; } .sbtn .sub{ font-size:11.5px; color:var(--muted); }
  .sbtn .chev{ margin-left:auto; color:#c9bfaf; }
  .ig{ background:#E1306C; } .yt{ background:#FF0000; } .fb{ background:#1877F2; }
  .em{ background:var(--emr); }

  .art{ display:flex; gap:12px; padding:12px 16px; border-bottom:1px solid var(--line); }
  .art:last-child{ border-bottom:none; }
  .art img{ width:70px; height:70px; border-radius:12px; object-fit:cover; flex:0 0 auto; background:#eee; }
  .art .t{ font-weight:700; font-size:14px; line-height:1.3; }
  .art .x{ color:var(--muted); font-size:12px; margin-top:3px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .art .d{ color:var(--gold); font-size:11px; font-weight:600; margin-top:4px; }

  .btn{ display:flex; align-items:center; justify-content:center; gap:8px; height:48px; margin:12px 16px 0; border-radius:9999px; font-weight:700; font-size:14.5px; }
  .btn-pri{ background:var(--emr); color:#fff; } .btn-gho{ background:transparent; color:var(--emr-deep); box-shadow:inset 0 0 0 1.5px var(--line); }
  .empty{ text-align:center; color:var(--muted); font-size:13px; padding:26px 16px; }

  .li{ display:flex; align-items:center; gap:12px; padding:14px 16px; border-bottom:1px solid var(--line); }
  .li:last-child{ border-bottom:none; }
  .li .ic{ width:38px; height:38px; border-radius:11px; background:#E6F4EE; color:var(--emr); display:flex; align-items:center; justify-content:center; flex:0 0 auto; }
  .li .nm{ font-weight:700; font-size:14px; } .li .val{ margin-left:auto; font-weight:800; font-size:16px; }

  .nav{ position:fixed; left:0; right:0; bottom:0; max-width:520px; margin:0 auto; background:rgba(255,255,255,.97); backdrop-filter:blur(12px); box-shadow:0 -8px 24px -18px rgba(0,0,0,.4), inset 0 1px 0 var(--line); display:flex; padding:7px 6px calc(7px + env(safe-area-inset-bottom)); z-index:20; }
  .nav a,.nav button{ flex:1; border:none; background:none; cursor:pointer; padding:5px 0; display:flex; flex-direction:column; align-items:center; gap:3px; font-size:10px; font-weight:600; color:var(--muted); font-family:inherit; }
  .nav .on{ color:var(--emr); }
  .nav svg{ width:23px; height:23px; }
  .tabpane{ display:none; } .tabpane.on{ display:block; }
</style>
</head>
<body>
@php
  // ikon SVG (fill=currentColor)
  $I = [
    'mosque' => 'M12 2.2c1.5 1.4 2.1 2.6 0 4.3-2.1-1.7-1.5-2.9 0-4.3ZM5 9.4a7 7 0 0 1 14 0v1.1H5zM3.6 12.2h16.8v8.4h-5.2v-4.2a3.2 3.2 0 0 0-6.4 0v4.2H3.6z',
    'user' => 'M12 12.2a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Zm0 2c-4.1 0-7.2 2.2-7.2 5.1V21h14.4v-1.7c0-2.9-3.1-5.1-7.2-5.1Z',
    'users' => 'M8.4 11.4a3.3 3.3 0 1 0 0-6.6 3.3 3.3 0 0 0 0 6.6Zm0 1.8c-3.5 0-6.4 1.9-6.4 4.3v1.7h12.8v-1.7c0-2.4-2.9-4.3-6.4-4.3Zm8-1.8a3 3 0 0 0 .7-5.9 4.9 4.9 0 0 1 0 5.6c.4.2.8.3 1.3.3Zm.6 1.8h-1.1c1 .9 1.6 2.1 1.6 3.5v1.5H22v-1.7c0-1.9-2.2-3.3-4.4-3.3Z',
    'grid' => 'M3.6 3.6h7v7h-7zM13.4 3.6h7v7h-7zM3.6 13.4h7v7h-7zM13.4 13.4h7v7h-7z',
    'camera' => 'M9 3.5 7.7 5.4H4.5a2 2 0 0 0-2 2v9.6a2 2 0 0 0 2 2h15a2 2 0 0 0 2-2V7.4a2 2 0 0 0-2-2h-3.2L15 3.5zM12 16.6a4.1 4.1 0 1 1 0-8.2 4.1 4.1 0 0 1 0 8.2Z',
    'play' => 'M5 3.6v16.8L19.5 12z',
    'compass' => 'M12 2.2a9.8 9.8 0 1 0 0 19.6 9.8 9.8 0 0 0 0-19.6Zm3.4 6.4-2 5-5 2 2-5z',
    'book' => 'M6 2.6h11.4a1 1 0 0 1 1 1v15.2a1 1 0 0 1-1 1H8.2a2.2 2.2 0 0 0-2.2 2.2zM8.4 5v9.2l2.3-1.4 2.3 1.4V5z',
    'heart' => 'M12 20.7 4.3 13a4.7 4.7 0 0 1 6.6-6.7l1.1 1 1.1-1A4.7 4.7 0 0 1 19.7 13z',
    'gift' => 'M20 7h-2.2a3 3 0 0 0-5.8-1.6A3 3 0 0 0 6.2 7H4v4h1v9h14v-9h1zM12 7V6.4M9.5 7a1 1 0 1 1 0-2c.9 0 1.6 1 2 2zm5 0h-2c.4-1 1.1-2 2-2a1 1 0 1 1 0 2z',
    'home' => 'M12 2.6 2.5 11h2.6v9.4h4.2v-5.8h5.4v5.8h4.2V11h2.6z',
    'newspaper' => 'M6.2 2.4h7.6l5 5v14.2a1 1 0 0 1-1 1H6.2a1 1 0 0 1-1-1V3.4a1 1 0 0 1 1-1Zm7.6 1.6V8h4z',
    'login' => 'M11 2.6h6a1 1 0 0 1 1 1v16.8a1 1 0 0 1-1 1h-6v-2h5V4.6h-5zM3 11h8.4l-2.7-2.7 1.4-1.4L15 11.9l-4.9 5-1.4-1.4L11.4 13H3z',
    'chev' => 'M9 5.6 15.4 12 9 18.4z',
  ];
  $svg = fn($n,$s=22) => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="currentColor"><path d="'.($I[$n]??'').'"/></svg>';

  $nowMin = (int)date('H')*60 + (int)date('i');
  $nextIdx = -1;
  foreach (($ibadah['jadwal'] ?? []) as $i => $s) {
    $t = $s['time'] ?? $s['waktu'] ?? '';
    if (preg_match('/(\d{1,2}):(\d{2})/', $t, $m) && ((int)$m[1]*60+(int)$m[2]) >= $nowMin) { $nextIdx = $i; break; }
  }
  $nextName = $nextIdx >= 0 ? ($ibadah['jadwal'][$nextIdx]['name'] ?? $ibadah['jadwal'][$nextIdx]['nama'] ?? '') : '';
  $nextTime = $nextIdx >= 0 ? ($ibadah['jadwal'][$nextIdx]['time'] ?? $ibadah['jadwal'][$nextIdx]['waktu'] ?? '') : '';
@endphp

<div class="wrap">
  <div class="screen">
    <div class="appbar">
      <div class="lg">@if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else<span>🕌</span>@endif</div>
      <div class="nm">{{ $namaPesantren }}</div>
    </div>

    {{-- ===== BERANDA ===== --}}
    <div class="tabpane on" data-pane="beranda">
      {{-- Banner jadwal sholat --}}
      @if(!empty($ibadah['jadwal']))
        <div class="banner">
          <div class="row1">
            <div>
              <div class="nx">Sholat berikutnya</div>
              <div class="nxt">{{ $nextName ?: 'Jadwal Sholat' }}@if($nextTime) · {{ $nextTime }}@endif</div>
            </div>
            @if($ibadah['kiblat'] !== null)
              <div class="kib"><div class="v">{{ $ibadah['kiblat'] }}°</div><div class="l">KIBLAT</div></div>
            @endif
          </div>
          <div class="strip">
            @foreach($ibadah['jadwal'] as $i => $s)
              <div class="s {{ $i===$nextIdx?'on':'' }}">
                <div class="n">{{ $s['name'] ?? $s['nama'] ?? '' }}</div>
                <div class="t">{{ $s['time'] ?? $s['waktu'] ?? '' }}</div>
              </div>
            @endforeach
          </div>
        </div>
      @endif

      {{-- Grid menu profil --}}
      <div class="sectitle">Tentang Pesantren</div>
      <div class="grid">
        <a class="gitem" href="{{ $cfg['app_profil_pondok_url'] }}"><span class="ic">{!! $svg('mosque',22) !!}</span><span><span class="tx">Profil Pondok</span><br><span class="ex">Sejarah &amp; visi misi</span></span></a>
        <a class="gitem" href="{{ $cfg['app_profil_abuya_url'] }}"><span class="ic">{!! $svg('user',22) !!}</span><span><span class="tx">Profil Abuya</span><br><span class="ex">Pengasuh pesantren</span></span></a>
        <a class="gitem" href="{{ $cfg['app_profil_guru_url'] }}"><span class="ic">{!! $svg('users',22) !!}</span><span><span class="tx">Profil Para Guru</span><br><span class="ex">Dewan asatidz</span></span></a>
        <a class="gitem" href="{{ $cfg['app_struktur_url'] }}"><span class="ic">{!! $svg('grid',22) !!}</span><span><span class="tx">Struktur Organisasi</span><br><span class="ex">Kepengurusan</span></span></a>
      </div>

      {{-- Galeri Foto (Instagram) --}}
      <div class="sectitle">Galeri Foto</div>
      <div class="card">
        <a class="sbtn" href="{{ $cfg['app_instagram_url'] }}"><span class="ic ig">{!! $svg('camera',20) !!}</span><span><span class="nm">Ponpes Asy-Syifaa</span><br><span class="sub">Instagram resmi pondok</span></span><span class="chev">{!! $svg('chev',16) !!}</span></a>
        <a class="sbtn" href="{{ $cfg['app_instagram2_url'] }}"><span class="ic ig">{!! $svg('camera',20) !!}</span><span><span class="nm">Asy-Syifaa Pusat</span><br><span class="sub">Instagram pusat</span></span><span class="chev">{!! $svg('chev',16) !!}</span></a>
        <a class="sbtn" href="{{ $cfg['app_instagram3_url'] }}"><span class="ic ig">{!! $svg('camera',20) !!}</span><span><span class="nm">Syifa Alawiyyah</span><br><span class="sub">Instagram putri</span></span><span class="chev">{!! $svg('chev',16) !!}</span></a>
      </div>

      {{-- Asy-Syifaa TV --}}
      <div class="sectitle">Asy-Syifaa TV</div>
      <div class="card">
        <a class="sbtn" href="{{ $cfg['app_youtube_url'] }}"><span class="ic yt">{!! $svg('play',18) !!}</span><span><span class="nm">YouTube</span><br><span class="sub">Video kajian &amp; kegiatan</span></span><span class="chev">{!! $svg('chev',16) !!}</span></a>
        <a class="sbtn" href="{{ $cfg['app_facebook_url'] }}"><span class="ic fb">{!! $svg('play',18) !!}</span><span><span class="nm">Facebook Live</span><br><span class="sub">Siaran langsung</span></span><span class="chev">{!! $svg('chev',16) !!}</span></a>
      </div>

      {{-- Kabar terbaru --}}
      @if($artikel->isNotEmpty())
        <div class="sectitle">Kabar Terbaru</div>
        <div class="card">
          @foreach($artikel->take(3) as $a)
            <div class="art">
              @if($a->featured_image_url)<img src="{{ $a->featured_image_url }}" alt="">@endif
              <div><div class="t">{{ $a->title }}</div><div class="x">{{ $a->excerpt }}</div>
                <div class="d">{{ optional($a->published_at)->translatedFormat('d M Y') }}</div></div>
            </div>
          @endforeach
        </div>
        <button class="btn btn-gho" onclick="tab('kabar')">Lihat semua kabar</button>
      @endif
      <div style="height:14px"></div>
    </div>

    {{-- ===== IBADAH ===== --}}
    <div class="tabpane" data-pane="ibadah">
      <div class="sectitle" style="margin-top:14px">Arah Kiblat &amp; Jadwal Sholat</div>
      @if(!empty($ibadah['jadwal']))
        <div class="card">
          @foreach($ibadah['jadwal'] as $s)
            <div class="li"><span class="ic">{!! $svg('mosque',18) !!}</span><span class="nm">{{ $s['name'] ?? $s['nama'] ?? '' }}</span><span class="val">{{ $s['time'] ?? $s['waktu'] ?? '' }}</span></div>
          @endforeach
        </div>
        @if($ibadah['kiblat'] !== null)
          <div class="card" style="margin-top:12px;text-align:center;padding:16px">
            <div class="muted small">Arah kiblat</div>
            <div class="disp" style="font-size:32px;color:var(--emr-deep);margin-top:2px">{{ $ibadah['kiblat'] }}°</div>
            <div class="muted small">dari arah utara</div>
          </div>
        @endif
      @else
        <div class="empty">Jadwal sholat belum tersedia (lokasi pesantren belum diatur).</div>
      @endif

      <div class="sectitle">Layanan Ibadah</div>
      <div class="card">
        @if($cfg['app_jadwal_kajian_url'])
          <a class="sbtn" href="{{ $cfg['app_jadwal_kajian_url'] }}"><span class="ic em">{!! $svg('book',18) !!}</span><span><span class="nm">Jadwal Kajian</span><br><span class="sub">Majelis &amp; pengajian</span></span><span class="chev">{!! $svg('chev',16) !!}</span></a>
        @endif
        @if($cfg['app_donasi_url'])
          <a class="sbtn" href="{{ $cfg['app_donasi_url'] }}"><span class="ic em">{!! $svg('heart',18) !!}</span><span><span class="nm">Donasi / Infaq</span><br><span class="sub">Salurkan kebaikan</span></span><span class="chev">{!! $svg('chev',16) !!}</span></a>
        @endif
        @if($cfg['app_zakat_wakaf_url'])
          <a class="sbtn" href="{{ $cfg['app_zakat_wakaf_url'] }}"><span class="ic em">{!! $svg('gift',18) !!}</span><span><span class="nm">Zakat &amp; Wakaf</span><br><span class="sub">Tunaikan zakat &amp; wakaf</span></span><span class="chev">{!! $svg('chev',16) !!}</span></a>
        @endif
        @if(!$cfg['app_jadwal_kajian_url'] && !$cfg['app_donasi_url'] && !$cfg['app_zakat_wakaf_url'])
          <div class="empty" style="padding:18px">Layanan ibadah belum diatur admin.</div>
        @endif
      </div>
      <div style="height:14px"></div>
    </div>

    {{-- ===== KABAR ===== --}}
    <div class="tabpane" data-pane="kabar">
      <div class="sectitle" style="margin-top:14px">Berita &amp; Pengumuman</div>
      @if($artikel->isNotEmpty())
        <div class="card">
          @foreach($artikel as $a)
            <div class="art">
              @if($a->featured_image_url)<img src="{{ $a->featured_image_url }}" alt="">@endif
              <div><div class="t">{{ $a->title }}</div><div class="x">{{ $a->excerpt }}</div>
                <div class="d">{{ optional($a->published_at)->translatedFormat('d M Y') }}</div></div>
            </div>
          @endforeach
        </div>
      @else
        <div class="empty">Belum ada kabar dipublikasikan.</div>
      @endif
      <div style="height:14px"></div>
    </div>
  </div>

  {{-- ===== BOTTOM NAV (Akun = tautan langsung ke login) ===== --}}
  <div class="nav">
    <button class="on" data-tab="beranda" onclick="tab('beranda')">{!! $svg('home',23) !!}Beranda</button>
    <button data-tab="ibadah" onclick="tab('ibadah')">{!! $svg('mosque',23) !!}Ibadah</button>
    <button data-tab="kabar" onclick="tab('kabar')">{!! $svg('newspaper',23) !!}Kabar</button>
    <a href="{{ $masukUrl }}">{!! $svg('login',23) !!}Akun</a>
  </div>
</div>

<script>
  function tab(name){
    document.querySelectorAll('.tabpane').forEach(p=>p.classList.toggle('on', p.dataset.pane===name));
    document.querySelectorAll('.nav button').forEach(b=>b.classList.toggle('on', b.dataset.tab===name));
    document.querySelector('.screen').scrollTo(0,0);
  }
</script>
</body>
</html>
