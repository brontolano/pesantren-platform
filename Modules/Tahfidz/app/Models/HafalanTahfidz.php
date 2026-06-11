<?php

namespace Modules\Tahfidz\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Kesantrian\Models\Santri;

class HafalanTahfidz extends Model
{
    use BelongsToTenant;

    protected $table = 'tahfidz_hafalan';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'halaqah_id',
        'surah',
        'ayat_awal',
        'ayat_akhir',
        'tanggal_target',
        'tanggal_selesai',
        'status',
        'catatan',
        'client_ref',
    ];

    protected $casts = [
        'tanggal_target' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(Halaqah::class, 'halaqah_id');
    }

    public function penilaian(): HasMany
    {
        return $this->hasMany(PenilaianTahfidz::class, 'hafalan_id');
    }
}
