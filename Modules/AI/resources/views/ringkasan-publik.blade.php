{{-- T-A012 — Ringkasan Kebijakan asisten AI untuk kyai/pimpinan (tautan
     bertanda tangan, BACA-SAJA). Tanpa login halaman ini hanya MEMBACA;
     persetujuan tetap lewat panel (login + permission ai.setting.setujui).

     Unduh PDF: keputusan sadar T-A012 — tidak ada package PDF terpasang
     (dompdf/snappy nihil di composer) dan TIDAK menambah dependency baru;
     tombol "Cetak / Simpan PDF" memakai window.print() + CSS print-friendly
     (browser modern menyediakan "Save as PDF" bawaan). --}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Ringkasan Kebijakan Asisten AI — {{ $namaPesantren }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 1.5rem 1rem 3rem;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f8fafc; color: #1f2937; line-height: 1.55;
        }
        .wrap { max-width: 56rem; margin: 0 auto; }
        .kartu {
            background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem;
            padding: 1.25rem 1.5rem; margin-bottom: 1rem;
        }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; color: #14532d; }
        .meta { font-size: .875rem; color: #6b7280; margin: 0; }
        .grid { display: grid; gap: 1rem; }
        @media (min-width: 768px) { .grid { grid-template-columns: 1fr 1fr; } }
        .panel { border-radius: .75rem; padding: 1rem 1.25rem; border: 1px solid; }
        .panel-akan { background: #f0fdf4; border-color: #bbf7d0; }
        .panel-tidak { background: #fef2f2; border-color: #fecaca; }
        .panel p { margin: 0 0 .5rem; font-weight: 600; font-size: .9rem; }
        .panel-akan p { color: #166534; }
        .panel-tidak p { color: #991b1b; }
        .panel ul { margin: 0; padding-inline-start: 1.25rem; font-size: .875rem; }
        .panel-akan ul { color: #14532d; }
        .panel-tidak ul { color: #7f1d1d; }
        .panel li { margin-bottom: .25rem; }
        .status {
            display: inline-block; border-radius: 999px; padding: .2rem .75rem;
            font-size: .8rem; font-weight: 600;
        }
        .status-menunggu { background: #fef9c3; color: #854d0e; }
        .status-disetujui { background: #dcfce7; color: #166534; }
        .status-ditolak { background: #fee2e2; color: #991b1b; }
        .peringatan {
            background: #fffbeb; border: 1px solid #fde68a; color: #92400e;
            border-radius: .75rem; padding: .75rem 1rem; font-size: .875rem;
            margin-bottom: 1rem;
        }
        .aksi { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.25rem; }
        .tombol {
            display: inline-block; border-radius: .5rem; padding: .55rem 1.1rem;
            font-size: .875rem; font-weight: 600; text-decoration: none;
            border: 1px solid transparent; cursor: pointer;
        }
        .tombol-utama { background: #166534; color: #fff; }
        .tombol-polos { background: #fff; color: #374151; border-color: #d1d5db; }
        .catatan { font-size: .8rem; color: #6b7280; margin-top: 1rem; }
        @media print {
            body { background: #fff; padding: 0; }
            .kartu { border: none; padding: 0; }
            .aksi, .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="kartu">
        <h1>Ringkasan Kebijakan Asisten AI</h1>
        <p class="meta">
            {{ $namaPesantren }} — pengajuan kebijakan versi {{ $approval->setting?->versi }},
            diajukan {{ $approval->created_at?->translatedFormat('d F Y H:i') }}.
        </p>
        <p class="meta" style="margin-top:.5rem">
            Status:
            @if ($approval->status === \Modules\AI\Models\AiApproval::STATUS_MENUNGGU)
                <span class="status status-menunggu">Menunggu keputusan</span>
            @elseif ($approval->status === \Modules\AI\Models\AiApproval::STATUS_DISETUJUI)
                <span class="status status-disetujui">Telah disetujui {{ $approval->decided_at?->translatedFormat('d F Y H:i') }}</span>
            @else
                <span class="status status-ditolak">Ditolak {{ $approval->decided_at?->translatedFormat('d F Y H:i') }}</span>
            @endif
        </p>
    </div>

    @unless ($hashCocok)
        <div class="peringatan no-print">
            Mohon dicatat: isi pengaturan telah berubah sejak pengajuan ini dibuat,
            sehingga ringkasan di bawah mungkin tidak lagi mencerminkan versi terkini.
            Mohon meminta pengelola mengajukan ulang sebelum memberi keputusan.
        </div>
    @endunless

    <div class="kartu">
        <div class="grid">
            <div class="panel panel-akan">
                <p>Asisten AI di pesantren ini AKAN:</p>
                <ul>
                    @foreach ($ringkasan['akan'] as $kalimat)
                        <li>{{ $kalimat }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="panel panel-tidak">
                <p>Dan TIDAK AKAN:</p>
                <ul>
                    @foreach ($ringkasan['tidak_akan'] as $kalimat)
                        <li>{{ $kalimat }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="aksi">
            <a class="tombol tombol-utama" href="{{ $urlPanel }}">
                Masuk ke panel untuk memberi keputusan
            </a>
            <button type="button" class="tombol tombol-polos" onclick="window.print()">
                Cetak / Simpan PDF
            </button>
        </div>

        <p class="catatan no-print">
            Tautan ini hanya menampilkan ringkasan dan akan kedaluwarsa dengan sendirinya.
            Demi keamanan, persetujuan atau penolakan hanya dapat dilakukan setelah masuk
            ke panel dengan akun yang berwenang.
        </p>
    </div>
</div>
</body>
</html>
