<?php

namespace Modules\Tahfidz\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

/**
 * Riwayat keanggotaan halaqah — JANGAN dihapus; keluar = soft-close (isi left_at).
 */
class HalaqahSantri extends Model
{
    use BelongsToTenant;

    protected $table = 'halaqah_santri';

    protected $fillable = [
        'tenant_id',
        'halaqah_id',
        'santri_id',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'joined_at' => 'date',
        'left_at' => 'date',
    ];

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(Halaqah::class, 'halaqah_id');
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    /** Soft-close keanggotaan — riwayat tetap tersimpan. */
    public function tutup(?\DateTimeInterface $tanggal = null): void
    {
        $this->update(['left_at' => $tanggal ?? now()]);
    }
}
