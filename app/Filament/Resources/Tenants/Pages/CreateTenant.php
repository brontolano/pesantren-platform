<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $domain = $data['primary_domain'];
        unset($data['primary_domain']);

        /** @var \App\Models\Tenant $tenant */
        $tenant = static::getModel()::create($data);
        $tenant->domains()->create([
            'domain' => $domain,
        ]);

        return $tenant;
    }
}
