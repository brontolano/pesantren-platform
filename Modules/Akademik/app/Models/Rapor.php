<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class Rapor extends Model
{
    use BelongsToTenant;

    protected $table = 'rapor';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'periode_akademik_id',
        'total_sks',
        'ipk',
        'rank_kelas',
        'catatan_walikelas',
        'ringkasan_nilai',
        'generated_at',
    ];

    protected $casts = [
        'total_sks' => 'float',
        'ipk' => 'float',
        'rank_kelas' => 'integer',
        'ringkasan_nilai' => 'array',
        'generated_at' => 'datetime',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function periodeAkademik(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'periode_akademik_id');
    }
}
