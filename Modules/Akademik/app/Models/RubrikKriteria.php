<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

/**
 * G3-18 — Kriteria rubrik kompetensi (berbobot). Agregat = Σ(skor×bobot)/Σbobot.
 */
class RubrikKriteria extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'rubrik_kriteria';

    protected $fillable = [
        'tenant_id', 'nama', 'pelajaran_id', 'periode_akademik_id', 'bobot', 'is_active',
    ];

    protected $casts = [
        'bobot' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function pelajaran(): BelongsTo
    {
        return $this->belongsTo(Pelajaran::class, 'pelajaran_id');
    }

    public function skor(): HasMany
    {
        return $this->hasMany(RubrikSkor::class, 'kriteria_id');
    }
}
