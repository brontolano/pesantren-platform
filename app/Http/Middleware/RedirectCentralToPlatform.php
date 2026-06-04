<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel tenant terdaftar di path /admin. Bila path itu diakses dari domain
 * central (mis. pesantren.online/admin), arahkan ke panel owner /platform
 * alih-alih melempar 403 dari PreventAccessFromCentralDomains.
 */
class RedirectCentralToPlatform
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getHost(), config('tenancy.central_domains', []), true)) {
            return redirect('/platform');
        }

        return $next($request);
    }
}
