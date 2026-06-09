<?php

namespace Modules\Keuangan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Ledger\Models\JurnalBaris;

/**
 * F6 — Satu baris mutasi rekening koran. arah=masuk (uang masuk ke bank,
 * cocok dengan jurnal_baris.debit pada akun kas/bank) / keluar (kredit).
 */
class BankStatementLine extends Model
{
    use BelongsToTenant;

    protected $table = 'bank_statement_line';

    protected $fillable = [
        'tenant_id', 'bank_statement_id', 'tanggal', 'keterangan', 'referensi',
        'nominal', 'arah', 'status', 'jurnal_baris_id', 'dicocokkan_oleh', 'dicocokkan_pada',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
        'dicocokkan_pada' => 'datetime',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function jurnalBaris(): BelongsTo
    {
        return $this->belongsTo(JurnalBaris::class, 'jurnal_baris_id');
    }

    public function pencocok(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicocokkan_oleh');
    }

    public function cocok(): bool
    {
        return $this->status === 'cocok';
    }
}
