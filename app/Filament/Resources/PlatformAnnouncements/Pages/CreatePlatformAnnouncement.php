<?php

namespace App\Filament\Resources\PlatformAnnouncements\Pages;

use App\Filament\Resources\PlatformAnnouncements\PlatformAnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformAnnouncement extends CreateRecord
{
    protected static string $resource = PlatformAnnouncementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();
        $data['published_by_user_id'] = ($data['status'] ?? null) === 'published' ? auth()->id() : null;

        return $data;
    }
}
