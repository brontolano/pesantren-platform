<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>403 — Akses Ditolak</title>
    <style>
        :root { color-scheme: light; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Plus Jakarta Sans", sans-serif;
            margin: 0; background: #f8faf9; color: #374151;
        }
        .wrap { max-width: 34rem; margin: 16vh auto; padding: 0 1.5rem; text-align: center; }
        .code { font-size: 4rem; font-weight: 800; margin: 0; color: #14532d; line-height: 1; }
        .title { font-size: 1.35rem; font-weight: 700; margin: .5rem 0 .25rem; color: #166534; }
        .desc { font-size: 1.02rem; line-height: 1.6; margin: 0 auto 1.75rem; max-width: 28rem; }
        .actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-block; padding: .6rem 1.25rem; border-radius: .6rem;
            font-weight: 600; text-decoration: none; font-size: .95rem; cursor: pointer; border: 0;
        }
        .btn-primary { background: #16a34a; color: #fff; }
        .btn-ghost { background: #fff; color: #166534; border: 1px solid #d1d5db; }
        .note { margin-top: 1.5rem; font-size: .85rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="wrap">
        <p class="code">403</p>
        <p class="title">Halaman ini di luar kewenangan Anda</p>
        <p class="desc">
            Akun Anda tidak memiliki izin untuk membuka halaman tersebut.
            Ini wajar — setiap peran (bendahara, guru, tata usaha, dll.) hanya dapat
            mengakses bagian yang sesuai tugasnya. Silakan kembali ke halaman sebelumnya
            atau ke Dasbor.
        </p>
        <div class="actions">
            <a href="javascript:history.back()" class="btn btn-ghost">&larr; Kembali</a>
            <a href="/admin" class="btn btn-primary">Ke Dasbor</a>
        </div>
        <p class="note">Bila Anda merasa seharusnya punya akses, hubungi admin pesantren untuk penyesuaian peran.</p>
    </div>
</body>
</html>
