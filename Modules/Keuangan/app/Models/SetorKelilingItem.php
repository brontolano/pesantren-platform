<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class SetorKelilingItem extends Model
{
    use BelongsToTenant;

    protected $table = 'setor_keliling_item';

    protected $fillable = [
        'tenant_id', 'sesi_id', 'santri_id', 'nominal', 'catatan', 'tabungan_transaksi_id',
    ];

    protected $casts = ['nominal' => 'decimal:2'];

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(SetorKelilingSesi::class, 'sesi_id');
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
