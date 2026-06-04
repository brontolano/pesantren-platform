<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class PortalWaliIzin extends Model
{
    use BelongsToTenant;

    protected $table = 'portal_wali_izin';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'wali_id',
        'jenis_izin',
        'tanggal_mulai',
        'tanggal_selesai',
        'alasan',
        'catatan_wali',
        'status',
        'diproses_oleh',
        'diproses_pada',
        'catatan_admin',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'diproses_pada' => 'datetime',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function wali(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_id');
    }

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
