<?php

namespace Modules\Pembiayaan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Aset extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'aset';

    protected $fillable = [
        'tenant_id', 'nama_aset', 'jenis', 'nilai', 'sumber_dana_id', 'is_wakaf', 'nomor_sertifikat',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
        'is_wakaf' => 'boolean',
    ];

    public function scopeNonWakaf($query)
    {
        return $query->where('is_wakaf', false);
    }
}
