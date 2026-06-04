<?php

namespace App\Filament\Resources\CMS\Profile\Pages;

use App\Filament\Resources\CMS\Profile\ProfileResource;
use Filament\Resources\Pages\EditRecord;

class EditProfile extends EditRecord
{
    protected static string $resource = ProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
