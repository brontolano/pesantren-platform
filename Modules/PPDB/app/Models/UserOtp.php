<?php

namespace Modules\PPDB\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OTP WA untuk akun peserta (aktivasi & reset password).
 *
 * Patuh Hukum #2 Blueprint: tabel pendukung di atas `users` — BUKAN sistem
 * auth baru. Kolom `kode` berisi HASH, dibandingkan via service (F1.1.2).
 */
class UserOtp extends Model
{
    use BelongsToTenant;

    public const PURPOSE_AKTIVASI = 'aktivasi';

    public const PURPOSE_RESET = 'reset';

    /** Masa berlaku OTP dalam menit (AUTH-2). */
    public const TTL_MINUTES = 5;

    /** Maks. percobaan verifikasi sebelum OTP hangus (AUTH-4). */
    public const MAX_ATTEMPTS = 3;

    protected $table = 'user_otps';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'kode',
        'purpose',
        'expires_at',
        'attempts',
        'used_at',
    ];

    protected $hidden = [
        'kode',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    /** OTP masih bisa diverifikasi? */
    public function isUsable(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired() && ! $this->hasExceededAttempts();
    }
}
