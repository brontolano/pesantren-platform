<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleActive
{
    public function handle(Request $request, Closure $next, string $moduleName): Response
    {
        if (! tenancy()->initialized) {
            abort(403, 'No tenant context.');
        }

        // Pemilik platform melihat semua (kelola lintas tenant).
        $user = $request->user();
        if ($user instanceof User && $user->isPlatformSuperAdmin()) {
            return $next($request);
        }

        // Selaras dengan ModuleService: modul CORE selalu boleh, development hanya
        // tenant demo, released hanya bila diaktifkan untuk tenant. (Sebelumnya
        // middleware ini hanya mengecek tenant_modules → mengabaikan is_core,
        // sehingga saat default modul minimal, rute modul INTI bisa keliru 403.)
        if (! app(ModuleService::class)->isVisibleForTenant($moduleName, tenant())) {
            abort(403, 'Module is not active for this tenant.');
        }

        return $next($request);
    }
}
