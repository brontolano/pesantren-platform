<?php

namespace Modules\Asrama\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KamarAsrama extends Model
{
    use BelongsToTenant;

    protected $table = 'kamar_asrama';

    protected $fillable = [
        'tenant_id',
        'blok_id',
        'nomor_kamar',
        'kapasitas',
        'tipe',
        'biaya_per_bulan',
        'is_active',
    ];

    protected $casts = [
        'kapasitas' => 'integer',
        'biaya_per_bulan' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function blok(): BelongsTo
    {
        return $this->belongsTo(BlokAsrama::class, 'blok_id');
    }

    public function penempatan(): HasMany
    {
        return $this->hasMany(PenempatanAsrama::class, 'kamar_id');
    }

    public function penempatanAktif(): HasMany
    {
        return $this->penempatan()->where('is_current', true);
    }
}
