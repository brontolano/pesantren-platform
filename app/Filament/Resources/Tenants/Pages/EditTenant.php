<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['primary_domain'] = $this->record->domains()->value('domain');

        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $domain = $data['primary_domain'];
        unset($data['primary_domain']);

        $record->update($data);
        $record->domains()->updateOrCreate(
            ['tenant_id' => $record->getKey()],
            ['domain' => $domain],
        );

        return $record;
    }
}

