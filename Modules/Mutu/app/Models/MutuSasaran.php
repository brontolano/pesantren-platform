<?php

namespace Modules\Mutu\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;

/**
 * G3-17 — Sasaran/KPI mutu (acuan KMA 939): target vs realisasi per area.
 */
class MutuSasaran extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'mutu_sasaran';

    public const AREA = [
        'akademik' => 'Akademik',
        'tahfidz' => 'Tahfidz',
        'layanan' => 'Layanan',
        'umum' => 'Umum',
    ];

    protected $fillable = [
        'tenant_id', 'kode', 'nama', 'area', 'satuan', 'target', 'realisasi',
        'periode', 'regulasi_acuan', 'is_active',
    ];

    protected $casts = [
        'target' => 'decimal:2',
        'realisasi' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    /** Persentase capaian (realisasi / target × 100), dibatasi wajar. */
    public function capaian(): float
    {
        $target = (float) $this->target;
        if ($target <= 0) {
            return 0.0;
        }

        return round((float) $this->realisasi / $target * 100, 2);
    }
}
