<?php

namespace Modules\Ledger\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JurnalBaris extends Model
{
    use BelongsToTenant;

    protected $table = 'jurnal_baris';

    protected $fillable = [
        'tenant_id',
        'jurnal_id',
        'akun_id',
        'divisi_id',
        'sumber_dana_id',
        'debit',
        'kredit',
        'memo',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
    ];

    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class);
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(DivisiUnit::class, 'divisi_id');
    }

    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class, 'sumber_dana_id');
    }
}
