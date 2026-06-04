<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class AccessWali extends Model
{
    use BelongsToTenant;

    protected $table = 'access_wali';

    protected $fillable = [
        'tenant_id',
        'wali_id',
        'santri_id',
        'relationship_type',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function wali(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_id');
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }
}
