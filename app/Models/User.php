<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\BelongsToTenant;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Kesantrian\Models\Santri;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'tenant_id', 'email_authentication_enabled'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasEmailAuthentication
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, BelongsToTenant, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_authentication_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function hasEmailAuthentication(): bool
    {
        return (bool) $this->email_authentication_enabled;
    }

    public function toggleEmailAuthentication(bool $condition): void
    {
        $this->forceFill([
            'email_authentication_enabled' => $condition,
        ])->save();
    }

    public function accessWali(): HasMany
    {
        return $this->hasMany(AccessWali::class, 'wali_id');
    }

    public function accessibleSantri(): BelongsToMany
    {
        return $this->belongsToMany(Santri::class, 'access_wali', 'wali_id', 'santri_id')
            ->withPivot(['tenant_id', 'relationship_type', 'is_primary'])
            ->withTimestamps();
    }
}
