<!DOCTYPE html>
@php
    $namaPesantren = $s['branding_name'] ?? $tenant->name;
    $tagline = $s['branding_tagline'] ?? '';
@endphp
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pendaftaran PPDB — {{ $namaPesantren }}</title>
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --primary: #15803d; --primary-dark: #14532d; --gold: #ca8a04; --bg: #f8faf9; --text: #1e293b; --text-muted: #64748b; --border: #d1d9d4; --error: #dc2626; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; -webkit-font-smoothing: antialiased; }
        a { color: var(--primary); text-decoration: none; }
        nav { background: #fff; border-bottom: 1px solid var(--border); padding: 0 1.5rem; position: sticky; top: 0; z-index: 50; }
        .nav-inner { max-width: 720px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .nav-logo { display: flex; align-items: center; gap: 0.6rem; font-weight: 800; color: var(--primary-dark); }
        .nav-logo .mark { width: 34px; height: 34px; background: var(--primary); color: #fff; border-radius: 9px; display: flex; align-items: center; justify-content: center; }
        .nav-back { font-size: 0.85rem; color: var(--text-muted); }
        .nav-back:hover { color: var(--primary); }
        main { max-width: 720px; margin: 0 auto; padding: 2.5rem 1.5rem 4rem; }
        .page-head { text-align: center; margin-bottom: 2rem; }
        .page-head .badge { display: inline-block; background: #ecfdf3; color: var(--primary); font-size: 0.8rem; font-weight: 600; padding: 0.3rem 0.9rem; border-radius: 9999px; margin-bottom: 0.75rem; }
        .page-head h1 { font-size: 1.85rem; font-weight: 800; color: var(--primary-dark); margin-bottom: 0.4rem; }
        .page-head p { color: var(--text-muted); font-size: 0.95rem; }

        .card { background: #fff; border: 1px solid var(--border); border-radius: 14px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .form-section-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gold); margin: 1.75rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); }
        .form-section-title:first-child { margin-top: 0; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .field { margin-bottom: 1rem; }
        .field label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; }
        .field label .req { color: var(--error); }
        .field input, .field select, .field textarea { width: 100%; padding: 0.7rem 0.85rem; border: 1.5px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 0.92rem; color: var(--text); background: #fff; transition: border-color 0.2s; }
        .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--primary); }
        .field textarea { resize: vertical; min-height: 80px; }
        .field .err { color: var(--error); font-size: 0.8rem; margin-top: 0.3rem; }
        .btn-submit { width: 100%; background: var(--primary); color: #fff; border: none; padding: 0.9rem; border-radius: 9px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background 0.2s; margin-top: 1.5rem; }
        .btn-submit:hover { background: var(--primary-dark); }
        .note { font-size: 0.82rem; color: var(--text-muted); text-align: center; margin-top: 1rem; }

        .success-card { background: #fff; border: 1px solid #bbf7d0; border-radius: 16px; padding: 3rem 2rem; text-align: center; box-shadow: 0 8px 30px rgba(21,128,61,0.08); }
        .success-icon { width: 72px; height: 72px; background: #ecfdf3; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.25rem; margin: 0 auto 1.5rem; }
        .success-card h2 { font-size: 1.6rem; font-weight: 800; color: var(--primary-dark); margin-bottom: 0.75rem; }
        .success-card p { color: var(--text-muted); margin-bottom: 1.5rem; }
        .reg-number { background: var(--bg); border: 1px dashed var(--primary); border-radius: 10px; padding: 1.25rem; margin: 1.5rem 0; }
        .reg-number .label { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.3rem; }
        .reg-number .number { font-size: 1.5rem; font-weight: 800; color: var(--primary); letter-spacing: 0.05em; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-error ul { margin: 0.5rem 0 0; padding-left: 1.25rem; }

        @media (max-width: 600px) { .grid2 { grid-template-columns: 1fr; } .card { padding: 1.5rem; } }
    </style>
</head>
<body>

<nav>
    <div class="nav-inner">
        <a href="/" class="nav-logo"><span class="mark">{{ mb_substr($namaPesantren, 0, 1) }}</span><span>{{ $namaPesantren }}</span></a>
        <a href="/" class="nav-back">← Beranda</a>
    </div>
</nav>

<main>
@if($success)
    <div class="success-card">
        <div class="success-icon">✓</div>
        <h2>Pendaftaran Berhasil!</h2>
        <p>Terima kasih <strong>{{ $success['nama'] }}</strong>, pendaftaran Anda telah kami terima.</p>
        <div class="reg-number">
            <div class="label">Nomor Pendaftaran Anda</div>
            <div class="number">{{ $success['nomor'] }}</div>
        </div>
        <p>Simpan nomor pendaftaran ini. Panitia PPDB akan menghubungi Anda melalui WhatsApp untuk proses selanjutnya.</p>
        <a href="/" class="btn-submit" style="display:inline-block; text-decoration:none; width:auto; padding:0.8rem 2rem;">Kembali ke Beranda</a>
    </div>
@else
    <div class="page-head">
        <div class="badge">PPDB 2026/2027</div>
        <h1>Formulir Pendaftaran</h1>
        <p>Lengkapi data calon santri di bawah ini dengan benar.</p>
    </div>

    <div class="card">
        @if($errors->any())
        <div class="alert-error">
            <strong>Mohon periksa kembali isian Anda:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route('tenant.ppdb.store') }}">
            @csrf

            <div class="form-section-title">Data Calon Santri</div>
            <div class="field">
                <label>Nama Lengkap <span class="req">*</span></label>
                <input type="text" name="nama" value="{{ old('nama') }}" required placeholder="Nama lengkap calon santri">
            </div>
            <div class="grid2">
                <div class="field">
                    <label>Jenis Kelamin <span class="req">*</span></label>
                    <select name="jenis_kelamin" required>
                        <option value="">— Pilih —</option>
                        <option value="L" @selected(old('jenis_kelamin')==='L')>Laki-laki</option>
                        <option value="P" @selected(old('jenis_kelamin')==='P')>Perempuan</option>
                    </select>
                </div>
                <div class="field">
                    <label>Program Diminati <span class="req">*</span></label>
                    <select name="program_diminati" required>
                        <option value="">— Pilih —</option>
                        <option value="Tahfidz" @selected(old('program_diminati')==='Tahfidz')>Tahfidzul Quran</option>
                        <option value="Kitab Kuning" @selected(old('program_diminati')==='Kitab Kuning')>Kajian Kitab Kuning</option>
                        <option value="Reguler" @selected(old('program_diminati')==='Reguler')>Reguler (Formal + Pesantren)</option>
                    </select>
                </div>
            </div>
            <div class="grid2">
                <div class="field">
                    <label>Tempat Lahir <span class="req">*</span></label>
                    <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}" required placeholder="Kota kelahiran">
                </div>
                <div class="field">
                    <label>Tanggal Lahir <span class="req">*</span></label>
                    <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" required>
                </div>
            </div>
            <div class="field">
                <label>Asal Sekolah</label>
                <input type="text" name="asal_sekolah" value="{{ old('asal_sekolah') }}" placeholder="SD/MI atau SMP/MTs asal (opsional)">
            </div>

            <div class="form-section-title">Data Orang Tua / Wali</div>
            <div class="grid2">
                <div class="field">
                    <label>Nama Ayah / Wali <span class="req">*</span></label>
                    <input type="text" name="nama_ayah" value="{{ old('nama_ayah') }}" required placeholder="Nama ayah atau wali">
                </div>
                <div class="field">
                    <label>Nama Ibu</label>
                    <input type="text" name="nama_ibu" value="{{ old('nama_ibu') }}" placeholder="Nama ibu (opsional)">
                </div>
            </div>
            <div class="grid2">
                <div class="field">
                    <label>No. HP / WhatsApp Wali <span class="req">*</span></label>
                    <input type="text" name="no_hp_wali" value="{{ old('no_hp_wali') }}" required placeholder="08xxxxxxxxxx">
                </div>
                <div class="field">
                    <label>Email Wali</label>
                    <input type="email" name="email_wali" value="{{ old('email_wali') }}" placeholder="email@contoh.com (opsional)">
                </div>
            </div>
            <div class="field">
                <label>Alamat Lengkap <span class="req">*</span></label>
                <textarea name="alamat" required placeholder="Alamat tempat tinggal lengkap">{{ old('alamat') }}</textarea>
            </div>

            <div class="field" style="display:flex;gap:10px;align-items:flex-start">
                <input type="checkbox" name="setuju_kebijakan" id="setuju_kebijakan" value="1"
                    style="margin-top:4px;width:18px;height:18px" {{ old('setuju_kebijakan') ? 'checked' : '' }} required>
                <label for="setuju_kebijakan" style="font-weight:normal;font-size:.9rem;line-height:1.5">
                    Saya (orang tua/wali) telah membaca dan menyetujui
                    <a href="{{ url('/kebijakan-privasi') }}" target="_blank" rel="noopener" style="color:#047857;text-decoration:underline">Kebijakan Privasi Sistem</a>
                    — termasuk penggunaan data, foto, dan contoh tulisan anak untuk keperluan pendidikan internal pesantren. <span class="req">*</span>
                </label>
            </div>
            @error('setuju_kebijakan')<p class="note" style="color:#b91c1c">{{ $message }}</p>@enderror

            <button type="submit" class="btn-submit">Kirim Pendaftaran</button>
        </form>
    </div>
@endif
</main>

</body>
</html>
