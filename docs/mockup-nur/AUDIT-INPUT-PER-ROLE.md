# Audit Menyeluruh — Kemampuan Input (Fase Penginputan) per Role

> 2026-07-06. Metode: **ground-truth route produksi** (`artisan route:list` di server) +
> halaman Create Filament + peta permission (RolesAndPermissionsSeeder). Bukan tebakan.

## Ringkasan eksekutif
**SEMUA role yang bertugas mencatat data TERBUKTI punya endpoint input.** Total **144 route tulis
(POST/PUT/PATCH)** di produksi, tersebar di 18 app; **PLUS** seluruh CRUD Filament (89 halaman
Create) yang submit lewat 1 endpoint Livewire (jadi tak terhitung sebagai route per-resource).
Tidak ada role operasional yang "buntu". Yang read-only memang by design (pengawas & publik).

## Bukti write-route per app (produksi)
| App (prefix) | Write routes | Role pemakai |
|---|---|---|
| guru | 17 | asatidz, guru, guru_tahfidz |
| wali | 16 | wali |
| spmb | 13 | peserta |
| keuangan | 10 | keuangan/bendahara, kepala_keuangan, bendahara_pusat, operator_divisi, kasir |
| app (santri) | 7 | santri |
| jemaah | 7 | jemaah (komentar/registrasi) |
| baitul-maal | 6 | keuangan (donasi/dana kebajikan) |
| ppdb-panitia | 4 | panitia_ppdb |
| uks | 4 | pengelola_uks |
| keamanan | 3 | (view_kegiatan) keamanan |
| pengasuhan | 3 | pengelola_asrama |
| pimpinan | 2 | kepala, dewan_pembina (approval) |
| sarpras | 2 | pengelola_sarpras |
| akuntan | 1 | akuntan (+ Filament jurnal) |
| konselor | 1 | konselor |
| pos-kantin | 1 | kasir |
| tu | 1 | tu (+ Filament) |
| admin (Filament/Livewire) | (semua CRUD) | admin_pesantren, super_admin, IT, tu, petugas_payroll |

## Verifikasi khusus (yang tampak "0 form" di scan awal)
- **petugas_payroll** → app Kepegawaian minim form Vue, TAPI input via **Filament**: 8 halaman Create
  (Employee, SlipGaji, Cuti, Kontrak, Jabatan, Pelamar, Penugasan, Pinjaman) + 13 resource ber-gate
  `run_payroll`. ✅ Bukan gap.
- **IT / admin_pesantren** → input via Filament (manage_modules/users/roles/settings). ✅
- **tu** → 1 route Vue + Filament (manage_santri/asrama/kegiatan/ppdb — Create pages ada). ✅

## Read-only BY DESIGN (benar, tak perlu input)
- **dewan_pembina** — pengawas keuangan/pimpinan; hanya 2 write (approval). Sesuai fungsi.
- **kepala** — pemantau; input terbatas ke Mutu + Pengaturan + approval. Operasional = view-only sengaja.
- **jemaah** — publik; hanya komentar & registrasi mandiri.
- **santri** — mayoritas view + kerjakan ujian/hafalan (7 write route /app). Cukup untuk perannya.

## Kesimpulan
✅ Tidak ada role operasional tanpa jalur input. Fase penginputan tersedia untuk semua yang membutuhkannya.

## Implikasi desain "Nur" (dua permukaan form)
Untuk menyamakan SEMUA form ke mockup Nur, garap **dua jalur**:
1. **Form Vue** (guru/wali/keuangan/uks/spmb/dll) → komponen Vue di `resources/js/Components/Ui`.
2. **Form Filament** (admin/tu/payroll/IT) → tema komponen form Filament.
Token warna/font sudah Nur (F1 live); yang tersisa = struktur/komponen form (F2/F4).
