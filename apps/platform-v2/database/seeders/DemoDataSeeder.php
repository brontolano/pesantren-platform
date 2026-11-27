<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\PlatformOperator;
use App\Models\TenantDomain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Demo fixtures may only be seeded in the local environment.');
        }

        $email = (string) config('tenancy.demo_operator_email');
        $password = (string) config('tenancy.demo_operator_password');
        if ($email === '' || strlen($password) < 16) {
            throw new \RuntimeException('Set DEMO_OPERATOR_EMAIL and a DEMO_OPERATOR_PASSWORD of at least 16 characters before seeding local demo data.');
        }

        PlatformOperator::query()->updateOrCreate(
            ['email' => $email],
            ['name' => 'Operator Demo Lokal', 'password' => Hash::make($password), 'is_active' => true],
        );

        $domain = config('tenancy.demo_domain');
        $organization = Organization::query()->firstOrCreate(
            ['canonical_domain' => $domain],
            [
                'name' => 'Pondok Pesantren Demo Fiktif',
                'slug' => 'pondok-pesantren-demo-fiktif',
                'organization_type' => 'pesantren',
                'first_admin_email' => 'admin@'.$domain,
                'setup_state' => 'draft',
                'service_state' => 'inactive',
            ],
        );

        TenantDomain::query()->firstOrCreate(
            ['domain' => $domain],
            ['organization_id' => $organization->id, 'is_canonical' => true],
        );

        $this->call(ModuleCatalogSeeder::class);
    }
}
