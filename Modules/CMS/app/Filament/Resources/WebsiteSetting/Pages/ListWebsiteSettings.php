<?php

namespace Modules\CMS\Filament\Resources\WebsiteSetting\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\CMS\Filament\Resources\WebsiteSetting\WebsiteSettingResource;

class ListWebsiteSettings extends ListRecords
{
    protected static string $resource = WebsiteSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
