# Plan Pengembangan — Integrasi Website ↔ Modul Platform

> Status dokumen: **PERENCANAAN (visi ke depan)** · Dibuat 2026-06-05 · Pemilik: Hamdan
> Progres dipantau di panel central → **Manajemen Proyek** → proyek "Integrasi Website / API".
> Catatan: belum dibangun. Ada client yang situsnya akan dikelola Hamdan → jadi bahan **uji coba** pertama.

---

## 1. Masalah & Visi

Website pesantren bisa datang dalam banyak bentuk:
- **Dikelola Hamdan** (sebagai jasa) — WordPress atau custom code.
- **Dikelola pesantren sendiri** (IT internal) — WordPress atau custom code.

**Visi:** apa pun teknologinya dan siapa pun yang mengelola, website itu **harus bisa terintegrasi** dengan modul platform (PPDB, portal wali/santri, data, berita) **tanpa membongkar isolasi antar tenant**.

## 2. Prinsip inti

**Pisahkan "website" (wajah) dari "modul" (mesin/data).** Website hanya presentasi; platform adalah otak (data + logika). Integrasi = bagaimana wajah berbicara ke otak.

> Siapa yang mengelola (Hamdan vs pesantren) dan teknologi apa (WordPress vs custom) **tidak mengubah pola integrasi** — hanya mengubah siapa yang mengerjakan sisi website. Cukup 3 pola.

```
        WEBSITE (wajah)                      PLATFORM (mesin/modul)
   WordPress / custom code      ── 3 cara ──▶  PPDB · Portal · Data · CMS
   (dikelola Hamdan / pesantren)              (per-tenant, TERISOLASI)
```

## 3. Tiga pola integrasi

| Pola | Cara kerja | Cocok untuk | Effort |
|---|---|---|---|
| **A. Subdomain delegation** ⭐ | Situs utama tetap WP/custom di `www.`; bagian dinamis di subdomain → platform (mis. `ppdb.` & `portal.pesantrenX.sch.id`). | Cepat, aman, nyaris tanpa koding. | Sangat rendah (DNS) |
| **B. Embed (iframe/widget)** | Form PPDB / widget statistik/berita ditempel ke halaman WP via `<iframe>`/`<script>`. | Satu domain, tampilan menyatu. | Rendah |
| **C. API (REST + Sanctum)** | Website punya halaman sendiri, baca-tulis ke platform via API + token per tenant. | Kontrol penuh / plugin WordPress. | Tinggi |

### Peta kebutuhan → pola
| Kebutuhan | Pola terbaik |
|---|---|
| Pendaftaran PPDB | A atau B (atau C submit) |
| Login wali/santri | **A** — jangan bangun ulang auth di WordPress |
| Berita / profil / statistik | C (headless) atau B (widget) |
| Notifikasi | webhook n8n + WAHA (sudah ada) |

## 4. Kondisi sistem saat ini

| Sudah ada ✅ | Perlu dibangun ⚠️ |
|---|---|
| Custom domain + on-demand TLS → **pola A bisa sekarang** | API publik read (profil, statistik, berita, jadwal PPDB) |
| Website bawaan (modul CMS): landing, artikel, sitemap, PPDB, portal wali | Token/API key **per tenant** + halaman "Integrasi & API" di dashboard tenant |
| Sanctum terpasang + API `v1` skeleton (PPDB) | Halaman PPDB **iframe-friendly** + widget `<script>` |
| Model `Domain` + `InitializeTenancyByDomain` | **CORS per domain tenant** + rate-limit |
| Webhook n8n + WAHA | Dokumentasi integrasi + (opsional) plugin WordPress |

## 5. Roadmap berfase (sinkron papan Manajemen Proyek)

| Fase | Fokus |
|---|---|
| **A** | Subdomain delegation — SOP + registrasi/verifikasi domain tenant (hasil tercepat) |
| **B** | Embed — halaman PPDB iframe-friendly + widget script |
| **C** | API publik — read data, submit PPDB, token per tenant, CORS + rate-limit |
| **D** | Distribusi & uji coba — dokumentasi, plugin WordPress, **uji coba dengan client pertama** |

## 6. Rekomendasi eksekusi

- **Mulai dari pola A (subdomain delegation)** untuk uji coba client pertama — paling cepat memberi hasil, paling sedikit risiko, dan infrastrukturnya (custom domain + auto-SSL) **sudah siap**.
- Lanjut pola C (API) saat butuh tampilan menyatu / data berita & statistik di situs mereka.
- Pola B (embed) sebagai jalan tengah bila mereka ingin tetap satu domain.

## 7. Keamanan & isolasi (WAJIB)

- API **tenant-scoped**: token pesantren A tidak boleh mengakses data pesantren B.
- CORS whitelist **per domain tenant**, bukan wildcard.
- Rate-limit + audit log pada endpoint publik.
- Verifikasi kepemilikan domain sebelum delegasi subdomain.
- Konsisten dengan aturan: `pesantren.online` = SaaS; data operasional tetap terisolasi di tenant.

## 8. Catatan uji coba

Client pertama (situs dikelola Hamdan) = kandidat **uji coba pola A**. Target uji coba: arahkan `ppdb.<domain-client>` & `portal.<domain-client>` ke platform, verifikasi auto-SSL + isolasi data, lalu evaluasi sebelum menstandarkan SOP untuk tenant lain.
