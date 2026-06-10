<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

/**
 * G3-22 — Program/jenjang formal (kurikulum) + capaian pembelajaran (outcome).
 */
class ProgramStudi extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'program_studi';

    protected $fillable = [
        'tenant_id', 'kode', 'nama', 'jenjang', 'kurikulum', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function outcome(): HasMany
    {
        return $this->hasMany(ProgramOutcome::class, 'program_id')->orderBy('urutan');
    }
}
