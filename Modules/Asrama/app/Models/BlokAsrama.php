<?php

namespace Modules\Asrama\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlokAsrama extends Model
{
    use BelongsToTenant;

    protected $table = 'blok_asrama';

    protected $fillable = [
        'tenant_id',
        'nama_blok',
        'lokasi',
        'jumlah_kamar',
        'is_active',
    ];

    protected $casts = [
        'jumlah_kamar' => 'integer',
        'is_active' => 'boolean',
    ];

    public function kamar(): HasMany
    {
        return $this->hasMany(KamarAsrama::class, 'blok_id');
    }
}
