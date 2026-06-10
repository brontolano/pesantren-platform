<?php

namespace Modules\Kesantrian\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sampel tulisan tangan berlabel — dataset HTR (sistem membaca tulisan santri). */
class HandwritingSample extends Model
{
    use BelongsToTenant;

    protected $table = 'handwriting_samples';

    protected $fillable = [
        'tenant_id', 'santri_id', 'script', 'form_type', 'form_version',
        'file_path', 'ground_truth', 'device', 'status_qc', 'captured_at',
    ];

    protected $casts = ['ground_truth' => 'array', 'captured_at' => 'datetime'];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
