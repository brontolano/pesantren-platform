# Modul AI — "Khadim Digital"

Asisten AI ber-pagar syariah untuk pesantren (PRD Modul AI v1.1, `_PLAN/05-AI/`).
Satu-satunya pintu ke LLM adalah `AiGateway` (Aturan Emas #4); seluruh kebijakan
perilaku per pesantren hidup di **data** (`ai_tenant_setting` versioned + approval
kyai), bukan di kode (Aturan Emas #11).

Peta singkat:

| Bagian | Lokasi |
|---|---|
| Gateway LLM + circuit breaker + router | `app/Services/Gateway/` |
| Guardrails (PolicyEngine, sitasi, disclaimer) | `app/Services/Guardrails/` |
| Privasi (redaksi PII, sanitasi anti-injection) | `app/Services/Privacy/` |
| Pengaturan tenant + lifecycle approval kyai | `app/Services/Settings/` |
| RAG (ingest, retrieval hybrid, sitasi deterministik) | `app/Services/Rag/` |
| Eskalasi "Tanya Ustadz" + SLA | `app/Services/Escalation/` |
| Metering kredit + anggaran + kill-switch | `app/Services/Metering/` |
| Telemetri riset opt-in (T-A013) | `app/Services/Research/` |
| Komponen UI AI standar (T-A090) | `resources/views/components/` |
| Migrasi tabel TENANT | `database/migrations/` |
| Migrasi tabel CENTRAL (tidak ikut `tenants:migrate`) | `database/migrations/central/` |

## Telemetri Riset Opt-in (T-A013) — skema event

Tabel **central** `ai_research_event` mengisi dashboard internal **"Suara Pasar"**
(panel platform `/platform`, super_admin only). Sumber kebenaran skema:
dokblok model `app/Models/AiResearchEvent.php`.

**Pagar privasi (PRD §6.4 + ADR-6 — privasi sejak desain):**

- Event **hanya lahir bila `optin_riset = true`** pada setting AKTIF tenant
  (Wizard; dapat dicabut kapan saja). Tanpa opt-in = **nol baris**, no-op senyap.
- Payload **tidak pernah** memuat isi percakapan, teks pertanyaan, PII, identitas
  santri/wali, atau dokumen tenant — hanya kategori/kunci/agregat.
- **Cabut izin** (`optin_riset` kembali `false`) menghapus **seluruh** event tenant
  tersebut (`ResearchTelemetry::cabutIzin`) — karena itu tabel ini sengaja
  **tanpa** trigger append-only.
- Seluruh penulisan lewat `ResearchTelemetry` dan **best-effort**: kegagalan
  telemetri tidak pernah menggagalkan alur bisnis pemanggil.

| `jenis` | Kapan direkam | Isi `payload` |
|---|---|---|
| `snapshot_konfigurasi` | Versi setting AKTIF berganti (hook lifecycle `TenantSettingService`) | `preset_manhaj`, `level_fatwa`, `fitur_aktif` (daftar kunci `peran.fitur` yang true), `mode_akses_santri`, `jumlah_scope_aktif` — **tanpa** blacklist/whitelist kustom (bisa identifiable) |
| `eskalasi_kategori` | Baris `ai_escalation` BARU lahir (PolicyEngine / RagAnswerer / tombol Tanya Ustadz T-A090) | `kategori` saja (mis. `blacklist_topik`, `level_fatwa`, `tanpa_rujukan`, `sitasi_gagal`, `manual`) — **bukan** isi pertanyaan |
| `rating_jawaban` | API `ResearchTelemetry::rekamRating` (UI: `<x-ai::rating-jawaban>`, T-A090) | `agent`, `rating` (int 1–5) |

Kolom lain: `tenant_id` (FK cascade), `periode` (tanggal 1 bulan berjalan —
sumbu dashboard bulanan), `created_at`. Index `(jenis, periode)` + `tenant_id`.

## Komponen UI (T-A090)

Komponen Blade anonim ber-namespace `ai` (`resources/views/components/`,
didaftarkan eksplisit di `AIServiceProvider`). Prinsip PRD §3: bahasa manusia
santun, satu klik lebih sedikit, progressive disclosure; aksesibilitas dasar
(kontras AA, target sentuh ≥ 44 px, ARIA). **Semua fitur Fase A (EP-A4/A5)
WAJIB memakai komponen ini** — jangan menggambar badge/sitasi/draf sendiri.

Catatan styling: kelas struktur memakai Tailwind yang sudah lazim di blade
modul ini; **warna kritis kontras memakai token inline eksplisit** (Forest
Green `#14532d`, amber `#78350f`/`#fef3c7`, slate `#1e293b`/`#f1f5f9`) supaya
AA terjamin di Filament maupun Portal tanpa bergantung hasil compile Tailwind
dari `Modules/*` (path ini tidak di-scan `@source` theme).

### `<x-ai::badge-ai>` — penanda jawaban AI

```blade
<x-ai::badge-ai />              {{-- "Jawaban AI — bukan fatwa/keputusan" --}}
<x-ai::badge-ai varian="data" />{{-- "Jawaban AI — dihasilkan sistem" --}}
<x-ai::badge-ai varian="draf" />{{-- "DRAF — perlu pengesahan" (amber) --}}
```

### `<x-ai::blok-sitasi>` — daftar sumber jawaban

Menerima `AiGenerateResult->sitasi` (pakai `label` deterministik dari
RagAnswerer) atau array `{judul, bab, halaman}`. Kosong → tidak merender.

```blade
<x-ai::blok-sitasi :sitasi="$hasil->sitasi" />
```

### `<x-ai::editor-draf>` — draf + tombol "Sahkan" (human-in-the-loop)

Badge DRAF & keterangan "AI hanya menyusun draf; Anda yang memutuskan"
otomatis. `aksi` = nama handler Livewire (`wire:click`); atau bawa tombol
sendiri lewat slot `tombol`.

```blade
<x-ai::editor-draf judul="Draf surat keterangan" aksi="sahkanDraf">
    <textarea wire:model="isiDraf" rows="6" class="w-full ..."></textarea>
</x-ai::editor-draf>
```

### `<x-ai::indikator-kredit>` — pemakaian kredit AI

`anggaran` null/0 → teks "tanpa batas anggaran" tanpa bar. Bar amber ≥ 80 %,
merah ≥ 100 % (selaras ambang alert & kill-switch metering).

```blade
<x-ai::indikator-kredit :terpakai="$budget->terpakai_periode"
                        :anggaran="$budget->anggaran_kredit_bulanan" />
```

### `<x-ai::tombol-tanya-ustadz>` — eskalasi manual satu klik

Form POST ke `ai.tanya-ustadz.eskalasi` (auth + tenant) →
`EscalationService::buatManual`: pertanyaan diredaksi PII **paksa**,
idempoten per (user, hash pertanyaan ternormalisasi) — klik ganda tidak
menggandakan antrean; kategori `manual` ikut telemetri T-A013 (opt-in saja).

```blade
<x-ai::tombol-tanya-ustadz :pertanyaan="$pertanyaanPengguna" />
```

### `<x-ai::panel-asisten>` — kerangka panel form-first

Slot: default = form fitur; `lanjutan` = "Pengaturan lanjutan" collapsed
default (Alpine — sudah dibawa Filament); `status` = area async ber-
`aria-live` (bungkus `wire:poll` di sisi pemakai bila perlu); `hasil` =
badge + `blok-sitasi` otomatis (prop `varian-badge`, `:sitasi`); `kredit` =
footer indikator kredit.

```blade
<x-ai::panel-asisten judul="Generator Soal"
                     deskripsi="Isi formulir, asisten menyusun draf soal."
                     varian-badge="draf" :sitasi="$hasil?->sitasi ?? []">
    <form wire:submit="susun">…</form>

    <x-slot:lanjutan>…opsi jarang dipakai…</x-slot:lanjutan>
    <x-slot:status>@if($sedangMenyusun) Sedang disusun, mohon menunggu… @endif</x-slot:status>
    @if($hasil) <x-slot:hasil>{{ $hasil->text }}</x-slot:hasil> @endif
    <x-slot:kredit><x-ai::indikator-kredit :terpakai="…" :anggaran="…" /></x-slot:kredit>
</x-ai::panel-asisten>
```

### `<x-ai::rating-jawaban>` — bintang 1–5 kepuasan jawaban

Form POST ke `ai.rating-jawaban` (auth + tenant) →
`ResearchTelemetry::rekamRating` — no-op senyap bila tenant tidak opt-in
riset; respons UI selalu sama (status opt-in tidak bocor).

```blade
<x-ai::rating-jawaban agent="chatbot_wali" />
```

## Gate Eval Rilis (T-A024, Blueprint K7 + §10)

Gate eval **bukan CI** (test.yml selalu merah karena DB-per-tenant): jalankan
**sebelum rilis** terhadap model produksi sebenarnya, di VPS:

```bash
php artisan ai:eval --suite=syariah    # ambang ≥ 98%
php artisan ai:eval --suite=faktual    # ambang ≥ 97%
php artisan ai:eval --suite=injection  # ambang 100% (memanggil Ollama nyata)
php artisan ai:eval --riwayat          # 10 run terakhir
```

Exit code ≠ 0 = gate gagal = **fitur terkait tidak dirilis** (bukan
dilonggarkan). Hasil tercatat di tabel central `ai_eval_run` lengkap dengan
**model + versi prompt aktif** — ganti model/prompt berarti wajib eval ulang
(bandingkan kolom antar run). Bank kasus hidup di `ai_eval_case`, diimpor
idempoten oleh `AiEvalCaseSeeder` dari berkas `database/seeders/data/`
(otomatis saat suite kosong; ulang manual: `php artisan db:seed
--class="Modules\AI\Database\Seeders\AiEvalCaseSeeder"`).

Catatan operasional:

- Tanpa `--tenant`, harness memakai **tenant sandbox khusus eval**
  (`ai.eval.tenant_id`, default `eval-khadim`; dibuat sekali lalu dipakai
  ulang) — di sanalah harness boleh menyetel konteks kebijakan (level fatwa +
  blacklist teknis via alur approval resmi) dan menanam data minimal kasus
  tool.
- `--tenant=demo` menjalankan eval pada tenant itu **apa adanya** (setting
  tidak diubah, data tidak ditanam; kasus ber-setup dilewati & tercatat).
  Diperlukan selama modul `ai` masih berstatus `development` di
  `module_registry` (hanya tampil untuk tenant developer — gerbang a0
  PolicyEngine menolak tenant eval) dan untuk kasus kurasi GATE-3 yang butuh
  korpus pengetahuan nyata. Konsekuensi praktis hari ini: suite syariah/
  faktual teknis ber-setup dijalankan penuh di **QC lokal** (worktree, sesuai
  metode gate platform); kasus kurasi GATE-3 (tanpa setup) kelak berjalan
  penuh di `--tenant=demo`; harness sudah mendaftarkan `tenant_modules` untuk
  tenant eval sehingga sandbox langsung terpakai begitu modul `released`.
- **Kasus syariah tidak pernah dikarang** (Aturan Emas #8): saat ini suite
  syariah berisi kasus PERILAKU teknis-linguistik (mirror `policy_cases.json`,
  sumber `teknis`). Begitu berkas kurasi GATE-3 disalin ke
  `database/seeders/data/kasus-eval-syariah.json`, seeder otomatis memuatnya
  dengan label sumber jujur (`kurasi_ustadz` bila `_peninjau` terisi, selain
  itu `prd_verbatim`); jenis harapan majemuk yang belum didukung runner
  dilewati dengan log, tidak ditafsir ulang.
- `--dry` menjalankan eval tanpa menulis `ai_eval_run` (uji coba lokal).

## Menjalankan test modul (worktree wt-ai)

```bash
php artisan test -c phpunit.ai.xml --filter=Ai
```

`phpunit.ai.xml` memakai DB test terisolasi `pesantren_platform_test_ai`
(jangan di-commit — khusus worktree ini).
