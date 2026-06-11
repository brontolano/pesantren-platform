<?php

namespace Modules\Pembiayaan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WP-33 — Hasil ceklis piket per scan QR area.
 * Unik per (tenant, area, petugas, tanggal) — scan ulang meng-update.
 */
class CeklisPiket extends Model
{
    use BelongsToTenant;

    protected $table = 'ceklis_piket';

    public const STATUS = ['bersih', 'kotor', 'rusak'];

    protected $fillable = [
        'tenant_id',
        'area_id',
        'petugas_id',
        'tanggal',
        'jam',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jam' => 'datetime:H:i:s',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaPiket::class, 'area_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
}
