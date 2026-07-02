<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\BelongsToTenant;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Kesantrian\Models\Santri;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'must_reset_password', 'tenant_id', 'email_authentication_enabled'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasEmailAuthentication
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Pin guard Spatie ke 'web'. Semua peran/izin di-seed di guard 'web'; tanpa
     * pin ini, request via guard 'sanctum' (aplikasi native) membuat Spatie
     * Guard::getDefaultName ikut driver aktif → 'sanctum' → izin 'web' tak ketemu
     * → can()/can:izin palsu-false (mis. can:komentar 403 padahal user berhak).
     * Konsisten dgn Filament & web yang juga guard 'web'. NOL logika uang/nilai.
     */
    protected $guard_name = 'web';

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
            return $this->isPlatformSuperAdmin();
        }

        // Panel tenant (staf). Peserta/wali ditolak (portal sendiri).
        return $this->isTenantStaff();
    }

    /**
     * Pemilik PLATFORM = super_admin tanpa tenant (mengelola lintas tenant).
     * Satu-satunya sumber definisi "platform super admin", dipakai ulang oleh
     * gerbang modul (ModuleGate / CheckModuleActive) agar bypass modul HANYA untuk
     * platform — super admin PESANTREN ikut aturan modul tenantnya sehingga
     * navigasi tetap ramping (hanya modul yang tersedia/siap untuk tenant itu).
     */
    public function isPlatformSuperAdmin(): bool
    {
        return $this->tenant_id === null && $this->hasRole('super_admin');
    }

    /**
     * Staf tenant = terhubung ke sebuah tenant DAN bukan akun eksternal. Peserta
     * (PPDB), wali, & jemaah (publik) memakai portal/halaman sendiri (PWA /spmb,
     * /wali, Beranda publik) dan tidak boleh masuk panel staf. Dipakai ulang oleh
     * canAccessPanel() & App\Support\AppLauncher sebagai satu-satunya sumber
     * definisi "staf".
     */
    public function isTenantStaff(): bool
    {
        return $this->tenant_id !== null && ! $this->hasAnyRole(['peserta', 'wali', 'jemaah']);
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
