# Blueprint Bisnis — Platform Pesantren White-Label (SaaS)

> **Status:** Terkunci v1.0 · 2026-06-02 · Pemilik: Hamdan
> Dokumen ini mengatur sisi **komersial** (apa, untuk siapa, bagaimana menghasilkan uang). Sisi teknis ada di `BLUEPRINT-ARSITEKTUR.md`.

---

## 1. Model Bisnis

**SaaS B2B** untuk pesantren & lembaga pendidikan Islam. Pesantren berlangganan platform (bulanan/tahunan); Anda menyediakan sistem, hosting, dan pembaruan. Satu basis kode melayani banyak pesantren (multi-tenant), sehingga biaya tambah pelanggan sangat rendah — inilah daya ungkit ekonomi SaaS.

---

## 2. Produk & Proposisi Nilai

Platform terpadu yang **distandarisasi tapi fleksibel**: mengganti spreadsheet + buku manual + grup WA dengan satu sistem. Nilai utama untuk pesantren:

- **Untuk pengelola:** data terpusat, administrasi cepat, laporan otomatis.
- **Untuk keuangan:** tunggakan SPP turun lewat tagihan & broadcast digital.
- **Untuk wali:** transparansi penuh — tagihan, hafalan, kehadiran anak di genggaman.
- **Pembeda:** fleksibel mengikuti kebijakan tiap pesantren (mis. sistem Mu'adalah / kelas linear), berbahasa Indonesia, harga kerjasama yang manusiawi.

---

## 3. Target Pasar

Puluhan ribu pesantren di Indonesia, mayoritas belum terdigitalisasi. Segmentasi:

| Segmen | Ukuran | Karakter | Pendekatan |
|---|---|---|---|
| Kecil | < 200 santri | Anggaran tipis, gaptek | Paket murah, onboarding dibantu |
| Menengah | 200–500 santri | Butuh efisiensi, mulai melek digital | Sasaran utama awal |
| Besar | > 500 santri | Kompleks, butuh banyak modul | ARPU tinggi, butuh dukungan lebih |

**Beachhead (titik masuk):** pesantren di lingkaran relasi Asy-Syifaa — kepercayaan & referral lebih mudah daripada pasar dingin.

---

## 4. Mesin Harga (Fleksibel per Kerjasama)

Harga **tidak dipatok kaku** — ini pembeda & kebutuhan pasar pesantren. Mesinnya: **Template Paket + Override per kerjasama** (lihat teknis di `BLUEPRINT-ARSITEKTUR.md` §12).

| Tipe billing | Untuk | Catatan |
|---|---|---|
| Per santri / bulan | Mayoritas | Adil, skalabel |
| Flat per tier (S/M/L) | Yang suka harga pasti | Sederhana dijual |
| Custom (nego) | Kerjasama khusus | Override manual |
| Gratis (mitra) | Pesantren mitra/strategis | Barter testimoni/referral |

**Acuan angka (HIPOTESIS — wajib divalidasi ke 3–5 pesantren):** Rp 2.000–8.000/santri/bulan, atau Rp 300rb–1,5jt/bulan flat per lembaga. Jangan kunci harga sebelum validasi pasar.

---

## 5. Aliran Pendapatan

1. **Langganan inti** (paket dasar) — pendapatan berulang utama.
2. **Add-on modul** (mis. modul tahfidz lanjutan, marketplace) — menaikkan ARPU tanpa menambah pelanggan.
3. **(Opsional) jasa onboarding/migrasi data** — sekali bayar saat klien baru masuk.

**Penagihan:** mulai dari **invoice langganan + transfer manual** (cukup untuk 1–10 klien). Gateway otomatis (Midtrans/Xendit) baru relevan saat klien > 10.

---

## 6. Strategi Masuk Pasar (Go-to-Market)

1. **Bukti hidup dulu.** Jadikan Asy-Syifaa berjalan penuh & rapi. Sistem nyata = alat jual terkuat.
2. **Referral & komunitas.** Jaringan kiai/yayasan saling percaya; satu testimoni kuat membuka banyak pintu.
3. **Demo berbasis data nyata**, bukan slide. Tunjukkan dashboard Asy-Syifaa.
4. **Mitra awal gratis/diskon** untuk membangun studi kasus & testimoni.
5. Skala bertahap — jangan ambil 10 klien sebelum sanggup melayani 3.

---

## 7. Build vs Buy (Disiplin Modal & Waktu)

Bangun hanya pembeda inti (sistem pesantren). Sisanya pakai yang sudah jadi.

| Kebutuhan | Keputusan | Alat |
|---|---|---|
| Produk pesantren & dashboard billing | **Bangun** (via Filament) | Sendiri |
| Monitoring server (uptime/resource) | **Beli/pakai** | Uptime Kuma / UptimeRobot |
| Error tracking | **Beli/pakai** | Sentry (free tier) |
| Ticketing/support | **Beli/pakai** | Awal: WhatsApp + spreadsheet → Chatwoot |

Membangun sendiri monitoring/ticketing = jebakan over-engineering. Waktu solo dev terlalu mahal untuk roda yang sudah bundar.

---

## 8. KPI Bisnis

| Metrik | Definisi | Target awal |
|---|---|---|
| MRR | Pendapatan langganan bulanan berulang | Tumbuh tiap bulan pasca-T4 |
| Jumlah tenant aktif | Pesantren berlangganan | ≥ 1 pesantren lain dalam 12 bulan |
| ARPU | Pendapatan rata-rata per pesantren | Naik via add-on |
| Churn | Pesantren berhenti langganan | < 10%/tahun |
| Adopsi internal (Asy-Syifaa) | % data dikelola di platform | ≥ 95% dalam 1 semester |

---

## 9. Risiko Bisnis & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Adopsi rendah (admin/wali gaptek) | Klien berhenti, ROI gagal | UX sederhana B.Indonesia, onboarding dibantu, manual in-app |
| Over-fitting ke Asy-Syifaa | Produk tak bisa dijual | Disiplin Configuration over Customization (§14 teknis) |
| Persaingan (Sais Cloud, ePesantren, dll) | Sulit menembus pasar | Pembeda: fleksibilitas config + harga kerjasama + relasi |
| Ketergantungan WA gateway non-resmi | Pesan gagal/blokir | Pantau log, siapkan WA Business API resmi (P2) |
| Data santri (anak di bawah umur) | Risiko hukum (UU PDP) | RBAC ketat, isolasi tenant, kebijakan privasi sebelum rilis publik |
| Solo founder kelelahan | Proyek mandek | Bangun bertahap (T0→T4), jangan jual sebelum sanggup melayani |

---

## 10. Pembeda Kompetitif (Mengapa Pesantren Pilih Anda)

1. **Fleksibilitas konfigurasi** — mengikuti kebijakan unik tiap pesantren (Mu'adalah, kelas linear, periode Hijriah) tanpa ganti sistem.
2. **Modular** — bayar & nyalakan hanya yang dipakai.
3. **Harga kerjasama manusiawi** — bukan harga korporat kaku.
4. **Dibangun oleh orang yang paham pesantren** — Asy-Syifaa sebagai bukti, bukan vendor IT yang asing dengan dunia pesantren.
