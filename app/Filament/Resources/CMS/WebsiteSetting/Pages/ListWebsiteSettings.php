<?php

namespace App\Filament\Resources\CMS\WebsiteSetting\Pages;

use App\Filament\Resources\CMS\WebsiteSetting\WebsiteSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteSettings extends ListRecords
{
    protected static string $resource = WebsiteSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

