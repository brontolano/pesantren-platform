<?php

namespace Modules\Pembiayaan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WP-33 — Area piket dengan kode QR unik per tenant.
 * Kode di-scan via keyboard-wedge (pola gerbang) di PWA Guru.
 */
class AreaPiket extends Model
{
    use BelongsToTenant;

    protected $table = 'area_piket';

    protected $fillable = [
        'tenant_id',
        'nama',
        'kode_qr',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function ceklis(): HasMany
    {
        return $this->hasMany(CeklisPiket::class, 'area_id');
    }
}
