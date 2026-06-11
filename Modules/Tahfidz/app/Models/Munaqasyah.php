<?php

namespace Modules\Tahfidz\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kesantrian\Models\Santri;

/**
 * WP-11 — Munaqasyah: ujian kelulusan juz. hasil ∈ {lulus, mengulang}
 * (CHECK DB). Daftar wisuda = hasil 'lulus' DAN is_peserta_wisuda true.
 */
class Munaqasyah extends Model
{
    use BelongsToTenant;

    protected $table = 'munaqasyah';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'juz_diuji',
        'penguji_id',
        'hasil',
        'nilai',
        'tanggal',
        'periode_akademik_id',
        'is_peserta_wisuda',
        'client_ref',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nilai' => 'decimal:2',
        'is_peserta_wisuda' => 'boolean',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function penguji(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penguji_id');
    }

    public function periodeAkademik(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'periode_akademik_id');
    }

    /** Hanya yang layak masuk daftar wisuda: LULUS dan ditandai peserta. */
    public function scopePesertaWisuda(Builder $query): Builder
    {
        return $query->where('hasil', 'lulus')->where('is_peserta_wisuda', true);
    }
}
