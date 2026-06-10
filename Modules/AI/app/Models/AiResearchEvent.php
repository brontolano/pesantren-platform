<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * T-A013 — event telemetri riset produk ber-OPT-IN. Tabel CENTRAL.
 *
 * SKEMA EVENT (kontrak payload per jenis — JANGAN tambah field berisi teks
 * bebas/PII; lihat juga Modules/AI/README.md §Telemetri Riset):
 *
 *  jenis = 'snapshot_konfigurasi' — direkam saat versi setting AKTIF tenant
 *  berganti (hook lifecycle TenantSettingService):
 *      {
 *        "preset_manhaj":      string  (aswaja_nu|muhammadiyah|salafi|kustom),
 *        "level_fatwa":        string  (edukatif_only|informatif|khilafiyah),
 *        "fitur_aktif":        string[] kunci "peran.fitur" yang bernilai true
 *                                       (KUNCI saja, tanpa nilai lain),
 *        "mode_akses_santri":  string  (tanpa_akses|lab_terjadwal|perangkat_terbatas),
 *        "jumlah_scope_aktif": int     banyaknya scope_data yang true
 *      }
 *      SENGAJA TANPA blacklist_topik/whitelist_kitab kustom — daftar topik
 *      buatan tenant bisa mengidentifikasi pesantren tertentu.
 *
 *  jenis = 'eskalasi_kategori' — direkam saat baris ai_escalation BARU lahir
 *  (PolicyEngine/RagAnswerer/EscalationService::buatManual T-A090); HANYA
 *  kategori sistem, BUKAN isi pertanyaan:
 *      { "kategori": string }  (mis. blacklist_topik, level_fatwa,
 *                               tanpa_rujukan, sitasi_gagal, manual)
 *
 *  jenis = 'rating_jawaban' — API ResearchTelemetry::rekamRating
 *  (UI: <x-ai::rating-jawaban> via route ai.rating-jawaban, T-A090):
 *      { "agent": string, "rating": int 1..5 }
 *
 * PAGAR PRIVASI (PRD §6.4 + ADR-6):
 *  - Penulisan HANYA lewat ResearchTelemetry; seluruh jalur no-op senyap
 *    bila optin_riset setting aktif tenant = false.
 *  - TIDAK PERNAH memuat isi percakapan, identitas santri/wali, atau
 *    dokumen tenant. periode = bulan (tanggal 1) — granularitas kasar.
 *  - Dapat DIHAPUS seluruhnya per tenant saat izin dicabut (cabutIzin) —
 *    karena itu TANPA trigger append-only.
 */
class AiResearchEvent extends Model
{
    public const JENIS_SNAPSHOT_KONFIGURASI = 'snapshot_konfigurasi';

    public const JENIS_ESKALASI_KATEGORI = 'eskalasi_kategori';

    public const JENIS_RATING_JAWABAN = 'rating_jawaban';

    protected $table = 'ai_research_event';

    /** Hanya created_at (di-set eksplisit), tanpa updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'jenis',
        'payload',
        'periode',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'periode' => 'date',
        'created_at' => 'datetime',
    ];

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
