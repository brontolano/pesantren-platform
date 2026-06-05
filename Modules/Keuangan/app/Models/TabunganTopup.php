<?php

namespace Modules\Keuangan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabunganTopup extends Model
{
    use BelongsToTenant;

    protected $table = 'tabungan_topup';

    protected $fillable = [
        'tenant_id',
        'tabungan_id',
        'nominal',
        'tanggal_transfer',
        'nama_pengirim',
        'bukti',
        'status',
        'alasan_penolakan',
        'verified_by',
        'verified_at',
        'created_by',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'tanggal_transfer' => 'date',
        'verified_at' => 'datetime',
    ];

    public function tabungan(): BelongsTo
    {
        return $this->belongsTo(Tabungan::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
