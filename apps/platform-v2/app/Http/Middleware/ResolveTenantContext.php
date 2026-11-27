<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\TenantDomain;
use App\Services\Tenancy\TenantConnection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(private readonly TenantConnection $connection) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! str_starts_with(trim($request->path(), '/'), 'pondok')) {
            return $next($request);
        }

        $host = strtolower(rtrim($request->getHost(), '.'));
        $domain = TenantDomain::query()->where('domain', $host)->first();

        if (! $domain) {
            abort(404, 'Organisasi pada domain ini tidak ditemukan.');
        }

        $organization = Organization::query()->findOrFail($domain->organization_id);
        abort_unless($organization->setup_state === 'ready', 404, 'Ruang kerja organisasi belum tersedia.');
        abort_unless($organization->service_state === 'active', 403, 'Layanan organisasi sedang tidak aktif.');

        $this->connection->forOrganization($organization);
        app()->instance('current-organization', $organization);

        return $next($request);
    }
}
