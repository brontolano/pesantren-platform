<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSupportReply extends Model
{
    protected $fillable = ['platform_support_ticket_id', 'author_user_id', 'author_name', 'is_internal', 'body'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(PlatformSupportTicket::class, 'platform_support_ticket_id');
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
