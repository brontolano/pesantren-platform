<?php

namespace Modules\Kesehatan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Santri;

/**
 * WP-27 — Kunjungan santri ke UKS (kesehatan).
 * Satu santri per tanggal (partial unique: tenant_id, santri_id, tanggal).
 * Status: pulang (langsung pulang), istirahat (tidur di UKS), rujuk (ke rumah sakit/klinik).
 *
 * Terintegrasi dengan PresensiSantri:
 * - Saat status='istirahat' created/updated → PresensiSantri otomatis set status='sakit' untuk tanggal itu.
 * - Saat istirahat ditutup (status change dari istirahat) → PresensiSantri di-reset ke normal.
 */
class KunjunganUks extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'kunjungan_uks';

    public const STATUS = ['pulang', 'istirahat', 'rujuk'];

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'tanggal',
        'jam_masuk',
        'jam_keluar',
        'keluhan',
        'tindakan',
        'obat',
        'status',
        'rujukan_ke',
        'rujukan_biaya',
        'pencatat_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jam_masuk' => 'datetime:H:i:s',
        'jam_keluar' => 'datetime:H:i:s',
        'rujukan_biaya' => 'decimal:2',
    ];

    // ── Relasi ──────────────────────────────────────────────────────────────

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }
}
