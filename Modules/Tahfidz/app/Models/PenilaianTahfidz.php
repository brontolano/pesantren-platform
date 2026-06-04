<?php

namespace Modules\Tahfidz\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class PenilaianTahfidz extends Model
{
    use BelongsToTenant;

    protected $table = 'tahfidz_penilaian';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'hafalan_id',
        'jenis',
        'nilai',
        'catatan',
        'tanggal',
        'input_by',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
        'tanggal' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function hafalan(): BelongsTo
    {
        return $this->belongsTo(HafalanTahfidz::class, 'hafalan_id');
    }
}
