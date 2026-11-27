<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformSupportTicket extends Model
{
    protected $fillable = [
        'ticket_number', 'tenant_id', 'created_by_user_id', 'category', 'impact', 'priority', 'subject', 'description',
        'status', 'status_updated_by', 'status_updated_at', 'last_reply_at',
    ];

    protected function casts(): array
    {
        return ['status_updated_at' => 'datetime', 'last_reply_at' => 'datetime'];
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PlatformSupportReply::class);
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
