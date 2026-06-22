# Modules/ — Indeks Peta Modul

> Tiap modul punya `AGENTS.md` sendiri: peta orientasi faktual (model, service, UI Filament,
> endpoint, jebakan, status rollout). **Untuk tugas di satu modul, baca `AGENTS.md`-nya dulu**
> lalu muat folder modul itu + `.d.ts` terkait + `resources/js/Components/Ui/README.md`.
> Acuan global: `docs/KONVENSI.md` · `docs/PLAYBOOK-IRISAN.md` · `../../BLUEPRINT-ARSITEKTUR-5-LAPISAN.md` · `../../ROLLOUT-5-LAPISAN-BACKLOG.md`.

| Modul | Domain singkat | Filament / Model | Rollout | Uang? |
|---|---|---|---|---|
| [AI](AI/AGENTS.md) | Khadim Digital: Gateway LLM, FAQ-mining, Eval, Audit | 10 / 26 | terpisah (Fase AI) | — |
| [Akademik](Akademik/AGENTS.md) | Nilai, Rapor, Kurikulum, Jadwal, Ujian | 18 / 23 | **G4** | — |
| [Asrama](Asrama/AGENTS.md) | Blok, Kamar, Penempatan, Biaya | 4 / 4 | **G3** | — |
| [CMS](CMS/AGENTS.md) | Artikel, Galeri, Landing (awas `/_tenant-storage`) | 6 / 5 | **G3** | — |
| [Core](Core/AGENTS.md) | Approval, Audit, Dokumen, Surat — fondasi platform | 5 / 5 | **TETAP terpusat** | — |
| [Kegiatan](Kegiatan/AGENTS.md) | Izin, Kunjungan, Gerbang, Penjengukan, Presensi | 7 / 9 | **G3** | — |
| [Kesantrian](Kesantrian/AGENTS.md) | Santri, Kelas, Alumni, Pelanggaran, Presensi | 7 / 14 | Santri Resource terpusat | — |
| [Kesehatan](Kesehatan/AGENTS.md) | Riwayat, UKS, Obat, Konseling, Mutasi Obat | 3 / 5 | **G3** | — |
| [Keuangan](Keuangan/AGENTS.md) | Tagihan, Pembayaran, Tabungan, POS, Inventory | 22 / 28 | **G6** | **UANG** |
| [Ledger](Ledger/AGENTS.md) | Double-entry, Kehalalan, Jurnal, Anggaran, Kurs | 18 / 10 | **G7** | **UANG** |
| [Mutu](Mutu/AGENTS.md) | Inspeksi, Sasaran, SKM, Dokumen, Lampu, Temuan | 23 / 7 | **G3** | — |
| [Notifikasi](Notifikasi/AGENTS.md) | WA, Log, Template (n8n→WAHA) | 3 / 3 | terpisah (integrasi) | — |
| [PPDB](PPDB/AGENTS.md) | Calon Santri, CRM, Ujian, Soal, OTP, TagihanPpdb | 10 / 13 | **G5** | uang ringan |
| [Pajak](Pajak/AGENTS.md) | Tarif, PPh21, BOP | 5 / 4 | **G8** | **UANG** |
| [Payroll](Payroll/AGENTS.md) | Employee, Slip Gaji, Cuti, Khidmah, THR | 13 / 14 | **G8** | **UANG** |
| [Pembiayaan](Pembiayaan/AGENTS.md) | Aset, Piket, Penyusutan | 6 / 7 | akhir | uang ringan |
| [Pengadaan](Pengadaan/AGENTS.md) | PO, Supplier, Belanja | 3 / 5 | akhir | uang ringan |
| [Tahfidz](Tahfidz/AGENTS.md) | Hafalan, Halaqah, Setoran, Sima'an, Munaqasyah | 5 / 7 | **G3** | — |

**Aturan modul uang (Keuangan/Ledger/Payroll/Pajak/Pembiayaan/Pengadaan/PPDB-tagihan):** semua operasi uang lewat event auto-jurnal → Ledger; posting ref TAK BOLEH null; WAJIB `TenantPanelAuthedSmokeTest` + adversarial 3-lensa sebelum deploy.

**Core Resources yang TETAP terpusat (jangan relokasi ke modul):** Santri, Users, Roles, Tenants, Modules, Billing, Approval, Media, Audit.
