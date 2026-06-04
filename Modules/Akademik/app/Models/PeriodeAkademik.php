<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeAkademik extends Model
{
    use BelongsToTenant;

    protected $table = 'periode_akademik';

    protected $fillable = [
        'tenant_id',
        'nama',
        'tahun_akademik',
        'semester',
        'tanggal_mulai',
        'tanggal_akhir',
        'is_active',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_akhir' => 'date',
        'is_active' => 'boolean',
    ];

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'periode_akademik_id');
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class, 'periode_akademik_id');
    }

    public function rapor(): HasMany
    {
        return $this->hasMany(Rapor::class, 'periode_akademik_id');
    }
}
