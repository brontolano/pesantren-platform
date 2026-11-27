<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    protected $connection = 'central';

    protected $fillable = ['organization_id', 'domain', 'is_canonical', 'verified_at'];

    protected function casts(): array
    {
        return ['is_canonical' => 'boolean', 'verified_at' => 'immutable_datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
