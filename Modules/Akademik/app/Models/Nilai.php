<?php

namespace Modules\Akademik\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

class Nilai extends Model
{
    use BelongsToTenant;

    protected $table = 'nilai';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'pelajaran_id',
        'periode_akademik_id',
        'kelas_id',
        'nilai_harian',
        'nilai_uts',
        'nilai_uas',
        'nilai_akhir',
        'nilai_komponen',
        'catatan',
        'input_by',
    ];

    protected $casts = [
        'nilai_harian' => 'float',
        'nilai_uts' => 'float',
        'nilai_uas' => 'float',
        'nilai_akhir' => 'float',
        'nilai_komponen' => 'array',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function pelajaran(): BelongsTo
    {
        return $this->belongsTo(Pelajaran::class, 'pelajaran_id');
    }

    public function periodeAkademik(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'periode_akademik_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
