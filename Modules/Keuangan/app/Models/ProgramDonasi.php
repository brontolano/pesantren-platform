<?php

namespace Modules\Keuangan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Ledger\Models\SumberDana;

class ProgramDonasi extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    protected $table = 'program_donasi';

    protected $fillable = [
        'tenant_id',
        'nama',
        'deskripsi',
        'target_nominal',
        'sumber_dana_id',
        'aktif',
    ];

    protected $casts = [
        'target_nominal' => 'decimal:2',
        'aktif' => 'boolean',
    ];

    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class, 'sumber_dana_id');
    }

    public function donasi(): HasMany
    {
        return $this->hasMany(Donasi::class, 'program_donasi_id');
    }
}
