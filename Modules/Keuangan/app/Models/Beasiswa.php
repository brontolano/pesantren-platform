<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Santri;
use Modules\Ledger\Models\SumberDana;

class Beasiswa extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    protected $table = 'beasiswa';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'sumber_dana_id',
        'nominal_per_bulan',
        'aktif',
        'mulai',
        'selesai',
        'keterangan',
    ];

    protected $casts = [
        'nominal_per_bulan' => 'decimal:2',
        'aktif' => 'boolean',
        'mulai' => 'date',
        'selesai' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class, 'sumber_dana_id');
    }
}
