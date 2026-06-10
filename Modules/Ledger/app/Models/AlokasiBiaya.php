<?php

namespace Modules\Ledger\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

/**
 * G3-23 — Header alokasi biaya (cost allocation) ke divisi via driver berbobot.
 * Distribusi diposting ke Buku Besar lewat JurnalService (Σ share = nominal).
 */
class AlokasiBiaya extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'alokasi_biaya';

    protected $fillable = [
        'tenant_id', 'akun_id', 'nominal', 'driver', 'periode', 'keterangan',
        'status', 'jurnal_id', 'dibuat_oleh',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'driver' => 'array',
    ];

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }
}
