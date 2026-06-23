<?php

namespace Modules\CMS\Filament\Resources\WebsiteSetting\Pages;

use Modules\CMS\Filament\Resources\WebsiteSetting\WebsiteSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteSettings extends ListRecords
{
    protected static string $resource = WebsiteSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

