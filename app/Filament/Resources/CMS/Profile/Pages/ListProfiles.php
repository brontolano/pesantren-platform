<?php

namespace App\Filament\Resources\CMS\Profile\Pages;

use App\Filament\Resources\CMS\Profile\ProfileResource;
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
