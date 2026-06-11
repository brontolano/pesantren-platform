<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Kesantrian\Models\Kelas;
use Modules\PPDB\Models\PaketSoal;
use Modules\PPDB\Models\UjianAttempt;

/**
 * F4 — Jadwal sesi ujian akademik per rombel: satu paket soal dibuka untuk
 * satu kelas pada jendela waktu tertentu. Attempt santri menempel ke sesi
 * (unique partial: satu attempt per santri per sesi).
 */
class SesiUjianAkademik extends Model
{
    use BelongsToTenant;

    protected $table = 'sesi_ujian_akademik';

    protected $fillable = [
        'tenant_id', 'paket_soal_id', 'pelajaran_id', 'kelas_id',
        'periode_akademik_id', 'nama', 'mulai_at', 'selesai_at',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'mulai_at' => 'datetime',
        'selesai_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketSoal::class, 'paket_soal_id');
    }

    public function pelajaran(): BelongsTo
    {
        return $this->belongsTo(Pelajaran::class, 'pelajaran_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function periodeAkademik(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'periode_akademik_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(UjianAttempt::class, 'sesi_ujian_akademik_id');
    }

    /** Sedang dibuka (aktif + dalam jendela waktu)? */
    public function sedangBerlangsung(): bool
    {
        return $this->is_active
            && now()->greaterThanOrEqualTo($this->mulai_at)
            && now()->lessThanOrEqualTo($this->selesai_at);
    }
}
