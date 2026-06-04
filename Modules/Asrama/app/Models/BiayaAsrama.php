<?php

namespace Modules\Asrama\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;

class BiayaAsrama extends Model
{
    use BelongsToTenant;

    protected $table = 'biaya_asrama';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'kamar_id',
        'periode',
        'nominal',
        'status_pembayaran',
        'keuangan_tagihan_id',
        'jatuh_tempo',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'jatuh_tempo' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(KamarAsrama::class, 'kamar_id');
    }

    public function tagihanKeuangan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class, 'keuangan_tagihan_id');
    }
}
