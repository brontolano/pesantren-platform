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
| `eskalasi_kategori` | Baris `ai_escalation` BARU lahir (PolicyEngine / RagAnswerer) | `kategori` saja (mis. `blacklist_topik`, `level_fatwa`, `tanpa_rujukan`, `sitasi_gagal`) — **bukan** isi pertanyaan |
| `rating_jawaban` | API `ResearchTelemetry::rekamRating` (UI menyusul di T-A090) | `agent`, `rating` (int 1–5) |

Kolom lain: `tenant_id` (FK cascade), `periode` (tanggal 1 bulan berjalan —
sumbu dashboard bulanan), `created_at`. Index `(jenis, periode)` + `tenant_id`.

## Menjalankan test modul (worktree wt-ai)

```bash
php artisan test -c phpunit.ai.xml --filter=Ai
```

`phpunit.ai.xml` memakai DB test terisolasi `pesantren_platform_test_ai`
(jangan di-commit — khusus worktree ini).
