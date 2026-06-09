<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Ledger\Models\Akun;

/**
 * Kategori transaksi POS — configurable per tenant (Configuration over Customization).
 * Membawa akun lawan Kas (akun_id) + dimensi sumber dana untuk posting Ledger.
 */
class PosKategori extends Model
{
    use BelongsToTenant;

    protected $table = 'pos_kategori';

    protected $fillable = [
        'tenant_id', 'kode', 'nama', 'jenis', 'handler',
        'akun_id', 'sumber_dana_id', 'aktif', 'sort',
    ];

    protected $casts = [
        'akun_id' => 'integer',
        'sumber_dana_id' => 'integer',
        'aktif' => 'boolean',
        'sort' => 'integer',
    ];

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    /** Akun lawan Kas (COA Ledger) untuk posting — opsional (boleh belum dikonfigurasi). */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }
}
