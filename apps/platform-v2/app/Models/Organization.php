<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'name', 'slug', 'organization_type', 'canonical_domain', 'setup_state', 'service_state',
        'first_admin_email', 'provisioning_key', 'setup_steps', 'tenant_database_name',
        'tenant_database_username', 'tenant_database_password', 'last_setup_error_code',
        'last_setup_at', 'last_technical_check_at', 'last_technical_check_state',
    ];

    protected $hidden = ['tenant_database_password', 'provisioning_key'];

    protected function casts(): array
    {
        return [
            'setup_steps' => 'array',
            'last_setup_at' => 'immutable_datetime',
            'last_technical_check_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            $organization->provisioning_key ??= (string) Str::uuid();
            $organization->setup_state ??= 'draft';
            $organization->service_state ??= 'inactive';
            $organization->setup_steps ??= [];
        });
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(PlatformInvitation::class);
    }

    public function provisioningAttempts(): HasMany
    {
        return $this->hasMany(ProvisioningAttempt::class);
    }

    public function moduleEntitlements(): HasMany
    {
        return $this->hasMany(TenantModuleEntitlement::class);
    }

    public function databasePassword(): ?string
    {
        $stored = $this->getRawOriginal('tenant_database_password');

        return $stored ? decrypt($stored) : null;
    }

    public function setDatabasePassword(?string $password): void
    {
        $this->tenant_database_password = $password === null ? null : encrypt($password);
    }

    public function scopeInSetup($query)
    {
        return $query->whereIn('setup_state', ['pending', 'running', 'failed']);
    }
}
