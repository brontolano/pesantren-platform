<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['primary_domain'] = $this->record->domains()->where('type', 'subdomain')->value('domain')
            ?? $this->record->domains()->value('domain');
        $data['website_domain'] = $this->record->domains()->where('type', 'website')->value('domain');
        $data['sistem_domain'] = $this->record->domains()->where('type', 'sistem')->value('domain');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $primaryDomain = $data['primary_domain'] ?? null;
        $websiteDomain = $data['website_domain'] ?? null;
        $sistemDomain = $data['sistem_domain'] ?? null;
        unset($data['primary_domain'], $data['website_domain'], $data['sistem_domain']);

        $record->update($data);
        foreach ([
            ['domain' => $primaryDomain, 'type' => 'subdomain'],
            ['domain' => $websiteDomain, 'type' => 'website'],
            ['domain' => $sistemDomain, 'type' => 'sistem'],
        ] as $mapping) {
            if (! filled($mapping['domain'])) {
                $record->domains()->where('type', $mapping['type'])->delete();

                continue;
            }

            $record->domains()->updateOrCreate(
                ['domain' => $mapping['domain']],
                [
                    'type' => $mapping['type'],
                    'is_primary' => $mapping['type'] !== 'subdomain' || (! filled($websiteDomain) && ! filled($sistemDomain)),
                ],
            );
        }

        return $record;
    }
}
