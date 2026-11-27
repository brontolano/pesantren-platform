<?php

namespace App\Services\Tenancy;

use App\Models\Organization;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class TenantConnection
{
    public function forOrganization(Organization $organization): void
    {
        if (! $organization->tenant_database_name || ! $organization->tenant_database_username || ! $organization->databasePassword()) {
            throw new InvalidArgumentException('Konfigurasi database tenant belum lengkap.');
        }

        $tenantConfig = [
            ...Config::get('database.connections.central'),
            'database' => $organization->tenant_database_name,
            'username' => $organization->tenant_database_username,
            'password' => $organization->databasePassword(),
        ];
        if (app()->environment('testing')) {
            $tenantConfig = [
                'driver' => config('tenancy.testing_driver', 'sqlite'),
                'database' => $organization->tenant_database_name,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }
        Config::set('database.connections.tenant', $tenantConfig);
        DB::purge('tenant');
        Config::set('filesystems.disks.tenant', [
            'driver' => 'local',
            'root' => storage_path(rtrim(config('tenancy.storage_root'), '/').'/'.$organization->id),
            'serve' => false,
            'throw' => true,
        ]);
    }
}
