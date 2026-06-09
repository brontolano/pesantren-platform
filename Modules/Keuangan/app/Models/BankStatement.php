<?php

namespace Modules\Keuangan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Ledger\Models\Akun;

/**
 * F6 — Rekening koran (statement) yang diimpor untuk direkonsiliasi
 * terhadap jurnal kas/bank.
 */
class BankStatement extends Model
{
    use BelongsToTenant;

    protected $table = 'bank_statement';

    protected $fillable = [
        'tenant_id', 'akun_id', 'nama', 'periode_awal', 'periode_akhir',
        'saldo_awal', 'saldo_akhir', 'sumber', 'status', 'diimpor_oleh',
    ];

    protected $casts = [
        'periode_awal' => 'date',
        'periode_akhir' => 'date',
        'saldo_awal' => 'decimal:2',
        'saldo_akhir' => 'decimal:2',
    ];

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'bank_statement_id');
    }

    public function pengimpor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diimpor_oleh');
    }

    public function terkunci(): bool
    {
        return $this->status === 'terkunci';
    }
}
