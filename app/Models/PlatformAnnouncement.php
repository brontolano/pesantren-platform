<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAnnouncement extends Model
{
    protected $fillable = [
        'target_scope', 'tenant_id', 'title', 'body', 'category', 'priority', 'status', 'published_at', 'ends_at',
        'created_by_user_id', 'updated_by_user_id', 'published_by_user_id',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(PlatformAnnouncementRead::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeVisibleTo(Builder $query, string $tenantId): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $target) => $target
                ->where('target_scope', 'all')
                ->orWhere(fn (Builder $specific) => $specific
                    ->where('target_scope', 'tenant')
                    ->where('tenant_id', $tenantId)))
            ->where(fn (Builder $expiry) => $expiry->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
