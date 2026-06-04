<?php

namespace Modules\Kegiatan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class PresensiKegiatan extends Model
{
    use BelongsToTenant;

    protected $table = 'presensi_kegiatan';

    protected $fillable = ['tenant_id', 'kegiatan_id', 'santri_id', 'status', 'keterangan'];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id');
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
