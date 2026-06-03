<?php

namespace Modules\Kesantrian\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alumni extends Model
{
    use BelongsToTenant;

    protected $table = 'alumni';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'tahun_lulus',
        'status_lanjutan',
        'sekolah_tujuan',
        'catatan',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
