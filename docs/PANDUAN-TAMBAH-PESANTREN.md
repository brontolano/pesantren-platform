# Panduan Cepat: Menambah & Menyetel Pesantren Baru

> **Untuk owner/admin platform.** Versi ringkas & terkini. Untuk detail onboarding lengkap (paket langganan, training), lihat [`PANDUAN-ONBOARDING-TENANT.md`](PANDUAN-ONBOARDING-TENANT.md). Untuk DNS, lihat [`DNS-ONBOARDING.md`](DNS-ONBOARDING.md).

## Inti yang wajib dipahami
Menambah pesantren = **membuat 1 tenant** (1 database fisik + 1 domain + setelan). **TIDAK menyentuh kode, TIDAK repo baru, TIDAK deploy ulang.** Semua pesantren memakai kode master yang sama; yang membedakan hanyalah **data + modul yang diaktifkan**.

```
1 pesantren baru  =  1 tenant  =  [ database sendiri ] + [ domain ] + [ pilihan modul ] + [ branding ]
```

---

## Langkah 1 — Buat tenant

**Cara A (artisan — paling cepat & andal).** Di server, jalankan:

```bash
cd /opt/pesantren-platform
docker compose -f docker-compose.production.yml exec -T app \
  php artisan tenant:create "Pondok Pesantren Al-Hikmah" al-hikmah
```

- Argumen 1 = **nama lengkap** (boleh spasi, pakai tanda kutip).
- Argumen 2 = **subdomain/ID** (huruf kecil, angka, tanda hubung — tanpa spasi). Ini jadi nama DB `tenantal-hikmah` & fallback domain `al-hikmah.pesantren.online`.
- Opsi domain sendiri: `--website-domain=al-hikmah.sch.id --system-domain=sistem.al-hikmah.sch.id`.

**Yang otomatis terjadi** (oleh `TenantProvisioner`): database fisik dibuat → migrasi dijalankan → modul **inti** + bagan akun (COA) + peran + **akun admin** di-seed. **Kredensial admin ditampilkan di layar — catat.**

**Cara B (lewat panel).** Login panel platform (super admin) → menu **Pesantren → Instalasi Tenant** → isi formulir. Cocok bila tak mau buka terminal.

---

## Langkah 2 — Arahkan domain (DNS)

Agar pesantren bisa diakses lewat domainnya:
1. Di DNS domain, buat record **A** → IP VPS **`187.77.116.167`** (atau **CNAME** ke subdomain platform bila pakai `*.pesantren.online`).
2. Tunggu propagasi (15–30 menit).
3. **SSL/HTTPS otomatis** (Caddy on-demand) — tak perlu pasang sertifikat manual, asalkan domain sudah terdaftar di tenant.

> Detail & verifikasi: [`DNS-ONBOARDING.md`](DNS-ONBOARDING.md), `php artisan domain:verify`.

---

## Langkah 3 — Aktifkan modul yang dibutuhkan

Tenant baru lahir **minimal** (hanya modul inti: santri, keuangan dasar, notifikasi, website). Aktifkan sisanya sesuai kebutuhan pesantren:

- **Cara A:** login panel pesantren itu → **Pusat Modul** → nyalakan modul (Akademik, Asrama, Tahfidz, PPDB, Payroll, dll).
- **Cara B:** panel platform → **Aktivasi Modul** (atur dari sisi super admin).

> Prinsip: nyalakan **hanya yang dipakai** → menu tetap ramping. Modul bisa dinyalakan/dimatikan kapan saja (data tak hilang saat dimatikan). Modul uang (Ledger/Payroll) sebaiknya diaktifkan sadar oleh owner.

---

## Langkah 4 — Branding & setelan dasar

Di panel pesantren → **Pengaturan**: unggah logo, atur warna/tema, nama & alamat pesantren, tahun ajaran, jadwal sholat (GPS), dll.

---

## Langkah 5 — Verifikasi sehat

```bash
docker compose -f docker-compose.production.yml exec -T app \
  php artisan tenant:render-check al-hikmah
```

Lalu buka domainnya + login panel dengan kredensial admin dari Langkah 1. Pastikan tak ada error 500.

---

## Langkah 6 — (Opsional) Impor data awal

Santri, wali, asrama, saldo tabungan dll bisa diimpor via fitur **Import** di tiap modul (Excel). Lihat [`PANDUAN-IMPORT-SANTRI.md`](PANDUAN-IMPORT-SANTRI.md) & [`PANDUAN-IMPORT-EXPORT-CETAK.md`](PANDUAN-IMPORT-EXPORT-CETAK.md).

---

## ✅ Checklist tambah pesantren
- [ ] `tenant:create` (atau Instalasi Tenant) → catat kredensial admin
- [ ] DNS A/CNAME → 187.77.116.167, tunggu propagasi, HTTPS otomatis
- [ ] Pusat Modul → nyalakan modul yang dipakai
- [ ] Branding (logo, warna, identitas)
- [ ] `tenant:render-check` + login uji (tak ada 500)
- [ ] (opsional) Import santri/wali

## ❌ Yang TIDAK perlu (penegasan)
- ❌ Repo GitHub baru — **tidak perlu** (semua tenant 1 repo).
- ❌ Deploy/build ulang — **tidak perlu** (kode tak berubah saat tambah tenant).
- ❌ Sentuh kode — **tidak perlu** (cukup data + setelan).

## Perintah tenant berguna (referensi)
| Perintah | Fungsi |
|---|---|
| `tenant:create "Nama" sub` | Buat tenant baru |
| `tenant:render-check <id>` | Cek kesehatan render panel tenant |
| `tenant:reprovision <id>` | Pulihkan modul/seed dasar bila ada yang hilang |
| `tenant:reset <id>` | **HATI-HATI** — reset data tenant (super admin platform) |
| `domain:verify` | Verifikasi domain tenant |

> ⚠️ Perintah lintas-semua-tenant (mis. `tenants:migrate`) hanya saat ada migrasi DB baru, dijalankan owner di maintenance window. Tambah tenant biasa **tidak** memerlukannya.
