<?php

namespace Modules\Keuangan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sesi/shift kasir POS — akuntabilitas tunai.
 * saldo_sistem = saldo_awal + Σ pemasukan − Σ pengeluaran.
 * selisih (saat tutup) = saldo_fisik − saldo_sistem.
 */
class PosSesi extends Model
{
    use BelongsToTenant;

    protected $table = 'pos_sesi';

    protected $fillable = [
        'tenant_id', 'kasir_id', 'pos_jenis', 'profil_id',
        'saldo_awal', 'saldo_sistem', 'saldo_fisik', 'selisih',
        'status', 'dibuka_pada', 'ditutup_pada', 'catatan',
    ];

    protected $casts = [
        'saldo_awal' => 'decimal:2',
        'saldo_sistem' => 'decimal:2',
        'saldo_fisik' => 'decimal:2',
        'selisih' => 'decimal:2',
        'dibuka_pada' => 'datetime',
        'ditutup_pada' => 'datetime',
    ];

    public function transaksi(): HasMany
    {
        return $this->hasMany(PosTransaksi::class, 'sesi_id');
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function isBuka(): bool
    {
        return $this->status === 'buka';
    }
}
