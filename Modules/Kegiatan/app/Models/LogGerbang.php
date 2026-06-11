<?php

namespace Modules\Kegiatan\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

/** WP-29 — log keluar/masuk santri di pos gerbang. */
class LogGerbang extends Model
{
    use BelongsToTenant;

    protected $table = 'log_gerbang';

    protected $fillable = [
        'tenant_id', 'santri_id', 'arah', 'waktu', 'petugas_id',
        'izin_id', 'is_tanpa_izin', 'catatan',
    ];

    protected $casts = [
        'waktu' => 'datetime',
        'is_tanpa_izin' => 'boolean',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function izin(): BelongsTo
    {
        return $this->belongsTo(IzinSantri::class, 'izin_id');
    }
}
