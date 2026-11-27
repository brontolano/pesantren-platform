<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePanelSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');
        $isPlatform = $path === 'platform' || str_starts_with($path, 'platform/');
        $isTenant = $path === 'pondok' || str_starts_with($path, 'pondok/');

        if ($isPlatform || $isTenant) {
            config([
                'session.cookie' => $isPlatform
                    ? config('session.platform_cookie')
                    : config('session.tenant_cookie'),
                'session.path' => $isPlatform ? '/platform' : '/pondok',
                'session.domain' => null,
            ]);
        }

        return $next($request);
    }
}
