<?php

namespace Modules\Tahfidz\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Santri;

/**
 * G3-18 — Target hafalan (KPI) per santri per periode. Realisasi dihitung dari
 * HafalanTahfidz berstatus selesai → capaian %.
 */
class TahfidzTarget extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'tahfidz_target';

    protected $fillable = [
        'tenant_id', 'santri_id', 'periode', 'target_surah', 'keterangan',
    ];

    protected $casts = [
        'target_surah' => 'integer',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
