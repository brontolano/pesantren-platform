<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModule extends Model
{
    protected $fillable = ['tenant_id', 'module_name', 'is_active', 'installed_at', 'configured_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'installed_at' => 'datetime',
        'configured_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function isModuleActive(string $tenantId, string $moduleName): bool
    {
        return static::where('tenant_id', $tenantId)
            ->where('module_name', $moduleName)
            ->where('is_active', true)
            ->exists();
    }

    /** Tandai modul "siap pakai" (langkah wajib tuntas) — jejak persisten agar
     *  navigasi tak berkedip bila data sempat kosong. Idempoten. */
    public function tandaiSiap(): void
    {
        if ($this->configured_at === null) {
            $this->forceFill(['configured_at' => now()])->save();
        }
    }
}
