<?php

namespace Modules\Kesantrian\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Tahfidz\Models\Halaqah;

class PresensiAsrama extends Model
{
    use BelongsToTenant;

    protected $table = 'presensi_asrama';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'tanggal',
        'slot',
        'status',
        'keterangan',
        'pencatat_id',
        'kamar_id',
        'halaqah_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(KamarAsrama::class, 'kamar_id');
    }

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(Halaqah::class, 'halaqah_id');
    }
}
