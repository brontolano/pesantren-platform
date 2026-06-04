<?php

namespace Modules\Kesehatan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class RiwayatKesehatan extends Model
{
    use BelongsToTenant;

    protected $table = 'riwayat_kesehatan';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'tanggal',
        'keluhan',
        'diagnosa',
        'tindakan',
        'kondisi',
        'petugas',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
