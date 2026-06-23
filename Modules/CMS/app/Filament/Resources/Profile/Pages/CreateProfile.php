<?php

namespace Modules\CMS\Filament\Resources\Profile\Pages;

use Modules\CMS\Filament\Resources\Profile\ProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProfile extends CreateRecord
{
    protected static string $resource = ProfileResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = tenant('id');
        $data['sort_order'] = $data['sort_order'] ?? 2;
        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }
}

