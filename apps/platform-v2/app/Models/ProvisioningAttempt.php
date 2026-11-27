<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningAttempt extends Model
{
    protected $connection = 'central';

    protected $fillable = ['organization_id', 'idempotency_key', 'state', 'steps', 'error_code', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
