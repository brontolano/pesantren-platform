<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSessionMatches
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = app('current-organization');
        abort_unless($request->user('tenant')?->is_active, 403, 'Akun admin organisasi tidak aktif.');
        abort_unless((int) $request->session()->get('tenant_context_id') === (int) $organization->id, 403, 'Sesi ini tidak cocok dengan organisasi pada domain tersebut.');

        return $next($request);
    }
}
