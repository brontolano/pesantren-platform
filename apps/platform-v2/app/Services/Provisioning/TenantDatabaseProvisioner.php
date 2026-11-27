<?php

namespace App\Services\Provisioning;

use App\Models\Organization;

interface TenantDatabaseProvisioner
{
    public function provision(Organization $organization): void;
}
