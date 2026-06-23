<?php

namespace Modules\CMS\Filament\Resources\Profile\Pages;

use Modules\CMS\Filament\Resources\Profile\ProfileResource;
use Filament\Resources\Pages\EditRecord;

class EditProfile extends EditRecord
{
    protected static string $resource = ProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

