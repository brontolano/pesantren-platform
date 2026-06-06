<?php

namespace Modules\PPDB\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesi ujian online peserta. Menyimpan jawaban + hasil skoring dari
 * UjianScoringService. Essay yang tertanam di `butuh_manual` menanti nilai
 * penguji sebelum status final.
 */
class SesiUjian extends Model
{
    use BelongsToTenant;

    public const STATUS_BERLANGSUNG = 'berlangsung';

    public const STATUS_SELESAI = 'selesai';   // auto-grade tersimpan

    public const STATUS_DINILAI = 'dinilai';   // essay manual sudah dinilai

    protected $table = 'sesi_ujian';

    protected $fillable = [
        'tenant_id',
        'calon_santri_id',
        'kategori_ujian',
        'status',
        'jawaban',
        'auto_score',
        'max_auto_score',
        'butuh_manual',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'jawaban' => 'array',
        'butuh_manual' => 'array',
        'auto_score' => 'integer',
        'max_auto_score' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function calonSantri(): BelongsTo
    {
        return $this->belongsTo(CalonSantri::class, 'calon_santri_id');
    }

    /**
     * Isi hasil skoring (output UjianScoringService::score) ke sesi.
     * Status → 'selesai' (auto-grade tersimpan; essay—bila ada—masih manual).
     *
     * @param array{auto_score:int,max_auto_score:int,butuh_manual:array} $hasil
     */
    public function isiDariSkor(array $hasil): self
    {
        $this->auto_score = (int) ($hasil['auto_score'] ?? 0);
        $this->max_auto_score = (int) ($hasil['max_auto_score'] ?? 0);
        $this->butuh_manual = $hasil['butuh_manual'] ?? [];
        $this->status = self::STATUS_SELESAI;

        return $this;
    }

    /** Persentase auto-grade (PG); null bila tak ada soal PG ber-bobot. */
    public function persenAuto(): ?float
    {
        $max = (int) $this->max_auto_score;
        if ($max <= 0) {
            return null;
        }

        return round((int) $this->auto_score / $max * 100, 1);
    }

    /** Masih ada soal essay yang menunggu penilaian manual? */
    public function butuhPenilaianManual(): bool
    {
        return ! empty($this->butuh_manual);
    }

    public function isBerlangsung(): bool
    {
        return $this->status === self::STATUS_BERLANGSUNG;
    }

    public function isSelesai(): bool
    {
        return in_array($this->status, [self::STATUS_SELESAI, self::STATUS_DINILAI], true);
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeSelesai($query)
    {
        return $query->whereIn('status', [self::STATUS_SELESAI, self::STATUS_DINILAI]);
    }
}
