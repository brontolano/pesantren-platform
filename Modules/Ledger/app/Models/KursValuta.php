<?php

namespace Modules\Ledger\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;

/**
 * G3-23 — Kurs valuta asing (donor) → IDR. 1 unit mata_uang = Rp kurs.
 */
class KursValuta extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'kurs_valuta';

    protected $fillable = [
        'tenant_id', 'mata_uang', 'kurs', 'berlaku_tanggal', 'is_active',
    ];

    protected $casts = [
        'kurs' => 'decimal:4',
        'berlaku_tanggal' => 'date',
        'is_active' => 'boolean',
    ];
}
