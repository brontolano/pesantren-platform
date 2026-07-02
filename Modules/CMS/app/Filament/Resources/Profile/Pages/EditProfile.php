<?php

namespace Modules\CMS\Filament\Resources\Profile\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\CMS\Filament\Resources\Profile\ProfileResource;

class EditProfile extends EditRecord
{
    protected static string $resource = ProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
