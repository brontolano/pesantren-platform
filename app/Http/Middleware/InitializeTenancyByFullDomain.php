<?php

namespace App\Http\Middleware;

use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

/**
 * Wrapper di atas InitializeTenancyByDomain dari stancl/tenancy.
 * Resolve tenant dari full domain (bukan hanya subdomain).
 * Mendukung: subdomain.localhost, asy-syifaa.sch.id, sistem.asy-syifaa.sch.id
 */
class InitializeTenancyByFullDomain extends InitializeTenancyByDomain
{
    // Inherit semua logika dari stancl/tenancy InitializeTenancyByDomain.
    // Override hanya jika perlu custom domain resolution di masa depan.
}
