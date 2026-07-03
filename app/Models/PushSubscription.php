<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F6 — Web Push (VAPID). Satu baris = satu subscription browser milik satu
 * user (opt-in). Suplemen kanal notifikasi; WA tetap kanal utama.
 */
class PushSubscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
