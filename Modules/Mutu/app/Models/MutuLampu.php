<?php

namespace Modules\Mutu\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

/**
 * 2a Dasbor Mutu — baris cache lampu kuning (hasil hitung job harian).
 * Unique (tenant_id, santri_id, jenis) → satu lampu per jenis per santri.
 */
class MutuLampu extends Model
{
    use BelongsToTenant;

    protected $table = 'mutu_lampu';

    public const JENIS = ['hafalan_macet', 'alpa', 'poin_disiplin', 'nilai_turun'];

    protected $fillable = [
        'tenant_id', 'santri_id', 'jenis', 'label', 'detail', 'computed_at', 'last_notified_at',
    ];

    protected $casts = [
        'detail' => 'array',
        'computed_at' => 'datetime',
        'last_notified_at' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
