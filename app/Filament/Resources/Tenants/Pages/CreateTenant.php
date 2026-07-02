<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Field domain bukan kolom tenant — keluarkan sebelum create.
        $domains = array_filter([
            $data['primary_domain'] ?? null,
            $data['website_domain'] ?? null,
            $data['sistem_domain'] ?? null,
        ]);
        $primaryDomain = $data['primary_domain'] ?? null;
        $websiteDomain = $data['website_domain'] ?? null;
        $sistemDomain = $data['sistem_domain'] ?? null;
        unset($data['primary_domain'], $data['website_domain'], $data['sistem_domain']);

        /** @var Tenant $tenant */
        // create() memicu TenantCreated -> CreateDatabase + MigrateDatabase (sync).
        $tenant = static::getModel()::create($data);

        // Daftarkan SEMUA domain yang diisi (deduplikasi).
        foreach (array_unique($domains) as $domain) {
            $type = match ($domain) {
                $websiteDomain => 'website',
                $sistemDomain => 'sistem',
                default => 'subdomain',
            };

            $tenant->domains()->updateOrCreate(
                ['domain' => $domain],
                [
                    'type' => $type,
                    'is_primary' => $type !== 'subdomain' || count(array_unique($domains)) === 1,
                ]
            );
        }

        // Seed roles + baris tenant lokal + user admin (DRY via service bersama).
        $adminDomain = $sistemDomain ?? $websiteDomain ?? (($primaryDomain ?? $tenant->getKey()).'.localhost');
        $creds = app(TenantProvisioner::class)->provision($tenant, 'admin@'.$adminDomain);

        if ($creds['password'] !== null) {
            Notification::make()
                ->title('Tenant siap — kredensial admin awal')
                ->body(
                    "Login: {$creds['email']}\n".
                    "Password: {$creds['password']}\n\n".
                    'Hanya ditampilkan SEKALI. Berikan ke admin pesantren & minta ganti setelah login pertama.'
                )
                ->persistent()
                ->success()
                ->send();
        }

        return $tenant;
    }
}
