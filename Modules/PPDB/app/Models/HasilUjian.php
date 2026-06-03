<?php

namespace Modules\PPDB\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilUjian extends Model
{
    use BelongsToTenant;

    protected $table = 'hasil_ujian';

    protected $fillable = [
        'tenant_id',
        'calon_santri_id',
        'kategori_ujian',
        'nilai',
        'catatan',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
    ];

    public function calonSantri(): BelongsTo
    {
        return $this->belongsTo(CalonSantri::class, 'calon_santri_id');
    }
}
