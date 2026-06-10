<?php

namespace Modules\Ledger\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Approvable;
use Modules\Core\Concerns\Auditable;

class Jurnal extends Model
{
    use Approvable, Auditable, BelongsToTenant;

    protected $table = 'jurnal';

    protected $fillable = [
        'tenant_id',
        'nomor',
        'tanggal',
        'deskripsi',
        'tipe_sumber',
        'ref_type',
        'ref_id',
        'idempotency_key',
        'periode_id',
        'status',
        'reversal_of_id',
        'reversed_by_id',
        'dibuat_oleh',
        // G3-23 — valuta asing (record-only; posting tetap IDR).
        'mata_uang',
        'kurs',
        'nilai_asing',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'kurs' => 'decimal:4',
        'nilai_asing' => 'decimal:2',
    ];

    public function baris(): HasMany
    {
        return $this->hasMany(JurnalBaris::class);
    }

    /**
     * G1-01: total debit jurnal — dipakai ApprovalRule (field 'total_debit')
     * untuk memicu persetujuan jurnal manual di atas ambang nominal.
     */
    public function getTotalDebitAttribute(): float
    {
        return (float) $this->baris()->sum('debit');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkuntansi::class, 'periode_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }
}
