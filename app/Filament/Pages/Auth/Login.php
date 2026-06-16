<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Login panel tenant (/admin) DISATUKAN ke halaman login bermerek di /masuk.
 *
 * Daripada menampilkan form login bawaan panel di /admin/login, halaman ini
 * MENGALIHKAN ke '/masuk' (StaffLoginController::show merender login bermerek
 * di SEMUA domain tenant). Dulu redirect ke '/' — tapi pada tenant yang '/'-nya
 * dipakai WEBSITE publik (subdomain), '/' bukan halaman login → admin jadi tak
 * bisa diakses. '/masuk' selalu form login. Dengan begitu:
 *   - GET /admin/login            → redirect '/masuk'
 *   - tamu buka /admin            → redirect-guest panel → /admin/login → '/masuk'
 *
 * Didaftar via TenantPanelProvider->login(self::class). AMAN: tidak menimpa
 * route web GET /admin/login (yang dulu memicu 500), hanya mengganti page class
 * yang sudah dirutekan Filament. Autentikasi tetap di POST /masuk.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        redirect()->to('/masuk');
    }
}
