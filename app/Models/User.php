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
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Kesantrian\Models\Santri;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'must_reset_password', 'tenant_id', 'email_authentication_enabled'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasEmailAuthentication, FilamentUser
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
            'must_reset_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            // Panel platform (central) — hanya pemilik platform (super_admin tanpa tenant)
            return $this->hasRole('super_admin') && $this->tenant_id === null;
        }

        if ($panel->getId() === 'portal') {
            // Portal peserta PPDB — hanya akun calon santri ber-role `peserta`.
            return $this->hasRole('peserta');
        }

        // Panel tenant (staf) — terhubung ke sebuah tenant DAN bukan akun eksternal.
        // Peserta (PPDB) & wali memakai portalnya sendiri; tidak boleh masuk panel staf.
        return $this->tenant_id !== null && ! $this->hasAnyRole(['peserta', 'wali']);
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
