<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAnnouncementRead extends Model
{
    protected $fillable = ['platform_announcement_id', 'tenant_id', 'user_id', 'read_at', 'archived_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(PlatformAnnouncement::class, 'platform_announcement_id');
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }
}
