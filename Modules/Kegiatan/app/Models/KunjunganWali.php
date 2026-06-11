<?php

namespace Modules\Kegiatan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class KunjunganWali extends Model
{
    use BelongsToTenant;

    protected $table = 'kunjungan_wali';

    protected $fillable = [
        'tenant_id', 'santri_id', 'nama_pengunjung', 'hubungan',
        'tanggal', 'jam_datang', 'jam_pulang', 'keperluan', 'status',
        'pengajuan_id', 'slot',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
