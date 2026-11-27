<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $connection = 'central';

    public $timestamps = false;

    protected $fillable = ['organization_id', 'actor_type', 'actor_id', 'event', 'subject_type', 'subject_id', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Log aktivitas bersifat append-only.'));
        static::deleting(fn () => throw new \LogicException('Log aktivitas bersifat append-only.'));
    }
}
