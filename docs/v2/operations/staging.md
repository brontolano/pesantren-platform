# Staging dan batas rilis

V2 belum punya staging publik dari workflow repository. Sebelum staging disetujui, siapkan host/subdomain terpisah, database pusat dan database tenant v2, bucket/storage privat v2, kredensial runtime terpisah, mail sink, serta session cookie khusus. MFA operator harus diwajibkan pada host yang dapat diakses internet. Validasi ulang role/policy, cookie Secure/HttpOnly/SameSite, TLS, proteksi CSRF, rate limit login/undangan, pencadangan, dan prosedur restore.

Kapasitas belum diketahui dari angka plan saja. VPS yang dipakai layanan lama menunjukkan 2 vCPU/8 GiB RAM, tetapi tak ada metrik headroom maupun beban sintetis untuk menilai apakah v2 aman ditambahkan. Ukur CPU/memori/p95 dan I/O pada staging terisolasi; jangan menambah runtime v2 ke server produksi atas asumsi kapasitas.

Rilis membutuhkan persetujuan terpisah. Jangan merge branch ke `main`, mengaktifkan workflow lama, mengubah DNS, atau memigrasikan data. Push ke branch v2 hanya menjalankan CI lint/test.

## Checklist pra-staging

- Semua tes izin, retry, invite, login tenant dan isolation lulus pada PostgreSQL disposable.
- MFA operator aktif, akun bootstrap aman, tidak ada endpoint daftar publik operator.
- Restore database pusat, tenant, dan berkas diuji dari backup baru.
- Probe teknis tidak membaca isi data tenant.
- Review keamanan dilakukan pada domain canonicalization, dynamic connection, cookie/session, idempotency, race condition, token, audit, dan upload.
- Uji keyboard, fokus, screen reader dasar, kontras, error form, dan viewport kecil selesai.
- LCP/INP/CLS dan p95 dicatat sebagai hasil lab beserta versi browser, server, ukuran fixture dan konfigurasi.
- Pemilik produk menyetujui host, akun operator, RPO/RTO, retensi, dan waktu staging.
