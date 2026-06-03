<?php

namespace Modules\Kesantrian\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenempatanKelas extends Model
{
    use BelongsToTenant;

    protected $table = 'penempatan_kelas';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'kelas_id',
        'semester',
        'tahun_akademik',
        'tanggal_penempatan',
        'alasan_mutasi',
        'is_current',
    ];

    protected $casts = [
        'tanggal_penempatan' => 'date',
        'is_current' => 'boolean',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }
}
