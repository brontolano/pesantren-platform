<?php

namespace Modules\CMS\Filament\Resources\Profile\Pages;

use Modules\CMS\Filament\Resources\Profile\ProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProfiles extends ListRecords
{
    protected static string $resource = ProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => ProfileResource::canCreate()),
        ];
    }
}

