<?php

namespace App\Http\Middleware;

use App\Models\TenantModule;
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

        if (! TenantModule::isModuleActive(tenant()->getTenantKey(), $moduleName)) {
            abort(403, 'Module is not active for this tenant.');
        }

        return $next($request);
    }
}
