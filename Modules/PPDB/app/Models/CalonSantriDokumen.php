<?php

namespace Modules\PPDB\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalonSantriDokumen extends Model
{
    use BelongsToTenant;

    protected $table = 'calon_santri_dokumen';

    protected $fillable = [
        'tenant_id',
        'calon_santri_id',
        'jenis_dokumen',
        'file_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function calonSantri(): BelongsTo
    {
        return $this->belongsTo(CalonSantri::class, 'calon_santri_id');
    }
}
