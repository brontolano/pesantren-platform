<?php

namespace Modules\Kegiatan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Kesantrian\Models\Santri;

/** WP-30 — pengajuan slot jenguk oleh wali via PWA. */
class PengajuanJenguk extends Model
{
    use BelongsToTenant;

    protected $table = 'pengajuan_jenguk';

    public const STATUS = ['diajukan', 'dikonfirmasi', 'ditolak', 'hadir', 'batal'];

    protected $fillable = [
        'tenant_id', 'santri_id', 'pengaju_id', 'tanggal',
        'status', 'client_ref', 'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengaju_id');
    }

    public function kunjungan(): HasOne
    {
        return $this->hasOne(KunjunganWali::class, 'pengajuan_id');
    }
}
