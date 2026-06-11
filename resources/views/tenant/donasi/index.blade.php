<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donasi — {{ $nama }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8faf9; color: #1c1917; margin: 0; }
        main { max-width: 760px; margin: 0 auto; padding: 48px 20px 80px; }
        h1 { font-size: 1.5rem; color: #064e3b; }
        .kartu { background: #fff; border: 1px solid #e7e5e4; border-radius: 16px; padding: 28px; margin-top: 20px; }
        .program { border-bottom: 1px solid #f1f0ee; padding: 14px 0; }
        .program:last-child { border-bottom: none; }
        .program h3 { margin: 0 0 4px; font-size: 1.05rem; color: #064e3b; }
        .program p { margin: 0; font-size: .9rem; color: #57534e; }
        label { display: block; font-size: .85rem; font-weight: 600; margin: 14px 0 4px; }
        input, select, textarea { width: 100%; box-sizing: border-box; border: 1px solid #d6d3d1; border-radius: 10px; padding: 10px 12px; font: inherit; }
        button { margin-top: 18px; background: #065f46; color: #fff; border: 0; border-radius: 10px; padding: 12px 22px; font: inherit; font-weight: 600; cursor: pointer; }
        .galat { color: #b91c1c; font-size: .85rem; margin-top: 8px; }
    </style>
</head>
<body>
<main>
    <h1>Donasi &amp; Infaq<br><span style="font-size:1rem;color:#57534e">{{ $nama }}</span></h1>

    <div class="kartu">
        <h2 style="margin-top:0;font-size:1.1rem">Program Donasi Aktif</h2>
        @forelse ($programs as $program)
            <div class="program">
                <h3>{{ $program->nama }}</h3>
                @if ($program->deskripsi)<p>{{ $program->deskripsi }}</p>@endif
                @if ($program->target_nominal)
                    <p>Target: Rp {{ number_format((float) $program->target_nominal, 0, ',', '.') }}</p>
                @endif
            </div>
        @empty
            <p>Belum ada program donasi aktif saat ini.</p>
        @endforelse
    </div>

    @if ($programs->isNotEmpty())
    <div class="kartu">
        <h2 style="margin-top:0;font-size:1.1rem">Form Niat Donasi</h2>
        @if ($errors->any())
            <div class="galat">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('tenant.donasi.store') }}">
            @csrf
            <label for="program_donasi_id">Program</label>
            <select id="program_donasi_id" name="program_donasi_id" required>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(old('program_donasi_id') == $program->id)>{{ $program->nama }}</option>
                @endforeach
            </select>

            <label for="nama_donatur">Nama</label>
            <input id="nama_donatur" name="nama_donatur" value="{{ old('nama_donatur') }}" required maxlength="150">

            <label for="kontak">No. WhatsApp</label>
            <input id="kontak" name="kontak" value="{{ old('kontak') }}" maxlength="50" placeholder="08xxxxxxxxxx">

            <label for="nominal">Nominal (Rp)</label>
            <input id="nominal" name="nominal" type="number" min="1000" step="1" value="{{ old('nominal') }}" required>

            <label for="pesan">Pesan / Doa (opsional)</label>
            <textarea id="pesan" name="pesan" rows="3" maxlength="1000">{{ old('pesan') }}</textarea>

            <button type="submit">Kirim Niat Donasi</button>
        </form>
    </div>
    @endif
</main>
</body>
</html>
