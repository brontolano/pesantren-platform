<?php

namespace App\Filament\Resources\PlatformAnnouncements\Pages;

use App\Filament\Resources\PlatformAnnouncements\PlatformAnnouncementResource;
use Filament\Resources\Pages\EditRecord;

class EditPlatformAnnouncement extends EditRecord
{
    protected static string $resource = PlatformAnnouncementResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();
        if (($data['status'] ?? null) === 'published' && $this->record->status !== 'published') {
            $data['published_by_user_id'] = auth()->id();
        }

        return $data;
    }
}
