<?php

namespace Modules\Keuangan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Approvable;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Santri;

class Pembayaran extends Model
{
    use Approvable, Auditable, BelongsToTenant;

    protected $table = 'pembayaran';

    protected $fillable = [
        'tenant_id',
        'tagihan_id',
        'santri_id',
        'nominal',
        'metode',
        'tanggal_pembayaran',
        'nomor_bukti',
        'bukti_pembayaran',
        'catatan',
        'alasan_penolakan',
        'is_verified',
        'verified_by',
        'verified_at',
        'created_by',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'tanggal_pembayaran' => 'date',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
