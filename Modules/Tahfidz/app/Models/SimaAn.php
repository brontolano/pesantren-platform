<?php

namespace Modules\Tahfidz\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

/**
 * WP-11 — Sima'an: penyimakan hafalan dengan rentang bebas (juz/surah-ayat).
 * Idempoten via client_ref (unique per tenant) untuk entri dari PWA Guru.
 */
class SimaAn extends Model
{
    use BelongsToTenant;

    protected $table = 'sima_an';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'rentang',
        'penyimak_id',
        'nilai_kelancaran',
        'nilai_tajwid',
        'catatan',
        'tanggal',
        'client_ref',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nilai_kelancaran' => 'integer',
        'nilai_tajwid' => 'integer',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function penyimak(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyimak_id');
    }
}
