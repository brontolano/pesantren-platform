<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create {name} {subdomain}';

    protected $description = 'Create a new tenant with the given name and subdomain';

    public function handle(): int
    {
        $name = $this->argument('name');
        $subdomain = $this->argument('subdomain');

        $tenant = Tenant::create([
            'id' => $subdomain,
            'name' => $name,
            'is_active' => true,
        ]);

        $tenant->domains()->create([
            'domain' => $subdomain,
        ]);

        $this->info("Tenant '{$name}' created with subdomain '{$subdomain}'");

        return self::SUCCESS;
    }
}
