<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * API key per tenant untuk akses API publik headless (CENTRAL).
 *
 * Keamanan: plaintext key TIDAK PERNAH disimpan — hanya hash sha256 (`key_hash`).
 * Plaintext hanya muncul sekali (dikembalikan oleh issue()). Verifikasi memakai
 * hash_equals (timing-safe). `prefix` untuk identifikasi kunci di UI tanpa rahasia.
 */
class TenantApiKey extends Model
{
    public const PLAIN_PREFIX = 'psa_';

    protected $fillable = [
        'tenant_id',
        'label',
        'key_hash',
        'prefix',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Paksa koneksi central — tabel ini hanya ada di DB central, tapi model
     * sering dipanggil saat tenancy aktif (middleware api.key di domain tenant,
     * panel tenant). Tanpa ini query akan kena DB tenant & gagal (tabel tak ada).
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }

    /** Hash sha256 (hex) dari kunci plaintext. */
    public static function hashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Buat kunci plaintext acak ber-prefix. */
    public static function generatePlainKey(): string
    {
        return self::PLAIN_PREFIX.Str::random(40);
    }

    /**
     * Siapkan atribut + kunci plaintext untuk satu key baru (belum disimpan).
     *
     * @return array{plain:string, attributes:array<string,mixed>}
     */
    public static function buildFor(string $tenantId, ?string $label = null): array
    {
        $plain = self::generatePlainKey();

        return [
            'plain' => $plain,
            'attributes' => [
                'tenant_id' => $tenantId,
                'label' => $label,
                'key_hash' => self::hashKey($plain),
                'prefix' => substr($plain, 0, 12),
            ],
        ];
    }

    /** Apakah kunci ini masih aktif (belum dicabut). */
    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /** Cocokkan kunci plaintext dgn hash tersimpan (timing-safe). */
    public function matches(string $plain): bool
    {
        return is_string($this->key_hash)
            && hash_equals($this->key_hash, self::hashKey($plain));
    }

    /** @param Builder $query */
    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }
}
