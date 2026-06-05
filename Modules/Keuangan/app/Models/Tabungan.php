<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Kesantrian\Models\Santri;

class Tabungan extends Model
{
    use BelongsToTenant;

    protected $table = 'tabungan';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'saldo',
        'limit_harian',
        'is_frozen',
    ];

    protected $casts = [
        'saldo' => 'decimal:2',
        'limit_harian' => 'decimal:2',
        'is_frozen' => 'boolean',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(TabunganTransaksi::class);
    }

    public function topupRequests(): HasMany
    {
        return $this->hasMany(TabunganTopup::class);
    }
}
