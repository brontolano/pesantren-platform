<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * SSO panel: auto-login panel web dari tautan bertanda-tangan, untuk WebView aplikasi native.
 *
 * Hanya bisa dibuka via URL signed dari `POST /api/v1/auth/panel-link` (TTL ≤ 60s). Middleware
 * `signed` memvalidasi tanda tangan + kadaluwarsa; `nonce` mencegah pemakaian ulang (replay).
 * Tenant ter-resolve dari domain (URL pada domain tenant yang sama dgn token diterbitkan).
 *
 * NOL logika uang. Otorisasi per-halaman tetap dijaga Filament/RBAC setelah redirect.
 */
class PanelMasukController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $nonce = (string) $request->query('nonce', '');
        abort_if($nonce === '', 403);

        // Sekali-pakai: Cache::add() mengembalikan false bila nonce sudah pernah dipakai.
        $fresh = Cache::add('panel-link:'.$nonce, true, now()->addMinutes(5));
        abort_unless($fresh, 403, 'Tautan sudah digunakan.');

        // Login sesi web (guard yang dipakai Filament). User di DB tenant berjalan.
        Auth::guard('web')->loginUsingId($request->query('user'));
        abort_unless(Auth::guard('web')->check(), 403);

        // Path sudah di-whitelist saat pembuatan tautan (panelLink).
        $path = '/'.ltrim((string) $request->query('path', '/admin'), '/');

        return redirect($path);
    }
}
