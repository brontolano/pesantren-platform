# GATE-3 — Pintu rilis konten keagamaan AI (scaffold)

> Status: **DITAHAN** menunggu sign-off owner + isian Dewan Ustadz.
> Seeder ini scaffold saja — di repo saat ini **tidak menanam apa pun**.

Modul AI "Khadim Digital" berstatus `development` di `module_registry`
(hanya tampil di tenant demo). Membuka tanya-jawab keagamaan ke pesantren
nyata butuh **konten keagamaan yang ditinjau manusia berilmu** (Aturan Emas
#8) — bukan karangan mesin. GATE-3 adalah pintu itu.

## Konten apa yang HARUS Ustadz isi, dan di mana

Template mentah ada di luar repo: **`_PLAN/05-AI/seed-gate3/`**
(baca `PANDUAN-PENGISIAN-SEED-USTADZ.md` di sana lebih dulu):

| Berkas template | Isi | Mengisi field |
|---|---|---|
| `blacklist-topik-default.json` | Topik yang AI **dilarang** jawab (nikah/talak/waris, takfir, politik praktis, aqidah sensitif, kesehatan jiwa) + kata kunci sehari-hari | `blacklist_topik` |
| `preset-manhaj-aswaja-nu.json` | **Whitelist kitab** rujukan + nada jawaban + aturan khilafiyah | `whitelist_kitab` |
| `template-pengalihan.json` | Kalimat penolakan/pengalihan santun | template guardrails |
| `kasus-eval-syariah.json` | Bank kasus uji gate rilis (target >= 50) | suite eval `syariah` |

Tiap berkas wajib diisi `_peninjau`, `_tanggal_tinjauan`, dan
`_status_berkas: "disetujui"` sebagai bukti tinjauan.

## Cara menyalin setelah disetujui

Salin (dengan status `disetujui`) ke folder ini:

```
Modules/AI/database/seeders/data/
  ├─ gate3-preset-syariah.json   ← gabungan blacklist_topik + whitelist_kitab
  │                                 (bentuk: lihat gate3-preset-syariah.json.example)
  └─ kasus-eval-syariah.json     ← dibaca AiEvalCaseSeeder otomatis
```

- `AiGate3PresetSeeder` menanam **blacklist_topik** ke policy preset
  (`sumber='preset'`, dikonsumsi PolicyEngine). **whitelist_kitab** TIDAK
  ditanam paksa — diatur per pesantren lewat Wizard "Pengaturan AI" (approval
  kyai), sesuai keputusan owner 2026-06-11 (kitab dipilih per pesantren).
- `AiEvalCaseSeeder` otomatis memuat `kasus-eval-syariah.json` dengan label
  sumber jujur (`kurasi_ustadz` bila `_peninjau` terisi).

## Langkah promote (DITAHAN — butuh sign-off owner + ustadz)

1. Dewan Ustadz isi & setujui 4 berkas di `_PLAN/05-AI/seed-gate3/`.
2. Salin `gate3-preset-syariah.json` + `kasus-eval-syariah.json` ke folder ini.
3. Jalankan gate eval terhadap **model produksi** di VPS:
   `php artisan ai:eval --suite=syariah` (>= 98%),
   `--suite=faktual` (>= 97%), `--suite=injection` (100%).
   Laporkan hasil ke Dewan Ustadz untuk persetujuan akhir.
4. **Setelah lulus + sign-off owner**: ubah `module_registry` key `ai`
   dari `development` → `released` (di
   `database/seeders/ModuleRegistrySeeder.php`) lalu aktifkan ke tenant
   lewat Pusat Modul / Aktivasi Modul.

> Langkah 4 **tidak dilakukan** pada task scaffold ini. Registry `ai` tetap
> `development`; tidak ada tenant yang diaktifkan.
