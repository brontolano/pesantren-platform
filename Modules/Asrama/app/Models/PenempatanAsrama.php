<?php

namespace Modules\Asrama\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class PenempatanAsrama extends Model
{
    use BelongsToTenant;

    protected $table = 'penempatan_asrama';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'kamar_id',
        'tanggal_masuk',
        'tanggal_keluar',
        'status',
        'keterangan',
        'is_current',
    ];

    protected $casts = [
        'tanggal_masuk' => 'date',
        'tanggal_keluar' => 'date',
        'is_current' => 'boolean',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(KamarAsrama::class, 'kamar_id');
    }
}
