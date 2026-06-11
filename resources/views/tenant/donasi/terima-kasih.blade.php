<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terima Kasih — {{ $nama }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8faf9; color: #1c1917; margin: 0; }
        main { max-width: 680px; margin: 0 auto; padding: 48px 20px 80px; }
        h1 { font-size: 1.4rem; color: #064e3b; }
        .kartu { background: #fff; border: 1px solid #e7e5e4; border-radius: 16px; padding: 28px; margin-top: 20px; }
        .rekening { background: #ecfdf5; border-radius: 10px; padding: 16px; white-space: pre-wrap; font-size: .95rem; }
        .sukses { color: #065f46; font-weight: 600; }
        .galat { color: #b91c1c; font-size: .85rem; margin-top: 8px; }
        button { margin-top: 14px; background: #065f46; color: #fff; border: 0; border-radius: 10px; padding: 10px 20px; font: inherit; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
<main>
    <h1>Jazakumullahu khairan, {{ $donasi->nama_donatur }}</h1>

    <div class="kartu">
        <p>Niat donasi <strong>Rp {{ number_format((float) $donasi->nominal, 0, ',', '.') }}</strong>
            untuk program <strong>{{ $donasi->program?->nama }}</strong> telah kami catat
            (status: menunggu transfer).</p>
        <p>Silakan transfer ke rekening berikut:</p>
        <div class="rekening">{{ $rekening }}</div>
    </div>

    <div class="kartu">
        <h2 style="margin-top:0;font-size:1.05rem">Unggah Bukti Transfer</h2>
        @if (session('bukti_sukses'))
            <p class="sukses">Bukti transfer diterima. Bendahara akan memverifikasi donasi Anda. Barakallahu fiikum.</p>
        @elseif ($donasi->bukti_path)
            <p class="sukses">Bukti transfer sudah terunggah dan menunggu verifikasi bendahara.</p>
        @endif

        @if ($donasi->status === 'menunggu_transfer')
            @if ($errors->any())<div class="galat">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('tenant.donasi.bukti', ['ref' => $donasi->client_ref]) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="bukti" accept=".pdf,.jpg,.jpeg,.png" required>
                <p style="font-size:.8rem;color:#78716c">Format: PDF / JPG / PNG, maks 5 MB.</p>
                <button type="submit">{{ $donasi->bukti_path ? 'Ganti Bukti' : 'Kirim Bukti' }}</button>
            </form>
        @endif
    </div>
</main>
</body>
</html>
