<?php

namespace Modules\Mutu\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Approvable;
use Modules\Core\Concerns\Auditable;

/**
 * G3-17 — Temuan ketidaksesuaian (Non-Conformance) + tindakan korektif.
 * State-machine: terbuka → tindakan → verifikasi → tutup.
 * Penutupan melewati gerbang persetujuan F1 (Approvable, no-op tanpa aturan).
 */
class MutuTemuan extends Model
{
    use Approvable, Auditable, BelongsToTenant;

    protected $table = 'mutu_temuan';

    public const STATUS = ['terbuka', 'tindakan', 'verifikasi', 'tutup'];

    public const SEVERITY = [
        'minor' => 'Minor',
        'major' => 'Major',
        'kritis' => 'Kritis',
    ];

    /** severity → level numerik (untuk ambang ApprovalRule). */
    public const SEVERITY_LEVEL = ['minor' => 1, 'major' => 2, 'kritis' => 3];

    protected $fillable = [
        'tenant_id', 'inspeksi_id', 'sasaran_id', 'judul', 'deskripsi',
        'severity', 'severity_level', 'status', 'tindakan_korektif', 'pic_id',
        'tenggat', 'diverifikasi_oleh', 'diverifikasi_pada', 'created_by',
    ];

    protected $casts = [
        'severity_level' => 'integer',
        'tenggat' => 'date',
        'diverifikasi_pada' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $t): void {
            // Severity TAK BOLEH diubah setelah temuan keluar dari status 'terbuka'.
            // Mencegah bypass gerbang F1 (downgrade kritis→minor lalu tutup tanpa
            // persetujuan). Berlaku di SEMUA jalur tulis (Filament/service/import).
            if ($t->exists && $t->isDirty('severity') && $t->getOriginal('status') !== 'terbuka') {
                throw new \InvalidArgumentException(
                    'Severity temuan tidak dapat diubah setelah tindakan dimulai.'
                );
            }
            // severity_level SELALU turunan dari severity (ambang ApprovalRule).
            $t->severity_level = self::SEVERITY_LEVEL[$t->severity] ?? 1;
        });
    }

    public function inspeksi(): BelongsTo
    {
        return $this->belongsTo(MutuInspeksi::class, 'inspeksi_id');
    }

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(MutuSasaran::class, 'sasaran_id');
    }

    public function scopeTerbuka($query)
    {
        return $query->whereIn('status', ['terbuka', 'tindakan', 'verifikasi']);
    }
}
