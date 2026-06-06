<?php

namespace Modules\PPDB\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalonSantriDokumen extends Model
{
    use BelongsToTenant;

    // Status verifikasi dokumen (F2/DOC-3, antrean verifikasi ERP).
    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu Verifikasi',
        self::STATUS_VERIFIED => 'Terverifikasi',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    protected $table = 'calon_santri_dokumen';

    protected $fillable = [
        'tenant_id',
        'calon_santri_id',
        'jenis_dokumen',
        'file_url',
        'metadata',
        'status',
        'verified_at',
        'verified_by',
        'catatan_verifikasi',
    ];

    protected $casts = [
        'metadata' => 'array',
        'verified_at' => 'datetime',
    ];

    public function calonSantri(): BelongsTo
    {
        return $this->belongsTo(CalonSantri::class, 'calon_santri_id');
    }

    /** Status efektif — dokumen tanpa status dianggap menunggu verifikasi. */
    public function effectiveStatus(): string
    {
        return $this->status ?: self::STATUS_PENDING;
    }

    public function isPending(): bool
    {
        return $this->effectiveStatus() === self::STATUS_PENDING;
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->effectiveStatus()] ?? self::STATUS_LABELS[self::STATUS_PENDING];
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }
}
