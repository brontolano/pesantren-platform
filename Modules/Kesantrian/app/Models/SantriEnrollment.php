<?php

namespace Modules\Kesantrian\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SantriEnrollment extends Model
{
    use BelongsToTenant;

    protected $table = 'santri_enrollments';

    protected $fillable = [
        'tenant_id', 'santri_id', 'step', 'status', 'file_path', 'captured_by', 'captured_at',
    ];

    protected $casts = ['captured_at' => 'datetime'];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
