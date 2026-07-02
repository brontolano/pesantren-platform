<?php

namespace Modules\Keuangan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Santri;

class Tagihan extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    protected $table = 'tagihan';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'divisi_unit_id',
        'sumber_dana_id',
        'jenis_tagihan',
        'keterangan',
        'nominal',
        'komponen',
        'diskon',
        'diskon_beasiswa',
        'nominal_akhir',
        'jatuh_tempo',
        'status',
        'tabungan_disetor_at',
        'is_dibayar_dimuka',
        'periode_akademik',
        'bulan_masehi',
        'bulan_hijriah',
        'created_by',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'komponen' => 'array',
        'tabungan_disetor_at' => 'datetime',
        'diskon' => 'decimal:2',
        'diskon_beasiswa' => 'decimal:2',
        'nominal_akhir' => 'decimal:2',
        'jatuh_tempo' => 'date',
        'is_dibayar_dimuka' => 'boolean',
        'notif_wa_sent_at' => 'datetime',
    ];

    // --- Relationships ---

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Computed ---

    public function getTotalDibayarAttribute(): float
    {
        return (float) $this->pembayaran()->where('is_verified', true)->sum('nominal');
    }

    public function getSisaTagihanAttribute(): float
    {
        return (float) $this->nominal_akhir - $this->total_dibayar;
    }

    // --- Scopes ---

    public function scopeBelumLunas($query)
    {
        return $query->whereIn('status', ['belum_bayar', 'sebagian']);
    }

    public function scopeLunas($query)
    {
        return $query->where('status', 'lunas');
    }

    public function scopeJatuhTempo($query)
    {
        return $query->where('jatuh_tempo', '<=', now())->where('status', '!=', 'lunas');
    }
}
