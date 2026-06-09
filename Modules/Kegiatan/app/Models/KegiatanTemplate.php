<?php

namespace Modules\Kegiatan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;

/**
 * G3-19 — Template kegiatan yang dapat di-clone berulang.
 * default_phases: [{nama, offset_hari, durasi_hari}] relatif tanggal kegiatan.
 */
class KegiatanTemplate extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'kegiatan_template';

    protected $fillable = [
        'tenant_id', 'nama', 'deskripsi', 'jenis', 'default_phases', 'is_active',
    ];

    protected $casts = [
        'default_phases' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
