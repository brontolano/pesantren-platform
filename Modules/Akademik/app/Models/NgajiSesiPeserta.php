<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

/** Issue #330 — Kehadiran + nilai ringkas per santri per sesi ngaji massal. */
class NgajiSesiPeserta extends Model
{
    use BelongsToTenant;

    protected $table = 'ngaji_sesi_peserta';

    protected $fillable = [
        'tenant_id',
        'ngaji_sesi_id',
        'santri_id',
        'hadir',
        'nilai',
        'catatan',
    ];

    protected $casts = [
        'hadir' => 'boolean',
        'nilai' => 'decimal:2',
    ];

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(NgajiSesi::class, 'ngaji_sesi_id');
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
