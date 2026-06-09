<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

/**
 * G3-18 — Skor santri per kriteria rubrik (0..100).
 */
class RubrikSkor extends Model
{
    use BelongsToTenant;

    protected $table = 'rubrik_skor';

    protected $fillable = [
        'tenant_id', 'kriteria_id', 'santri_id', 'periode_akademik_id', 'skor',
    ];

    protected $casts = [
        'skor' => 'decimal:2',
    ];

    public function kriteria(): BelongsTo
    {
        return $this->belongsTo(RubrikKriteria::class, 'kriteria_id');
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
