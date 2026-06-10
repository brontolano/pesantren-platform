<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G3-22 — Capaian pembelajaran (outcome) sebuah program.
 */
class ProgramOutcome extends Model
{
    use BelongsToTenant;

    protected $table = 'program_outcome';

    protected $fillable = [
        'tenant_id', 'program_id', 'kode', 'deskripsi', 'urutan',
    ];

    protected $casts = [
        'urutan' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_id');
    }
}
