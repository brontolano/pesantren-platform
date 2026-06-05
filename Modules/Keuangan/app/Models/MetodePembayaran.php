<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MetodePembayaran extends Model
{
    use BelongsToTenant;

    protected $table = 'metode_pembayaran';

    protected $fillable = [
        'tenant_id',
        'tipe',
        'nama',
        'nomor',
        'atas_nama',
        'instruksi',
        'gambar',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'is_active' => 'boolean',
    ];
}
