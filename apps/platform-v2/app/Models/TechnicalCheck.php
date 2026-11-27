<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalCheck extends Model
{
    protected $connection = 'central';

    protected $fillable = ['organization_id', 'check_key', 'state', 'checked_at', 'failure_code', 'recovery_step'];

    protected function casts(): array
    {
        return ['checked_at' => 'immutable_datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
