<?php

namespace Modules\Akademik\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

/**
 * WP-07 — Keputusan sidang kenaikan kelas.
 *
 * rekomendasi = hasil mesin (KenaikanKelasService::evaluasi);
 * keputusan   = hasil rapat (boleh override rekomendasi);
 * finalized_at NULL = draft — gerbang idempotensi finalisasi.
 */
class KeputusanKenaikan extends Model
{
    use Auditable;
    use BelongsToTenant;

    public const KEPUTUSAN = ['naik', 'tinggal', 'lulus'];

    protected $table = 'keputusan_kenaikan';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'periode_akademik_id',
        'kelas_asal_id',
        'kelas_tujuan_id',
        'rekomendasi',
        'keputusan',
        'alasan',
        'catatan',
        'diputuskan_oleh',
        'finalized_at',
    ];

    protected $casts = [
        'alasan' => 'array',
        'finalized_at' => 'datetime',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function periodeAkademik(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'periode_akademik_id');
    }

    public function kelasAsal(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_asal_id');
    }

    public function kelasTujuan(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_tujuan_id');
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }
}
