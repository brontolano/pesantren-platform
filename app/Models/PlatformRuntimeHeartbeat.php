<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformRuntimeHeartbeat extends Model
{
    protected $primaryKey = 'service_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['service_key', 'last_seen_at', 'detail'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
