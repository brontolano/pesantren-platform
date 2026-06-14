<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Login panel tenant (/admin) DISATUKAN ke halaman login bermerek di ROOT '/'.
 *
 * Daripada menampilkan form login bawaan panel di /admin/login, halaman ini
 * langsung MENGALIHKAN ke '/' (StaffLoginController merender login bermerek di
 * sana). Dengan begitu:
 *   - GET /admin/login            → redirect '/'
 *   - tamu buka /admin            → redirect-guest panel → /admin/login → '/'
 *
 * Didaftar via TenantPanelProvider->login(self::class). Cara ini AMAN: tidak
 * menimpa route web GET /admin/login (yang dulu memicu 500), hanya mengganti
 * page class yang sudah dirutekan Filament. Autentikasi tetap di POST /masuk
 * (StaffLoginController), jadi form bawaan ini memang tak pernah ditampilkan.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        redirect()->to('/');
    }
}
