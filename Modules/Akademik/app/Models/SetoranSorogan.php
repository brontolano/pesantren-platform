<?php

namespace Modules\Akademik\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Akademik\Database\Factories\SetoranSoroganFactory;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Santri;

/**
 * Issue #330 — Setoran baca kitab per santri (sorogan): penilaian
 * kelancaran, ketepatan makna (ngabsahi), kedudukan nahwu/i'rab (0–100).
 */
class SetoranSorogan extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    public const METODE = ['sorogan' => 'Sorogan', 'bandongan' => 'Bandongan', 'halaqah' => 'Halaqah'];

    public const STATUS = ['lanjut' => 'Lanjut', 'ulang' => 'Ulang', 'khatam' => 'Khatam'];

    protected $table = 'sorogan_setoran';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'kitab_id',
        'bab',
        'halaman_dari',
        'halaman_sampai',
        'tanggal',
        'ustadz_id',
        'metode',
        'nilai_kelancaran',
        'nilai_makna',
        'nilai_nahwu',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'halaman_dari' => 'integer',
        'halaman_sampai' => 'integer',
        'nilai_kelancaran' => 'decimal:2',
        'nilai_makna' => 'decimal:2',
        'nilai_nahwu' => 'decimal:2',
    ];

    protected static function newFactory(): SetoranSoroganFactory
    {
        return SetoranSoroganFactory::new();
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function kitab(): BelongsTo
    {
        return $this->belongsTo(Kitab::class, 'kitab_id');
    }

    public function ustadz(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ustadz_id');
    }

    /** Rata-rata dari nilai yang terisi (null bila belum dinilai sama sekali). */
    public function getNilaiRataAttribute(): ?float
    {
        $nilai = array_filter([
            $this->nilai_kelancaran,
            $this->nilai_makna,
            $this->nilai_nahwu,
        ], fn ($v): bool => $v !== null);

        return $nilai === []
            ? null
            : round(array_sum(array_map('floatval', $nilai)) / count($nilai), 2);
    }
}
