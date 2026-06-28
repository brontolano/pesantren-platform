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
    --grad:linear-gradient(135deg,#0e5c3a 0%,#0a4e31 60%,#0F5132 100%);
  }
  html,body{ margin:0; height:100%; }
  body{ font-family:'Plus Jakarta Sans',system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--cream); color:var(--ink); -webkit-font-smoothing:antialiased; }
  .wrap{ max-width:520px; margin:0 auto; min-height:100vh; display:flex; flex-direction:column; }
  .screen{ flex:1; overflow-y:auto; padding-bottom:96px; }
  .pad{ padding:18px 18px 0; }
  h1,h2,h3{ margin:0; }
  .disp{ font-weight:800; letter-spacing:-.01em; }
  .muted{ color:var(--muted); } .small{ font-size:12.5px; }
  .eyebrow{ font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.14em; color:var(--emr); }

  .hero{ background:var(--grad); color:#fff; padding:26px 20px 30px; border-radius:0 0 26px 26px; position:relative; overflow:hidden; }
  .hero::after{ content:""; position:absolute; right:-50px; top:-50px; width:170px; height:170px; border-radius:50%; background:radial-gradient(circle,#d8b461 0%,transparent 70%); opacity:.2; }
  .hero .logo{ width:54px; height:54px; border-radius:15px; background:rgba(255,255,255,.16); display:flex; align-items:center; justify-content:center; overflow:hidden; }
  .hero .logo img{ width:100%; height:100%; object-fit:cover; }
  .hero h1{ font-size:23px; margin-top:14px; line-height:1.2; }
  .hero p{ color:#d9e4dc; font-size:13.5px; margin:6px 0 0; line-height:1.5; }

  .card{ background:var(--card); border-radius:18px; box-shadow:inset 0 0 0 1px var(--line), 0 14px 30px -26px rgba(60,45,10,.3); padding:16px; }
  .row{ display:flex; gap:12px; }
  .btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; height:48px; border-radius:9999px; font-weight:700; font-size:14.5px; text-decoration:none; border:none; cursor:pointer; width:100%; transition:transform .12s; }
  .btn:active{ transform:scale(.97); }
  .btn-pri{ background:var(--emr); color:#fff; }
  .btn-gho{ background:transparent; color:var(--emr-deep); box-shadow:inset 0 0 0 1.5px var(--line); }
  .grid2{ display:grid; grid-template-columns:1fr 1fr; gap:11px; }

  .salat{ display:flex; gap:8px; overflow-x:auto; padding-bottom:4px; }
  .salat::-webkit-scrollbar{ height:0; }
  .salat .s{ flex:0 0 auto; min-width:74px; text-align:center; background:var(--card); border-radius:14px; padding:11px 6px; box-shadow:inset 0 0 0 1px var(--line); }
  .salat .s .nm{ font-size:11px; color:var(--muted); font-weight:600; }
  .salat .s .tm{ font-size:17px; font-weight:800; margin-top:3px; font-variant-numeric:tabular-nums; }
  .salat .s.next{ background:var(--emr); } .salat .s.next .nm{ color:#cdeede; } .salat .s.next .tm{ color:#fff; }

  .art{ display:flex; gap:13px; padding:13px 0; border-bottom:1px solid var(--line); text-decoration:none; color:inherit; }
  .art:last-child{ border-bottom:none; }
  .art img{ width:74px; height:74px; border-radius:13px; object-fit:cover; flex:0 0 auto; background:#eee; }
  .art .t{ font-weight:700; font-size:14.5px; line-height:1.3; }
  .art .x{ color:var(--muted); font-size:12px; margin-top:4px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .art .d{ color:var(--gold); font-size:11px; font-weight:600; margin-top:5px; }

  .li{ display:flex; align-items:center; gap:13px; padding:14px 0; border-bottom:1px solid var(--line); text-decoration:none; color:inherit; }
  .li:last-child{ border-bottom:none; }
  .li .ic{ width:40px; height:40px; border-radius:12px; background:#E6F4EE; color:var(--emr); display:flex; align-items:center; justify-content:center; flex:0 0 auto; }
  .li .nm{ font-weight:700; font-size:14.5px; } .li .sub{ color:var(--muted); font-size:12px; }
  .li .chev{ margin-left:auto; color:#c9bfa f; color:#c9bfaf; }
  .sectitle{ font-weight:800; font-size:16px; margin:22px 0 10px; }

  .empty{ text-align:center; color:var(--muted); font-size:13px; padding:28px 10px; }

  /* bottom nav */
  .nav{ position:fixed; left:0; right:0; bottom:0; max-width:520px; margin:0 auto; background:rgba(255,255,255,.96); backdrop-filter:blur(12px); box-shadow:0 -8px 24px -18px rgba(0,0,0,.4), inset 0 1px 0 var(--line); display:flex; padding:7px 6px calc(7px + env(safe-area-inset-bottom)); z-index:20; }
  .nav button{ flex:1; border:none; background:none; cursor:pointer; padding:5px 0; display:flex; flex-direction:column; align-items:center; gap:3px; font-size:10px; font-weight:600; color:var(--muted); }
  .nav button.on{ color:var(--emr); }
  .nav svg{ width:23px; height:23px; }
  .tabpane{ display:none; } .tabpane.on{ display:block; }
</style>
</head>
<body>
<div class="wrap">
  <div class="screen">
    @php
      // tentukan sholat berikutnya (untuk highlight) — jam:menit lokal
      $nowMin = (int)date('H')*60 + (int)date('i');
      $nextIdx = -1;
      foreach (($ibadah['jadwal'] ?? []) as $i => $s) {
        $t = $s['time'] ?? $s['waktu'] ?? '';
        if (preg_match('/(\d{1,2}):(\d{2})/', $t, $m)) {
          if (((int)$m[1]*60 + (int)$m[2]) >= $nowMin) { $nextIdx = $i; break; }
        }
      }
    @endphp

    {{-- ===== BERANDA ===== --}}
    <div class="tabpane on" data-pane="beranda">
      <div class="hero">
        <div class="logo">@if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else <span style="font-size:24px">🕌</span>@endif</div>
        <h1 class="disp">{{ $namaPesantren }}</h1>
        <p>{{ $tagline }}</p>
      </div>
      <div class="pad">
        @if(!empty($ibadah['jadwal']))
          <div class="eyebrow" style="margin:6px 0 9px">Jadwal sholat hari ini</div>
          <div class="salat">
            @foreach($ibadah['jadwal'] as $i => $s)
              <div class="s {{ $i===$nextIdx?'next':'' }}">
                <div class="nm">{{ $s['name'] ?? $s['nama'] ?? '' }}</div>
                <div class="tm">{{ $s['time'] ?? $s['waktu'] ?? '' }}</div>
              </div>
            @endforeach
          </div>
        @endif

        <div class="grid2" style="margin-top:16px">
          @if($ppdbAktif)<a class="btn btn-pri" href="/ppdb">Daftar PPDB</a>@endif
          @if($ibadah['donasiUrl'])<a class="btn btn-gho" href="{{ $ibadah['donasiUrl'] }}">Donasi / Infaq</a>@endif
        </div>

        @if($artikel->isNotEmpty())
          <div class="sectitle">Kabar terbaru</div>
          <div class="card" style="padding:2px 16px">
            @foreach($artikel->take(3) as $a)
              <a class="art" href="javascript:void(0)" onclick="buka('{{ $a->slug }}')">
                @if($a->featured_image_url)<img src="{{ $a->featured_image_url }}" alt="">@endif
                <div><div class="t">{{ $a->title }}</div><div class="x">{{ $a->excerpt }}</div>
                  <div class="d">{{ optional($a->published_at)->translatedFormat('d M Y') }}</div></div>
              </a>
            @endforeach
          </div>
          <button class="btn btn-gho" style="margin-top:12px" onclick="tab('kabar')">Lihat semua kabar</button>
        @endif
      </div>
    </div>

    {{-- ===== IBADAH ===== --}}
    <div class="tabpane" data-pane="ibadah">
      <div class="pad">
        <div class="eyebrow" style="margin-top:8px">Ibadah</div>
        <h2 class="disp" style="font-size:22px;margin:6px 0 16px">Jadwal Sholat &amp; Kiblat</h2>
        @if(!empty($ibadah['jadwal']))
          <div class="card" style="padding:6px 16px">
            @foreach($ibadah['jadwal'] as $i => $s)
              <div class="li">
                <div class="ic">🕌</div>
                <div class="nm">{{ $s['name'] ?? $s['nama'] ?? '' }}</div>
                <div style="margin-left:auto;font-weight:800;font-size:17px">{{ $s['time'] ?? $s['waktu'] ?? '' }}</div>
              </div>
            @endforeach
          </div>
          @if($ibadah['kiblat'] !== null)
            <div class="card" style="margin-top:14px;text-align:center">
              <div class="muted small">Arah kiblat</div>
              <div class="disp" style="font-size:34px;color:var(--emr-deep);margin-top:2px">{{ $ibadah['kiblat'] }}°</div>
              <div class="muted small">dari arah utara</div>
            </div>
          @endif
        @else
          <div class="empty">Jadwal sholat belum tersedia.<br>Lokasi pesantren belum diatur di pengaturan.</div>
        @endif
        @if($ibadah['donasiUrl'])<a class="btn btn-pri" style="margin-top:16px" href="{{ $ibadah['donasiUrl'] }}">Donasi / Infaq</a>@endif
      </div>
    </div>

    {{-- ===== KABAR ===== --}}
    <div class="tabpane" data-pane="kabar">
      <div class="pad">
        <div class="eyebrow" style="margin-top:8px">Kabar</div>
        <h2 class="disp" style="font-size:22px;margin:6px 0 14px">Berita &amp; Pengumuman</h2>
        @if($artikel->isNotEmpty())
          <div class="card" style="padding:2px 16px">
            @foreach($artikel as $a)
              <a class="art" href="javascript:void(0)" onclick="buka('{{ $a->slug }}')">
                @if($a->featured_image_url)<img src="{{ $a->featured_image_url }}" alt="">@endif
                <div><div class="t">{{ $a->title }}</div><div class="x">{{ $a->excerpt }}</div>
                  <div class="d">{{ optional($a->published_at)->translatedFormat('d M Y') }}</div></div>
              </a>
            @endforeach
          </div>
        @else
          <div class="empty">Belum ada kabar dipublikasikan.</div>
        @endif
      </div>
    </div>

    {{-- ===== AKUN ===== --}}
    <div class="tabpane" data-pane="akun">
      <div class="pad">
        <div class="eyebrow" style="margin-top:8px">Akun</div>
        @if($user)
          <h2 class="disp" style="font-size:22px;margin:6px 0 4px">{{ $user->name }}</h2>
          <p class="muted small" style="margin:0 0 16px">Anda sudah masuk.</p>
          <a class="btn btn-pri" href="{{ $authedTarget }}">Buka Dashboard</a>
          <form method="POST" action="/keluar" style="margin-top:10px">@csrf
            <button class="btn btn-gho" type="submit">Keluar</button></form>
        @else
          <h2 class="disp" style="font-size:22px;margin:6px 0 4px">Assalamu'alaikum</h2>
          <p class="muted small" style="margin:0 0 18px">Masuk untuk mengelola pesantren, atau daftar santri baru.</p>
          <a class="btn btn-pri" href="/masuk">Masuk ke akun</a>
          @if($ppdbAktif)<a class="btn btn-gho" style="margin-top:10px" href="/ppdb">Daftar santri baru (PPDB)</a>@endif
        @endif
      </div>
    </div>

    {{-- ===== INFO & KONTAK ===== --}}
    <div class="tabpane" data-pane="info">
      <div class="pad">
        <div class="eyebrow" style="margin-top:8px">Info &amp; Kontak</div>
        <h2 class="disp" style="font-size:22px;margin:6px 0 14px">{{ $namaPesantren }}</h2>
        <div class="card" style="padding:4px 16px">
          @if($kontak['alamat'])<div class="li"><div class="ic">📍</div><div><div class="nm">Alamat</div><div class="sub">{{ $kontak['alamat'] }}</div></div></div>@endif
          @if($kontak['telepon'])<a class="li" href="https://wa.me/{{ preg_replace('/[^0-9]/','', preg_replace('/^0/','62',$kontak['telepon'])) }}"><div class="ic">💬</div><div><div class="nm">WhatsApp / Telepon</div><div class="sub">{{ $kontak['telepon'] }}</div></div><div class="chev">›</div></a>@endif
          @if($kontak['email'])<a class="li" href="mailto:{{ $kontak['email'] }}"><div class="ic">✉️</div><div><div class="nm">Email</div><div class="sub">{{ $kontak['email'] }}</div></div><div class="chev">›</div></a>@endif
        </div>
        <div class="sectitle">Tentang Aplikasi</div>
        <div class="card">
          <p class="small muted" style="margin:0;line-height:1.6">Aplikasi resmi {{ $namaPesantren }} — layanan informasi, ibadah, kabar, dan pengelolaan pesantren dalam satu tempat. Versi 2.1</p>
        </div>
      </div>
    </div>
  </div>

  {{-- ===== BOTTOM NAV ===== --}}
  <div class="nav">
    <button class="on" data-tab="beranda" onclick="tab('beranda')"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.6 2.5 11h2.6v9.4h4.2v-5.8h5.4v5.8h4.2V11h2.6z"/></svg>Beranda</button>
    <button data-tab="ibadah" onclick="tab('ibadah')"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.2c1.5 1.4 2.1 2.6 0 4.3-2.1-1.7-1.5-2.9 0-4.3ZM5 9.4a7 7 0 0 1 14 0v1.1H5zM3.6 12.2h16.8v8.4h-5.2v-4.2a3.2 3.2 0 0 0-6.4 0v4.2H3.6z"/></svg>Ibadah</button>
    <button data-tab="kabar" onclick="tab('kabar')"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.2 2.4h7.6l5 5v14.2a1 1 0 0 1-1 1H6.2a1 1 0 0 1-1-1V3.4a1 1 0 0 1 1-1Zm7.6 1.6V8h4z"/></svg>Kabar</button>
    <button data-tab="akun" onclick="tab('akun')"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12.2a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Zm0 2c-4.1 0-7.2 2.2-7.2 5.1V21h14.4v-1.7c0-2.9-3.1-5.1-7.2-5.1Z"/></svg>Akun</button>
    <button data-tab="info" onclick="tab('info')"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.2a9.8 9.8 0 1 0 0 19.6 9.8 9.8 0 0 0 0-19.6Zm0 4.2a1.4 1.4 0 1 1 0 2.8 1.4 1.4 0 0 1 0-2.8Zm1.3 11.3h-2.6v-7h2.6z"/></svg>Info</button>
  </div>
</div>

<script>
  function tab(name){
    document.querySelectorAll('.tabpane').forEach(p=>p.classList.toggle('on', p.dataset.pane===name));
    document.querySelectorAll('.nav button').forEach(b=>b.classList.toggle('on', b.dataset.tab===name));
    document.querySelector('.screen').scrollTo(0,0);
  }
  // Buka artikel di tab sistem (sementara) — versi in-app reader menyusul.
  function buka(slug){ tab('kabar'); /* TODO: in-shell reader via /api/v1/posts/{slug} */ }
</script>
</body>
</html>
