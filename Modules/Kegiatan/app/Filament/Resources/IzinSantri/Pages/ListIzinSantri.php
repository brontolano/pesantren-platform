<?php

namespace Modules\Kegiatan\Filament\Resources\IzinSantri\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Kegiatan\Filament\Resources\IzinSantriResource;

class ListIzinSantri extends ListRecords
{
    protected static string $resource = IzinSantriResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
