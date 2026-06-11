<?php

namespace Modules\Kesantrian\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Akademik\Models\Lembaga;

class Alumni extends Model
{
    use BelongsToTenant;

    protected $table = 'alumni';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'tahun_lulus',
        'tanggal_lulus',
        'lembaga_id',
        'nomor_syahadah',
        'nomor_syahadah_urut',
        'nomor_ijazah',
        'jenis_ijazah',
        'status_lanjutan',
        'sekolah_tujuan',
        'catatan',
    ];

    protected $casts = [
        'tanggal_lulus' => 'date',
        'nomor_syahadah_urut' => 'integer',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class, 'lembaga_id');
    }
}
